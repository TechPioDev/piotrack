<?php

declare(strict_types=1);

/**
 * Strategy Insights (Phase 5 — STRAT-001..004/006/010..013/017..023).
 *
 * The workspace was already tested; these rows close because the product now
 * COMPUTES the analyses from the tenant's own records. The honesty guards are
 * part of the contract: the revenue model refuses to project below five closed
 * deals, and the ICP refuses to describe an ideal customer with no wins.
 */

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Funnel;
use App\Models\Keyword;
use App\Models\Pipeline;
use App\Models\SeoAudit;
use App\Models\Visitor;
use App\Services\Strategy\StrategyInsights;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Strategy Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
    $this->pipeline = Pipeline::where('is_default', true)->firstOrFail();
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function makeDeal(string $name, string $status, int $mrr, ?int $companyId = null, ?string $source = null): Deal
{
    $pipeline = Pipeline::where('is_default', true)->firstOrFail();

    return Deal::create([
        'name' => $name, 'status' => $status, 'mrr' => $mrr, 'value' => $mrr * 12,
        'pipeline_id' => $pipeline->id, 'stage_id' => $pipeline->stages()->first()->id,
        'company_id' => $companyId, 'lead_source' => $source,
    ]);
}

it('finds keyword opportunities and maps geographic markets from tracked keywords', function () {
    Keyword::create(['phrase' => 'msp services', 'intent' => 'commercial', 'search_volume' => 900, 'difficulty' => 30, 'is_tracked' => true]); // unranked
    Keyword::create(['phrase' => 'it support philadelphia', 'intent' => 'commercial', 'search_volume' => 400, 'location' => 'Philadelphia, PA', 'current_position' => 14, 'is_tracked' => true]);
    Keyword::create(['phrase' => 'ranked keyword', 'intent' => 'informational', 'search_volume' => 100, 'current_position' => 3, 'is_tracked' => true]);

    $insights = app(StrategyInsights::class);

    $opportunities = $insights->keywordOpportunities();
    expect(collect($opportunities['opportunities'])->pluck('phrase'))->toContain('msp services', 'it support philadelphia')
        ->and(collect($opportunities['opportunities'])->pluck('phrase'))->not->toContain('ranked keyword')
        ->and($opportunities['opportunities'][0]['phrase'])->toBe('msp services') // higher volume first
        ->and($opportunities['intent_mix']['commercial'])->toBe(2);

    $markets = $insights->geoMarkets();
    expect($markets)->toHaveCount(1)
        ->and($markets[0]['market'])->toBe('Philadelphia, PA')
        ->and($markets[0]['opportunities'])->toBe(1);
});

it('computes vertical performance and a data-driven ICP from won deals', function () {
    $mfg = Company::create(['name' => 'Mfg Co', 'industry' => 'Manufacturing']);
    $law = Company::create(['name' => 'Law Co', 'industry' => 'Legal']);

    makeDeal('M1', 'won', 450000, $mfg->id, 'referral');
    makeDeal('M2', 'won', 350000, $mfg->id, 'referral');
    makeDeal('M3', 'lost', 200000, $mfg->id, 'ads');
    makeDeal('L1', 'won', 250000, $law->id, 'website');

    $insights = app(StrategyInsights::class);

    $verticals = collect($insights->verticalPerformance());
    $manufacturing = $verticals->firstWhere('industry', 'Manufacturing');
    expect($manufacturing['deals'])->toBe(3)
        ->and($manufacturing['won'])->toBe(2)
        ->and($manufacturing['win_rate'])->toBe(66.7)
        ->and($manufacturing['won_mrr'])->toBe(800000)
        ->and($verticals->first()['industry'])->toBe('Manufacturing'); // sorted by won MRR

    $icp = $insights->icpProfile();
    expect($icp['insufficient_data'])->toBeFalse()
        ->and(array_key_first($icp['top_industries']))->toBe('Manufacturing')
        ->and(array_key_first($icp['top_sources']))->toBe('referral')
        ->and($icp['median_mrr'])->toBe(350000);
});

it('refuses to model revenue on thin history, then projects from the real win rate', function () {
    makeDeal('Open A', 'open', 100000);
    makeDeal('W1', 'won', 100000);
    makeDeal('L1', 'lost', 100000);

    $model = app(StrategyInsights::class)->revenueModel();
    expect($model['insufficient_data'])->toBeTrue()
        ->and($model['projected_mrr'])->toBeNull();

    // Five closed deals: 3 won / 2 lost = 60% win rate on 100,000¢ open MRR.
    makeDeal('W2', 'won', 100000);
    makeDeal('W3', 'won', 100000);
    makeDeal('L2', 'lost', 100000);

    $model = app(StrategyInsights::class)->revenueModel();
    expect($model['insufficient_data'])->toBeFalse()
        ->and($model['win_rate'])->toBe(60.0)
        ->and($model['open_mrr'])->toBe(100000)
        ->and($model['projected_mrr'])->toBe(60000);
});

it('audits conversion, CRM hygiene, funnels and lead-gen gaps from real records', function () {
    // Conversion chain + an unconverted source (3 visitors, none identified).
    $contact = Contact::create(['first_name' => 'Ida', 'email' => 'ida@x.test', 'owner_id' => $this->owner->id]);
    Visitor::create(['visitor_key' => 'v1', 'contact_id' => $contact->id, 'utm_source' => 'newsletter', 'first_seen_at' => now(), 'visits' => 1]);
    foreach (range(2, 4) as $i) {
        Visitor::create(['visitor_key' => "v{$i}", 'utm_source' => 'bing-ads', 'first_seen_at' => now(), 'visits' => 1]);
    }

    // Hygiene: a contact with no owner/company/email.
    Contact::create(['first_name' => 'Orphan']);

    // A funnel stage with no assets.
    $funnel = Funnel::create(['name' => 'Audit Funnel']);
    $funnel->stages()->create(['name' => 'Empty', 'position' => 1, 'category' => 'tof', 'lifecycle_stage' => 'lead']);

    SeoAudit::create(['url' => 'https://msp.test/', 'score' => 62, 'checks' => [], 'issues_count' => 4, 'fetched_status' => 200]);

    $insights = app(StrategyInsights::class);

    $conversion = $insights->conversionAudit();
    expect($conversion['visitors'])->toBe(4)
        ->and($conversion['identified'])->toBe(1)
        ->and($conversion['identification_rate'])->toBe(25.0);

    $hygiene = $insights->crmHygiene();
    expect($hygiene['contacts_without_owner'])->toBe(1)
        ->and($hygiene['contacts_without_email'])->toBe(1);

    expect($insights->funnelAudit()[0]['stages_without_assets'])->toBe(1);

    $gaps = $insights->leadGenGaps();
    expect($gaps['sources'])->toHaveCount(1)
        ->and($gaps['sources'][0]['source'])->toBe('bing-ads');

    $seo = $insights->seoAuditSummary();
    expect($seo['audits'])->toBe(1)->and($seo['total_issues'])->toBe(4)->and($seo['worst_url'])->toBe('https://msp.test/');

    // The composite assessment aggregates the audits.
    $assessment = $insights->assessment();
    expect($assessment['issues']['unconverted_sources'])->toBe(1)
        ->and($assessment['issues']['funnel_stage_gaps'])->toBe(1)
        ->and($assessment['issues']['crm_hygiene'])->toBeGreaterThanOrEqual(2);
});

it('serves the insights on the strategy page, permission-gated', function () {
    app(CurrentOrganization::class)->forget();

    $response = $this->actingAs($this->owner)->get(route('strategy.index'))->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['insights'])->toHaveKeys([
        'keyword_opportunities', 'competitor_landscape', 'geo_markets', 'vertical_performance',
        'conversion_audit', 'crm_hygiene', 'funnel_audit', 'ppc_audit', 'lead_gen_gaps',
        'revenue_model', 'icp_profile', 'seo_audit_summary', 'assessment',
    ])->and($props['insights']['revenue_model']['insufficient_data'])->toBeTrue();
});
