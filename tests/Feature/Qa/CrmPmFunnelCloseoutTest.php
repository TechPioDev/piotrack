<?php

declare(strict_types=1);

/**
 * CRM + Funnel Management + Project Management close-out
 * (Phase 51 — CRM-024/025/027, FUNL-019/020, PROJ-015/016).
 *
 * Marketing-owner assignment, rule-based lead routing ahead of the tested
 * round-robin, enrichment through the provider seam with set-once fills,
 * funnel ROI/lead-quality from records on both sides, and the period-bounded
 * review data pack for QBRs and strategy reviews.
 */

use App\Authorization\Role;
use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\AssignmentRule;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Engagement;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Funnel;
use App\Models\KpiTarget;
use App\Models\Pipeline;
use App\Services\Marketing\FunnelService;
use App\Services\Marketing\LeadCaptureService;
use App\Services\Strategy\ReviewPacketService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('CrmPmFunnel Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function closeoutDeal(array $attributes): Deal
{
    $pipeline = Pipeline::where('is_default', true)->with('stages')->firstOrFail();
    $stage = ($attributes['status'] ?? 'open') === 'won'
        ? $pipeline->stages->firstWhere('is_won', true)
        : $pipeline->stages->firstWhere('is_won', false);

    return Deal::create($attributes + ['pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'name' => 'D'.uniqid()]);
}

it('assigns a marketing owner beside the sales owner (CRM-024)', function () {
    $marketer = addMember($this->org, Role::MarketingManager);

    $this->actingAs($this->owner)->post(route('crm.deals.store'), ['name' => 'Owned deal', 'value' => 100])->assertRedirect();
    $deal = Deal::firstOrFail();

    $this->actingAs($this->owner)->patch(route('crm.deals.update', $deal), [
        'name' => 'Owned deal', 'marketing_owner_id' => $marketer->id,
    ])->assertRedirect();
    expect($deal->refresh()->marketing_owner_id)->toBe($marketer->id);

    $props = $this->actingAs($this->owner)->get(route('crm.deals.show', $deal))->assertOk()->viewData('page')['props'];
    expect($props['deal']['marketing_owner'])->toBe($marketer->name);

    // A user outside the org is refused.
    [$otherOrg, $otherOwner] = makeOrganization('Elsewhere Org');
    app(CurrentOrganization::class)->set($this->org);
    $this->actingAs($this->owner)->patch(route('crm.deals.update', $deal), [
        'name' => 'Owned deal', 'marketing_owner_id' => $otherOwner->id,
    ])->assertSessionHasErrors('marketing_owner_id');
});

it('routes new leads by rules first, round-robin as the fallback (CRM-025)', function () {
    $specialist = addMember($this->org, Role::SalesRepresentative);
    $form = Form::create(['name' => 'Contact form', 'slug' => 'cf'.uniqid(), 'status' => 'published', 'fields' => []]);

    // Rule: everything captured from a form goes to the specialist.
    AssignmentRule::create(['position' => 1, 'field' => 'lead_source', 'value' => 'form', 'user_id' => $specialist->id]);
    $ruled = app(LeadCaptureService::class)->capture($form, ['email' => 'ruled@corp.test', 'first_name' => 'R']);
    expect($ruled->owner_id)->toBe($specialist->id);

    // An email-domain rule beats position-later rules and the fallback.
    $domainRep = addMember($this->org, Role::SalesRepresentative);
    AssignmentRule::create(['position' => 0, 'field' => 'email_domain', 'value' => 'bigfish.com', 'user_id' => $domainRep->id]);
    $whale = app(LeadCaptureService::class)->capture($form, ['email' => 'cfo@bigfish.com', 'first_name' => 'W']);
    expect($whale->owner_id)->toBe($domainRep->id);

    // No rules left: falls back to the tested least-loaded round-robin.
    AssignmentRule::query()->delete();
    $fallback = app(LeadCaptureService::class)->capture($form, ['email' => 'plain@nowhere.test', 'first_name' => 'F']);
    expect($fallback->owner_id)->not->toBeNull();
});

it('enriches contacts through the seam with set-once fills and audited provenance (CRM-027)', function () {
    $contact = Contact::create(['first_name' => 'E', 'email' => 'ops@precision-mfg.example']);

    $this->actingAs($this->owner)->post(route('crm.contacts.enrich', $contact))->assertRedirect();

    $company = Company::firstOrFail();
    expect($contact->refresh()->company_id)->toBe($company->id)
        ->and($company->name)->toBe('Precision Mfg')
        ->and($company->industry)->not->toBeNull()
        ->and(AuditLog::where('action', 'crm.contact.enriched')->first()?->context['provider'] ?? null)->toBe('fixture');

    // Set-once: operator data is never overwritten by a second enrichment.
    $company->update(['industry' => 'Aerospace (operator-set)']);
    $this->actingAs($this->owner)->post(route('crm.contacts.enrich', $contact))->assertRedirect();
    expect($company->refresh()->industry)->toBe('Aerospace (operator-set)');

    // A personal mailbox honestly yields nothing.
    $personal = Contact::create(['first_name' => 'P', 'email' => 'someone@gmail.com']);
    $this->actingAs($this->owner)->post(route('crm.contacts.enrich', $personal))->assertRedirect();
    expect($personal->refresh()->company_id)->toBeNull();
});

it('computes funnel ROI and lead quality from records on both sides (FUNL-019/020)', function () {
    $funnel = Funnel::create(['name' => 'MSP acquisition']);
    $tof = $funnel->stages()->create(['name' => 'Leads', 'position' => 1, 'category' => 'tof', 'lifecycle_stage' => 'lead']);

    $form = Form::create(['name' => 'Guide form', 'slug' => 'gf'.uniqid(), 'status' => 'published', 'fields' => []]);
    $campaign = AdCampaign::create(['platform' => 'google_search', 'name' => 'Funnel ads', 'status' => 'active']);
    $tof->assets()->create(['asset_type' => 'form', 'asset_id' => $form->id]);
    $tof->assets()->create(['asset_type' => 'ad_campaign', 'asset_id' => $campaign->id]);
    AdMetric::create(['ad_campaign_id' => $campaign->id, 'date' => now()->toDateString(), 'spend' => 100000, 'clicks' => 50, 'provider' => 'fixture']);

    $buyer = Contact::create(['first_name' => 'B', 'email' => 'b@x.test', 'lead_score' => 80]);
    $browser = Contact::create(['first_name' => 'C', 'email' => 'c@x.test', 'lead_score' => 10]);
    FormSubmission::create(['form_id' => $form->id, 'contact_id' => $buyer->id, 'payload' => []]);
    FormSubmission::create(['form_id' => $form->id, 'contact_id' => $browser->id, 'payload' => []]);
    closeoutDeal(['status' => 'won', 'value' => 300000, 'contact_id' => $buyer->id, 'closed_at' => now()]);

    $roi = app(FunnelService::class)->roi($funnel);
    expect($roi['leads'])->toBe(2)
        ->and($roi['customers'])->toBe(1)
        ->and($roi['won_revenue'])->toBe(300000)
        ->and($roi['spend'])->toBe(100000)
        ->and($roi['roi'])->toBe(3.0)
        ->and($roi['bands'])->toBe(['hot' => 1, 'warm' => 0, 'cold' => 1])
        ->and($roi['avg_score'])->toBe(45.0);

    // No cost basis on record: an explicit null, never infinity.
    $bare = Funnel::create(['name' => 'No ads funnel']);
    expect(app(FunnelService::class)->roi($bare)['roi'])->toBeNull();

    // The funnel page carries it.
    $props = $this->actingAs($this->owner)->get(route('marketing.funnels.show', $funnel))->assertOk()->viewData('page')['props'];
    expect($props['roi']['roi'])->toBe(3.0);
});

it('generates the period-bounded review data pack for QBRs and strategy reviews (PROJ-015/016)', function () {
    Contact::create(['first_name' => 'New', 'email' => 'new@x.test']);
    closeoutDeal(['status' => 'won', 'value' => 450000, 'mrr' => 150000, 'closed_at' => now()]);
    KpiTarget::create(['metric' => 'leads', 'target_value' => 10, 'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth()]);

    $qbr = Engagement::create(['type' => 'qbr', 'title' => 'September review', 'scheduled_at' => now(), 'status' => 'scheduled']);
    $quarterly = Engagement::create(['type' => 'strategy_review', 'title' => 'Q3 strategy', 'scheduled_at' => now(), 'status' => 'scheduled']);
    $workshop = Engagement::create(['type' => 'workshop', 'title' => 'Not a review', 'scheduled_at' => now(), 'status' => 'scheduled']);

    $service = app(ReviewPacketService::class);
    expect($service->periodFor($qbr)['label'])->toBe(now()->format('F Y'))
        ->and($service->periodFor($quarterly)['label'])->toBe('Q'.now()->quarter.' '.now()->year);

    $text = collect($service->lines($qbr))->pluck('text')->implode("\n");
    expect($text)->toContain('New leads: 1')
        ->and($text)->toContain('Deals won: 1 ($4,500.00 value, $1,500.00 MRR)')
        ->and($text)->toContain('KPI attainment')
        ->and($text)->toContain('computed from platform records');

    // Download works for review types only.
    $this->actingAs($this->owner)->get(route('strategy.engagements.packet', $qbr))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($this->owner)->get(route('strategy.engagements.packet', $workshop))->assertNotFound();
});
