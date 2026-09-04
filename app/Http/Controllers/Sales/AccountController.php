<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\TargetAccount;
use App\Services\Sales\AbmPlayRunner;
use App\Services\Sales\AccountService;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AccountController extends Controller
{
    public function __construct(
        private AccountService $accounts,
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('sales/accounts/index', [
            'accounts' => TargetAccount::with('company:id,name')->orderBy('tier')->orderByDesc('account_score')->get()
                ->map(function (TargetAccount $a) {
                    $engagement = $this->accounts->engagement($a);

                    return [
                        'id' => $a->id,
                        'company' => $a->company?->name,
                        'tier' => $a->tier,
                        'status' => $a->status,
                        'account_score' => $a->account_score,
                        'committee' => $engagement['committee_size'],
                        'engaged' => $engagement['engaged'],
                        'decision_makers' => $engagement['decision_makers'],
                        'multi_threaded' => $engagement['multi_threaded'],
                    ];
                }),
            'companies' => Company::orderBy('name')->limit(200)->get(['id', 'name'])
                ->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', Rule::exists('companies', 'id')->where('organization_id', $this->currentOrganization->id())],
            'tier' => ['required', 'integer', 'min:1', 'max:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (TargetAccount::where('company_id', $data['company_id'])->exists()) {
            return back()->withErrors(['company_id' => __('That company is already a target account.')]);
        }

        $account = TargetAccount::create($data);
        $this->accounts->score($account);
        $this->audit->log('sales.account.created', context: ['tier' => $account->tier], resourceType: 'target_account', resourceId: (string) $account->id, organizationId: $account->organization_id);

        return back()->with('status', __('Target account added.'));
    }

    public function update(Request $request, TargetAccount $account): RedirectResponse
    {
        $account->update($request->validate([
            'tier' => ['required', 'integer', 'min:1', 'max:3'],
            'status' => ['required', Rule::in(['targeted', 'engaged', 'opportunity', 'won', 'lost'])],
        ]));

        return back()->with('status', __('Account updated.'));
    }

    public function rescore(TargetAccount $account): RedirectResponse
    {
        $score = $this->accounts->score($account);

        return back()->with('status', __('Account rescored (:n).', ['n' => $score]));
    }

    public function destroy(TargetAccount $account): RedirectResponse
    {
        $account->delete();

        return back()->with('status', __('Account removed.'));
    }

    /**
     * Per-account engagement report (ABM-015): committee, roles, revenue,
     * meetings and the intent trail — all real records.
     */
    public function report(TargetAccount $account): Response
    {
        return Inertia::render('sales/accounts/report', $this->accounts->report($account) + [
            // ABM-007: reporting lines a rep captured, as a forest.
            'org_chart' => $this->accounts->orgChart($account),
            // ABM-011: content targeted at this account, plus pieces available to attach.
            'content' => $this->accounts->content($account),
            'available_content' => ContentPiece::whereNull('company_id')->orderByDesc('id')->limit(100)
                ->get(['id', 'title', 'status'])
                ->map(fn (ContentPiece $p) => ['id' => $p->id, 'title' => $p->title, 'status' => $p->status]),
            // ABM-016/019: the runnable plays.
            'plays' => collect(AbmPlayRunner::PLAYS)->map(fn (string $description, string $key) => [
                'key' => $key, 'description' => $description,
            ])->values(),
        ]);
    }

    /**
     * ABM-007: set (or clear) who a committee contact reports to. Same-company
     * only, never self — reporting lines across companies are fiction.
     */
    public function setManager(Request $request, Contact $contact): RedirectResponse
    {
        $data = $request->validate([
            'reports_to_contact_id' => ['nullable', 'integer', TenantExists::active('contacts')],
        ]);

        $managerId = $data['reports_to_contact_id'] ?? null;
        if ($managerId !== null) {
            $manager = Contact::findOrFail($managerId);
            if ($manager->id === $contact->id || $manager->company_id !== $contact->company_id) {
                return back()->withErrors(['reports_to_contact_id' => __('A contact can only report to a different person at the same company.')]);
            }
        }

        $contact->update(['reports_to_contact_id' => $managerId]);

        return back()->with('status', __('Reporting line updated.'));
    }

    /** ABM-011: target an existing content piece at this account's company. */
    public function attachContent(Request $request, TargetAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'content_piece_id' => ['required', 'integer', TenantExists::in('content_pieces')],
        ]);

        ContentPiece::findOrFail((int) $data['content_piece_id'])->update(['company_id' => $account->company_id]);

        return back()->with('status', __('Content targeted at this account.'));
    }

    /**
     * ABM-012: the LinkedIn Campaign Manager company-list upload file.
     */
    public function linkedinExport(Request $request): SymfonyResponse
    {
        $data = $request->validate(['tier' => ['nullable', 'integer', 'min:1', 'max:3']]);
        $tier = isset($data['tier']) ? (int) $data['tier'] : null;

        return response($this->accounts->linkedinCompanyCsv($tier))
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="linkedin-abm-companies'.($tier !== null ? "-tier{$tier}" : '').'.csv"');
    }

    /** ABM-016/019: run one orchestration play against the account. */
    public function runPlay(Request $request, TargetAccount $account, AbmPlayRunner $plays): RedirectResponse
    {
        $data = $request->validate(['play' => ['required', Rule::in(array_keys(AbmPlayRunner::PLAYS))]]);

        $result = $plays->run($account, $data['play'], $request->user()?->id);

        return back()->with('play_result', $result);
    }

    /**
     * Sync a tier's buying committees into a marketing list (ABM-009/013) so
     * campaigns can target it with per-contact merge tags.
     */
    public function syncList(Request $request): RedirectResponse
    {
        $data = $request->validate(['tier' => ['required', 'integer', 'min:1', 'max:3']]);

        $list = $this->accounts->syncTierList((int) $data['tier']);

        return back()->with('status', __('List ":name" synced with :n contacts.', ['name' => $list->name, 'n' => $list->member_count]));
    }

    /** Draft a landing page personalized to this account (ABM-010). */
    public function createPage(Request $request, TargetAccount $account): RedirectResponse
    {
        $data = $request->validate(['service' => ['required', 'string', 'max:120']]);

        $page = $this->accounts->createPage($account, trim($data['service']));

        return back()->with('status', __('Draft landing page ":name" created — review it under Marketing → Landing pages.', ['name' => $page->name]));
    }
}
