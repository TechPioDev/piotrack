<?php

namespace App\Services\Sales;

use App\Models\Booking;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\IntentSignal;
use App\Models\LandingPage;
use App\Models\MarketingList;
use App\Models\TargetAccount;
use App\Services\Marketing\ListService;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Account-based marketing (ABM). Scores a target account by aggregating the
 * lead scores + buyer intent of its company's contacts, maps the buying
 * committee with roles and engagement (multi-threading), syncs tier committees
 * into marketing lists for personalized ABM campaigns, generates per-account
 * landing pages, and assembles the per-account engagement report. Company
 * enrichment/org-chart data arrive via INTG connectors (external).
 */
class AccountService
{
    /** Title fragments that mark a likely decision maker when no explicit role is set (ABM-005). */
    private const DECISION_TITLES = [
        'ceo', 'cfo', 'cto', 'cio', 'coo', 'ciso', 'president', 'owner', 'founder',
        'vp', 'vice president', 'director', 'principal', 'partner', 'head of',
    ];

    public function __construct(
        private IntentService $intent,
        private AuditLogger $audit,
        private ListService $lists,
    ) {}

    public function score(TargetAccount $account): int
    {
        $contacts = Contact::where('company_id', $account->company_id)->get();

        $leadScore = (int) $contacts->sum('lead_score');
        $intentScore = $contacts->reduce(fn (int $carry, Contact $c) => $carry + $this->intent->intentScore($c), 0);

        $score = $leadScore + $intentScore;
        $account->update(['account_score' => $score]);

        $this->audit->log('sales.account.rescored', context: ['score' => $score], resourceType: 'target_account', resourceId: (string) $account->id, organizationId: $account->organization_id);

        return $score;
    }

    /**
     * @return Collection<int, Contact>
     */
    public function buyingCommittee(TargetAccount $account): Collection
    {
        return Contact::where('company_id', $account->company_id)->get();
    }

    /**
     * Whether a contact is a decision maker: the explicit buying_role wins,
     * the title heuristic covers unclassified contacts (ABM-005).
     */
    public function isDecisionMaker(Contact $contact): bool
    {
        if ($contact->buying_role !== null) {
            return $contact->buying_role === 'decision_maker';
        }

        $title = Str::lower((string) $contact->title);
        if ($title === '') {
            return false;
        }

        foreach (self::DECISION_TITLES as $fragment) {
            if (str_contains($title, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Engagement across the committee (ABM-008/018): who is actually engaged
     * (lead score or recent intent), and whether the account is multi-threaded
     * (two or more engaged people — one contact is a single thread that can
     * snap).
     *
     * @return array{committee_size: int, engaged: int, decision_makers: int, multi_threaded: bool}
     */
    public function engagement(TargetAccount $account): array
    {
        $committee = $this->buyingCommittee($account);

        $engaged = $committee->filter(fn (Contact $c) => $c->lead_score > 0 || $this->intent->intentScore($c) > 0);

        return [
            'committee_size' => $committee->count(),
            'engaged' => $engaged->count(),
            'decision_makers' => $committee->filter(fn (Contact $c) => $this->isDecisionMaker($c))->count(),
            'multi_threaded' => $engaged->count() >= 2,
        ];
    }

    /**
     * Sync a tier's buying committees into a marketing list (ABM-009/013):
     * campaigns target the list, merge tags personalize per contact. Idempotent
     * — re-syncing refreshes membership to the current committees.
     */
    public function syncTierList(int $tier): MarketingList
    {
        $list = MarketingList::firstOrCreate(
            ['name' => "ABM Tier {$tier} committee"],
            ['type' => 'static', 'description' => "Buying-committee contacts of every active Tier {$tier} target account. Managed by ABM sync."],
        );

        $accountCompanyIds = TargetAccount::where('tier', $tier)
            ->where('status', '!=', 'archived')->pluck('company_id');

        $contacts = Contact::whereIn('company_id', $accountCompanyIds)->get();
        foreach ($contacts as $contact) {
            $this->lists->addContact($list, $contact);
        }

        $this->audit->log('sales.account.list_synced', context: ['tier' => $tier, 'contacts' => $contacts->count()], resourceType: 'marketing_list', resourceId: (string) $list->id, organizationId: $list->organization_id);

        return $list->refresh();
    }

    /**
     * Generate a draft landing page personalized to the account's company
     * (ABM-010): reviewed before publishing at /p/{slug}.
     */
    public function createPage(TargetAccount $account, string $service): LandingPage
    {
        $company = (string) ($account->company?->name ?? 'your company');

        $base = Str::slug("{$service} for {$company}");
        $slug = $base;
        $i = 1;
        while (LandingPage::withoutGlobalScope('tenant')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        $page = LandingPage::create([
            'name' => "ABM — {$company}",
            'slug' => $slug,
            'headline' => "{$service} built for {$company}",
            'subheadline' => "A plan put together specifically for the {$company} team.",
            'body_html' => '<h2>'.e($service).' for '.e($company).'</h2>'
                .'<p>We prepared this page for '.e($company).' — what we would prioritize, how we would roll it out, and what it would cost to find out more.</p>'
                .'<h3>Why teams like '.e($company).' work with us</h3>'
                .'<p>Local, accountable delivery with response times in writing — and reporting that ties the work to results.</p>',
            'status' => 'draft',
        ]);

        $this->audit->log('sales.account.page_created', context: ['account' => $company, 'slug' => $page->slug], resourceType: 'landing_page', resourceId: (string) $page->id, organizationId: $page->organization_id);

        return $page;
    }

    /**
     * The per-account engagement report (ABM-015): everything known about the
     * account from real records — committee with roles and scores, open and won
     * revenue, meetings, and the recent intent trail.
     *
     * @return array<string, mixed>
     */
    public function report(TargetAccount $account): array
    {
        $committee = $this->buyingCommittee($account);
        $contactIds = $committee->pluck('id');

        return [
            'account' => [
                'id' => $account->id,
                'company' => $account->company?->name,
                'tier' => $account->tier,
                'status' => $account->status,
                'score' => $account->account_score,
            ],
            'engagement' => $this->engagement($account),
            'committee' => $committee->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->fullName(),
                'title' => $c->title,
                'buying_role' => $c->buying_role,
                'is_decision_maker' => $this->isDecisionMaker($c),
                'lead_score' => $c->lead_score,
                'intent_score' => $this->intent->intentScore($c),
                'lifecycle_stage' => $c->lifecycle_stage,
            ])->values()->all(),
            'deals' => Deal::where('company_id', $account->company_id)->get()
                ->map(fn (Deal $d) => ['id' => $d->id, 'name' => $d->name, 'status' => $d->status, 'value' => $d->value, 'mrr' => $d->mrr])->all(),
            'bookings' => Booking::whereIn('contact_id', $contactIds)->latest('scheduled_at')->limit(10)->get()
                ->map(fn (Booking $b) => ['id' => $b->id, 'name' => $b->name, 'status' => $b->status, 'scheduled_at' => $b->scheduled_at?->toIso8601String()])->all(),
            'signals' => IntentSignal::whereIn('contact_id', $contactIds)->latest('occurred_at')->limit(20)->get()
                ->map(fn (IntentSignal $s) => ['type' => $s->type, 'weight' => $s->weight, 'url' => $s->url, 'occurred_at' => $s->occurred_at?->toIso8601String()])->all(),
        ];
    }
}
