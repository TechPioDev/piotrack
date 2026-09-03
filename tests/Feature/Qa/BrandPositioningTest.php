<?php

declare(strict_types=1);

/**
 * MSP Branding close-out (Phase 22 — BRAND-001/002/004/008..012, 020/021/022, 025).
 *
 * Positioning evidence computed from real records: discovery from the brand
 * profile, competitor messaging from captured snapshots, differentiators
 * checked against our pages AND theirs, ICP alignment against actual wins
 * (guarded), per-axis coverage. Plus the identity fields rendered as a real
 * style-guide deliverable, and the public site wearing the brand.
 */

use App\Models\BrandProfile;
use App\Models\Company;
use App\Models\Competitor;
use App\Models\CompetitorSnapshot;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\ScoringRule;
use App\Models\ServiceLine;
use App\Models\SitePage;
use App\Services\Strategy\BrandPositioningService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Brand Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('reports discovery honestly in both directions', function () {
    $empty = app(BrandPositioningService::class)->discovery();
    expect($empty['answered'])->toBe(0);

    BrandProfile::create([
        'positioning_statement' => 'CMMC-first managed IT for manufacturers.',
        'usp' => 'The only MSP with a 100% first-audit pass rate.',
        'differentiators' => ['first-audit pass rate', '15-minute response'],
        'tone_of_voice' => 'Plain, confident.',
        'palette' => ['primary' => '#0d7a6f'],
    ]);
    ScoringRule::create(['name' => 'ICP: industry', 'category' => 'firmographic', 'attribute' => 'company_industry', 'operator' => 'contains', 'value' => 'Manufacturing', 'points' => 5, 'is_active' => true]);

    $filled = app(BrandPositioningService::class)->discovery();
    expect($filled['answered'])->toBe(6) // narrative still missing
        ->and(collect($filled['items'])->firstWhere('key', 'narrative')['ok'])->toBeFalse();
});

it('lists competitor messaging from captured snapshots and flags copied differentiators', function () {
    BrandProfile::create(['differentiators' => ['15-minute response', 'co-managed IT']]);
    SitePage::create(['type' => 'service', 'slug' => 'response', 'title' => 'Our 15-minute response guarantee', 'status' => SitePage::STATUS_PUBLISHED]);

    $rival = Competitor::create(['name' => 'Rival MSP', 'domain' => 'rival.example', 'is_tracked' => true]);
    CompetitorSnapshot::create([
        'competitor_id' => $rival->id, 'pages_count' => 2,
        'pages' => [
            ['url' => 'https://rival.example/', 'title' => 'Rival MSP — 15-minute response for Philadelphia', 'hash' => 'a'],
            ['url' => 'https://rival.example/services', 'title' => 'Managed IT Services', 'hash' => 'b'],
        ],
    ]);

    $messaging = app(BrandPositioningService::class)->competitorMessaging();
    expect($messaging[0]['competitor'])->toBe('Rival MSP')
        ->and($messaging[0]['titles'])->toContain('Rival MSP — 15-minute response for Philadelphia');

    $checks = collect(app(BrandPositioningService::class)->differentiators())->keyBy('differentiator');
    // Ours, on our site, but ALSO claimed by the rival: table stake.
    expect($checks['15-minute response']['on_our_site'])->toBeTrue()
        ->and($checks['15-minute response']['claimed_by'])->toBe(['Rival MSP']);
    // Genuinely ours — nobody else claims it, but we never published it either.
    expect($checks['co-managed IT']['on_our_site'])->toBeFalse()
        ->and($checks['co-managed IT']['claimed_by'])->toBe([]);
});

it('aligns the stated ICP against actual wins, guarded when there are none', function () {
    ScoringRule::create(['name' => 'ICP: industry', 'category' => 'firmographic', 'attribute' => 'company_industry', 'operator' => 'contains', 'value' => 'Manufacturing', 'points' => 5, 'is_active' => true]);
    ScoringRule::create(['name' => 'ICP: region', 'category' => 'firmographic', 'attribute' => 'company_region', 'operator' => 'contains', 'value' => 'PA', 'points' => 10, 'is_active' => true]);

    expect(app(BrandPositioningService::class)->icpAlignment()['insufficient_data'])->toBeTrue();

    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $won = $pipeline->stages()->where('is_won', true)->firstOrFail();
    $mfg = Company::create(['name' => 'Mfg Co', 'industry' => 'Manufacturing', 'region' => 'NJ']);
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $won->id, 'name' => 'Won A', 'value' => 100, 'status' => 'won', 'company_id' => $mfg->id, 'closed_at' => now()]);

    $alignment = app(BrandPositioningService::class)->icpAlignment();
    $byAttr = collect($alignment['checks'])->keyBy('attribute');

    expect($alignment['insufficient_data'])->toBeFalse()
        ->and($byAttr['industry']['aligned'])->toBeTrue()   // stated Manufacturing, wins Manufacturing
        ->and($byAttr['region']['aligned'])->toBeFalse()    // stated PA, wins NJ
        ->and($byAttr['region']['observed'])->toBe('NJ');
});

it('computes per-axis positioning evidence from coverage and wins', function () {
    $evidence = app(BrandPositioningService::class)->positioningEvidence();

    // Fresh org: default taxonomy active, nothing published, no wins.
    expect($evidence['premium']['won_deals'])->toBe(0)
        ->and($evidence['premium']['avg_deal_value'])->toBeNull()
        ->and($evidence['service']['with_published_page'])->toBe(0)
        ->and($evidence['service']['active'])->toBeGreaterThan(0);

    $service = ServiceLine::where('is_active', true)->firstOrFail();
    SitePage::create(['type' => 'service', 'slug' => 'svc', 'title' => 'Service', 'status' => SitePage::STATUS_PUBLISHED, 'service_line_id' => $service->id]);

    expect(app(BrandPositioningService::class)->positioningEvidence()['service']['with_published_page'])->toBe(1);
});

it('renders the style guide PDF and dresses the public site in the brand', function () {
    BrandProfile::create([
        'tagline' => 'IT that passes the audit.',
        'palette' => ['primary' => '#0d7a6f', 'accent' => '#b45309'],
        'typography' => ['heading' => 'Fraunces', 'body' => 'Inter'],
        'imagery_direction' => 'Real plant floors, no stock handshakes.',
        'logo_url' => 'https://brand-org.test/logo.png',
    ]);

    $pdf = $this->actingAs($this->owner)->get(route('strategy.brand.style-guide'))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')->streamedContent();

    expect(substr($pdf, 0, 8))->toBe('%PDF-1.4')
        ->and($pdf)->toContain('#0d7a6f')
        ->and($pdf)->toContain('Fraunces')
        ->and($pdf)->toContain('Real plant floors');

    // BRAND-025: the public site wears the palette and the logo.
    SitePage::create(['type' => 'home', 'slug' => 'brand-home', 'title' => 'Home', 'status' => SitePage::STATUS_PUBLISHED]);
    app(CurrentOrganization::class)->forget();

    $html = $this->get('/s/brand-home')->assertOk()->getContent();
    expect($html)->toContain('#0d7a6f')
        ->and($html)->toContain('https://brand-org.test/logo.png');
});
