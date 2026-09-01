<?php

declare(strict_types=1);

/**
 * ABM close-out (Phase 4 — ABM-005/008/009/010/013/015/018).
 *
 * Decision makers resolve from the explicit buying_role with a title heuristic
 * behind it; engagement counts who is actually active and calls two or more
 * engaged people multi-threaded; tier committees sync into marketing lists so
 * campaigns (with merge tags) become ABM email campaigns; landing pages
 * personalize per account; and the account report assembles committee, revenue,
 * meetings and intent from real records. Enrichment/org charts/LinkedIn stay
 * external.
 */

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\LandingPage;
use App\Models\MarketingList;
use App\Models\Pipeline;
use App\Models\TargetAccount;
use App\Services\Sales\AccountService;
use App\Services\Sales\IntentService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('ABM Org');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->company = Company::create(['name' => 'Precision Manufacturing Group']);
    $this->account = TargetAccount::create(['company_id' => $this->company->id, 'tier' => 1, 'status' => 'targeted']);
    app(CurrentOrganization::class)->forget();
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('identifies decision makers by explicit role first, title heuristic second', function () {
    app(CurrentOrganization::class)->set($this->org);
    $cfo = Contact::create(['first_name' => 'Michael', 'company_id' => $this->company->id, 'title' => 'Chief Financial Officer & CFO']);
    $vp = Contact::create(['first_name' => 'Vera', 'company_id' => $this->company->id, 'title' => 'VP of Operations']);
    $tech = Contact::create(['first_name' => 'Terry', 'company_id' => $this->company->id, 'title' => 'Help Desk Technician']);
    // Explicit role overrides the title in both directions.
    $blockerCeo = Contact::create(['first_name' => 'Bea', 'company_id' => $this->company->id, 'title' => 'CEO', 'buying_role' => 'blocker']);
    $championTech = Contact::create(['first_name' => 'Cham', 'company_id' => $this->company->id, 'title' => 'Sysadmin', 'buying_role' => 'decision_maker']);

    $service = app(AccountService::class);
    expect($service->isDecisionMaker($cfo))->toBeTrue()
        ->and($service->isDecisionMaker($vp))->toBeTrue()
        ->and($service->isDecisionMaker($tech))->toBeFalse()
        ->and($service->isDecisionMaker($blockerCeo))->toBeFalse()
        ->and($service->isDecisionMaker($championTech))->toBeTrue();

    $engagement = $service->engagement($this->account);
    expect($engagement['committee_size'])->toBe(5)->and($engagement['decision_makers'])->toBe(3);
});

it('measures engagement and multi-threading from real activity (ABM-008/018)', function () {
    app(CurrentOrganization::class)->set($this->org);
    $a = Contact::create(['first_name' => 'A', 'company_id' => $this->company->id, 'lead_score' => 40]);
    $b = Contact::create(['first_name' => 'B', 'company_id' => $this->company->id]);
    Contact::create(['first_name' => 'C', 'company_id' => $this->company->id]);

    $service = app(AccountService::class);
    expect($service->engagement($this->account)['multi_threaded'])->toBeFalse()
        ->and($service->engagement($this->account)['engaged'])->toBe(1);

    // A second person shows buying intent: the account becomes multi-threaded.
    app(IntentService::class)->record($b, 'high_intent_page', 8, '/pricing');
    $engagement = $service->engagement($this->account);
    expect($engagement['engaged'])->toBe(2)->and($engagement['multi_threaded'])->toBeTrue();

    // The account score aggregates lead + intent across the committee.
    expect($service->score($this->account))->toBe(48);
});

it('syncs a tier committee into a marketing list for ABM campaigns (ABM-009/013)', function () {
    app(CurrentOrganization::class)->set($this->org);
    Contact::create(['first_name' => 'M1', 'email' => 'm1@pmg.test', 'company_id' => $this->company->id]);
    Contact::create(['first_name' => 'M2', 'email' => 'm2@pmg.test', 'company_id' => $this->company->id]);
    // A tier-2 account's people must not leak into the tier-1 list.
    $other = Company::create(['name' => 'Other Co']);
    TargetAccount::create(['company_id' => $other->id, 'tier' => 2, 'status' => 'targeted']);
    Contact::create(['first_name' => 'X', 'email' => 'x@other.test', 'company_id' => $other->id]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)
        ->post(route('sales.accounts.sync-list'), ['tier' => 1])
        ->assertRedirect()->assertSessionHasNoErrors();

    $list = MarketingList::withoutGlobalScopes()->where('name', 'ABM Tier 1 committee')->firstOrFail();
    expect($list->member_count)->toBe(2);

    // Re-sync is idempotent.
    $this->actingAs($this->owner)->post(route('sales.accounts.sync-list'), ['tier' => 1]);
    expect($list->fresh()->member_count)->toBe(2);
});

it('drafts a landing page personalized to the account (ABM-010)', function () {
    $this->actingAs($this->owner)
        ->post(route('sales.accounts.page.create', $this->account->id), ['service' => 'Managed Cybersecurity'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $page = LandingPage::withoutGlobalScope('tenant')->firstOrFail();
    expect($page->status)->toBe('draft')
        ->and($page->headline)->toBe('Managed Cybersecurity built for Precision Manufacturing Group')
        ->and($page->body_html)->toContain('Precision Manufacturing Group')
        ->and((int) $page->organization_id)->toBe((int) $this->org->id);
});

it('assembles the per-account report from real records (ABM-015)', function () {
    app(CurrentOrganization::class)->set($this->org);
    $contact = Contact::create(['first_name' => 'Michael', 'email' => 'm@pmg.test', 'company_id' => $this->company->id, 'title' => 'CFO', 'lead_score' => 40]);
    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    Deal::create([
        'name' => 'CMMC Program', 'pipeline_id' => $pipeline->id, 'stage_id' => $pipeline->stages()->first()->id,
        'company_id' => $this->company->id, 'contact_id' => $contact->id, 'status' => 'won', 'value' => 5400000, 'mrr' => 450000,
    ]);
    app(IntentService::class)->record($contact, 'high_intent_page', 8, '/pricing');
    app(CurrentOrganization::class)->forget();

    $response = $this->actingAs($this->owner)
        ->get(route('sales.accounts.report', $this->account->id))
        ->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props['account']['company'])->toBe('Precision Manufacturing Group')
        ->and($props['committee'][0]['is_decision_maker'])->toBeTrue()
        ->and($props['deals'][0]['name'])->toBe('CMMC Program')
        ->and($props['signals'][0]['type'])->toBe('high_intent_page');

    // Tenant seal: an outsider's account id 404s for this org's members and
    // vice versa — the report is bound by the tenant scope.
    [$otherOrg, $otherOwner] = makeOrganization('Other ABM Org');
    subscribeOrganization($otherOrg, 'enterprise');
    $this->actingAs($otherOwner)
        ->get(route('sales.accounts.report', $this->account->id))
        ->assertNotFound();
});
