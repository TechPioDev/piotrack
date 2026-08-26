<?php

declare(strict_types=1);

/**
 * Module 00 baseline: the whole commercial story as one journey, through the
 * real HTTP endpoints in order. Registration → organization → team invite →
 * campaign → company → contact → lead → scoring → conversion → pipeline →
 * closed-won revenue → analytics. Each rung asserts, so a regression names the
 * exact step where the business flow breaks instead of failing at the end.
 */

use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\ScoringRule;
use App\Models\User;
use App\Notifications\OrganizationInvitation;
use App\Support\CurrentOrganization;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

it('walks a lead from signup to attributed closed-won revenue', function () {
    Notification::fake();

    // ---- Signup: Daniel Carter registers and creates the workspace ----------
    $this->post(route('register'), [
        'name' => 'Daniel Carter',
        'email' => 'daniel@acme-it.test',
        'password' => 'Journey-Acme-1!',
        'password_confirmation' => 'Journey-Acme-1!',
    ])->assertRedirect();

    $daniel = User::where('email', 'daniel@acme-it.test')->firstOrFail();
    $daniel->markEmailAsVerified();

    $this->actingAs($daniel)
        ->post(route('organizations.store'), ['name' => 'Acme Managed IT Services'])
        ->assertRedirect(route('dashboard'));

    $org = Organization::withoutGlobalScopes()->where('name', 'Acme Managed IT Services')->firstOrFail();
    // Plan activation stands in for Stripe checkout, which cannot run in tests.
    subscribeOrganization($org, 'enterprise');

    // ---- Team: Sarah Mitchell is invited and accepts ------------------------
    $this->actingAs($daniel)
        ->post(route('invitations.store'), ['email' => 'sarah@acme-it.test', 'role' => 'sales_representative'])
        ->assertRedirect();

    // The plaintext token lives only in the emailed link (the DB stores a hash),
    // so read it out of the mail exactly as the invitee would.
    $token = null;
    Notification::assertSentOnDemand(OrganizationInvitation::class, function ($notification) use (&$token) {
        $url = $notification->toMail(new AnonymousNotifiable)->actionUrl;
        $token = basename(parse_url($url, PHP_URL_PATH));

        return true;
    });
    expect($token)->not->toBeNull();

    $this->post(route('logout'));
    $this->post(route('register'), [
        'name' => 'Sarah Mitchell',
        'email' => 'sarah@acme-it.test',
        'password' => 'Journey-Acme-2!',
        'password_confirmation' => 'Journey-Acme-2!',
    ]);
    $sarah = User::where('email', 'sarah@acme-it.test')->firstOrFail();
    $sarah->markEmailAsVerified();

    $this->actingAs($sarah)->post(route('invitations.accept', $token))->assertRedirect(route('dashboard'));
    expect($org->members()->where('email', 'sarah@acme-it.test')->exists())->toBeTrue();

    // ---- Campaign the lead will be attributed to ----------------------------
    $this->actingAs($daniel)
        ->post(route('marketing.campaigns.store'), [
            'name' => 'Philadelphia CMMC Growth',
            'channel' => 'email',
            'subject' => 'CMMC readiness for Philadelphia manufacturers',
        ])->assertRedirect();
    $campaign = Campaign::withoutGlobalScope('tenant')->where('name', 'Philadelphia CMMC Growth')->firstOrFail();
    expect((int) $campaign->organization_id)->toBe((int) $org->id);

    // ---- CRM: prospect company and contact ----------------------------------
    $this->actingAs($daniel)->post(route('crm.companies.store'), [
        'name' => 'Precision Manufacturing Group',
        'industry' => 'Manufacturing',
        'size' => '180',
    ])->assertRedirect();
    $company = Company::withoutGlobalScope('tenant')->where('name', 'Precision Manufacturing Group')->firstOrFail();

    $this->actingAs($daniel)->post(route('crm.contacts.store'), [
        'first_name' => 'Michael',
        'last_name' => 'Rodriguez',
        'email' => 'michael@precisionmfg.test',
        'title' => 'CFO',
        'company_id' => $company->id,
        'lead_source' => 'Website',
        'campaign' => 'Philadelphia CMMC Growth',
    ])->assertRedirect();
    expect(Contact::withoutGlobalScope('tenant')->where('email', 'michael@precisionmfg.test')->exists())->toBeTrue();

    // ---- Lead, assigned to the salesperson ----------------------------------
    $this->actingAs($daniel)->post(route('crm.leads.store'), [
        'first_name' => 'Michael',
        'last_name' => 'Rodriguez',
        'email' => 'michael@precisionmfg.test',
        'company_name' => 'Precision Manufacturing Group',
        'source' => 'Website',
        'campaign' => 'Philadelphia CMMC Growth',
        'owner_id' => $sarah->id,
    ])->assertRedirect();
    $lead = Lead::withoutGlobalScope('tenant')->where('email', 'michael@precisionmfg.test')->firstOrFail();
    expect((int) $lead->owner_id)->toBe((int) $sarah->id);

    // ---- Scoring: a CFO title is worth points -------------------------------
    app(CurrentOrganization::class)->set($org);
    ScoringRule::create([
        'name' => 'Finance decision maker',
        'category' => 'demographic',
        'attribute' => 'title',
        'operator' => 'contains',
        'value' => 'CFO',
        'points' => 40,
        'is_active' => true,
    ]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($daniel)->post(route('sales.scoring.recompute'))->assertRedirect();
    $contact = Contact::withoutGlobalScope('tenant')->where('email', 'michael@precisionmfg.test')->firstOrFail();
    expect((int) $contact->lead_score)->toBeGreaterThanOrEqual(40);

    // ---- Conversion into a deal, with MSP economics -------------------------
    $this->actingAs($daniel)
        ->post(route('crm.leads.convert', $lead->id), ['create_deal' => true, 'deal_value' => 54000])
        ->assertRedirect();
    $deal = Deal::withoutGlobalScope('tenant')->where('id', $lead->refresh()->converted_deal_id)->firstOrFail();

    $this->actingAs($daniel)->patch(route('crm.deals.update', $deal->id), [
        'name' => 'Managed Cybersecurity + CMMC',
        'value' => 54000,
        'mrr' => 4500,
        'contract_term_months' => 12,
        'campaign' => 'Philadelphia CMMC Growth',
        'owner_id' => $sarah->id,
    ])->assertRedirect();
    $deal->refresh();
    // Money is stored in cents; ARR is annualised from MRR by the model.
    expect((int) $deal->mrr)->toBe(450000)->and((int) $deal->arr)->toBe(5400000);

    // ---- Pipeline: walk every stage to closed-won ---------------------------
    $stages = $deal->pipeline->stages()->orderBy('position')->get();
    foreach ($stages as $stage) {
        $this->actingAs($daniel)
            ->patch(route('crm.deals.stage', $deal->id), ['stage_id' => $stage->id])
            ->assertRedirect();
        if ($stage->is_won) {
            break;
        }
    }
    $deal->refresh();
    expect($deal->status)->toBe('won')->and($deal->closed_at)->not->toBeNull();

    // ---- Analytics: the revenue shows up where decisions are made -----------
    $dashboard = $this->actingAs($daniel)->get(route('analytics.dashboard'))->assertOk();
    $metrics = $dashboard->viewData('page')['props']['metrics'];
    expect((int) $metrics['revenue']['mrr'])->toBe(450000)
        ->and((int) $metrics['revenue']['arr'])->toBe(5400000)
        ->and((int) $metrics['funnel']['leads'])->toBeGreaterThanOrEqual(1)
        ->and((int) $metrics['funnel']['closed_won'])->toBe(1)
        ->and($metrics['sources'])->toHaveKey('Website');

    // Attribution credits the campaign with the full contract value and can
    // replay the buyer journey.
    $attribution = $this->actingAs($daniel)->get(route('analytics.attribution.index'))->assertOk();
    $props = $attribution->viewData('page')['props'];
    expect((int) $props['campaigns']['Philadelphia CMMC Growth'])->toBe(5400000)
        ->and((int) $props['channels']['Website'])->toBe(5400000)
        ->and(collect($props['journeys'])->pluck('name'))->toContain('Michael Rodriguez');
});
