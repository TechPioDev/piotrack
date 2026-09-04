<?php

declare(strict_types=1);

/**
 * Proprietary Data Layer close-out (Phase 29 — BENCH-003/004/005/013/014/015/016).
 *
 * Segmented peer benchmarks: CPC by service and by region, best keywords,
 * offers, verticals and ad platforms — the k-anonymity floor applied PER
 * SEGMENT, with emission and suppression both proven on synthetic cohorts.
 * Plus SEO conversion rate joining the flat metric list.
 */

use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Keyword;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\SeoLocation;
use App\Models\ServiceLine;
use App\Services\Analytics\BenchmarkService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    config(['analytics.benchmark_min_cohort' => 2]);
    [$this->orgA, $this->ownerA] = makeOrganization('Bench Org A');
    subscribeOrganization($this->orgA, 'enterprise');
    [$this->orgB] = makeOrganization('Bench Org B');
    app(CurrentOrganization::class)->set($this->orgA);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

/** A campaign with one metrics row, in whatever org is current. */
function benchCampaign(array $campaign, array $metric): AdCampaign
{
    $created = AdCampaign::create($campaign + ['name' => 'C'.uniqid(), 'platform' => 'google_search', 'status' => 'active']);
    AdMetric::create($metric + ['ad_campaign_id' => $created->id, 'date' => now()->toDateString(), 'impressions' => 1000, 'clicks' => 100, 'spend' => 10000, 'conversions' => 5, 'revenue' => 0]);

    return $created;
}

function inOrg(Organization $org, callable $seed): void
{
    app(CurrentOrganization::class)->set($org);
    $seed();
}

it('benchmarks CPC by service on the canonical key, suppressing thin segments', function () {
    $key = ServiceLine::where('is_active', true)->orderBy('id')->firstOrFail()->key;
    $lonelyKey = ServiceLine::where('is_active', true)->where('key', '!=', $key)->orderBy('id')->firstOrFail()->key;

    // Org A: 10000c / 100 clicks = 100c CPC on the shared service; also the
    // only org on a second service (which must therefore be withheld).
    inOrg($this->orgA, function () use ($key, $lonelyKey) {
        benchCampaign(['service_line_id' => ServiceLine::where('key', $key)->firstOrFail()->id], []);
        benchCampaign(['service_line_id' => ServiceLine::where('key', $lonelyKey)->firstOrFail()->id], ['spend' => 5000]);
    });
    // Org B: 20000c / 100 clicks = 200c CPC on the shared service.
    inOrg($this->orgB, function () use ($key) {
        benchCampaign(['service_line_id' => ServiceLine::where('key', $key)->firstOrFail()->id], ['spend' => 20000]);
    });

    app(CurrentOrganization::class)->set($this->orgA);
    $segments = collect(app(BenchmarkService::class)->segmented()['cpc_by_service']['segments']);

    expect($segments)->toHaveCount(1); // the lonely service is withheld entirely
    $shared = $segments->firstWhere('segment', $key);
    expect($shared['cohort'])->toBe(2)
        ->and($shared['peer_median_cpc'])->toBe(150.0)
        ->and($shared['your_cpc'])->toBe(100.0);

    // The campaign endpoint accepts the binding.
    app(CurrentOrganization::class)->forget();
    $service = ServiceLine::withoutGlobalScope('tenant')->where('organization_id', $this->orgA->id)->where('key', $key)->firstOrFail();
    $this->actingAs($this->ownerA)->post(route('ads.campaigns.store'), [
        'name' => 'Bound', 'platform' => 'google_search', 'objective' => 'leads', 'daily_budget' => 1000,
        'service_line_id' => $service->id,
    ])->assertRedirect();
    app(CurrentOrganization::class)->set($this->orgA);
    expect(AdCampaign::where('name', 'Bound')->firstOrFail()->service_line_id)->toBe($service->id);
});

it('benchmarks CPC by region with city fallback', function () {
    inOrg($this->orgA, function () {
        $philly = SeoLocation::create(['name' => 'Philly', 'city' => 'Philadelphia', 'region' => 'PA', 'is_active' => true]);
        benchCampaign(['seo_location_id' => $philly->id], []); // 100c
    });
    inOrg($this->orgB, function () {
        // Region left empty: the segment key falls back to the city — which
        // would make this org segment 'Philadelphia', not 'PA'.
        $pa = SeoLocation::create(['name' => 'Scranton', 'city' => 'Scranton', 'region' => 'PA', 'is_active' => true]);
        benchCampaign(['seo_location_id' => $pa->id], ['spend' => 30000]); // 300c
    });

    app(CurrentOrganization::class)->set($this->orgA);
    $segments = collect(app(BenchmarkService::class)->segmented()['cpc_by_region']['segments']);

    $pa = $segments->firstWhere('segment', 'PA');
    expect($pa['cohort'])->toBe(2)
        ->and($pa['peer_median_cpc'])->toBe(200.0)
        ->and($pa['your_cpc'])->toBe(100.0);
});

it('ranks shared keywords by page-one share and platforms by CTR, withholding unique ones', function () {
    inOrg($this->orgA, function () {
        Keyword::create(['phrase' => 'MSP Services Philadelphia', 'is_tracked' => true, 'current_position' => 3]);
        Keyword::create(['phrase' => 'only-we-track-this', 'is_tracked' => true, 'current_position' => 1]);
        benchCampaign([], []); // google_search: CTR 10%
    });
    inOrg($this->orgB, function () {
        Keyword::create(['phrase' => 'msp services philadelphia ', 'is_tracked' => true, 'current_position' => 15]);
        benchCampaign([], ['clicks' => 300]); // google_search: CTR 30%
    });

    app(CurrentOrganization::class)->set($this->orgA);
    $segmented = app(BenchmarkService::class)->segmented();

    $keywords = collect($segmented['top_keywords']['segments']);
    expect($keywords)->toHaveCount(1); // the unique phrase is withheld
    $shared = $keywords->firstWhere('segment', 'msp services philadelphia');
    expect($shared['cohort'])->toBe(2)
        ->and($shared['page_one_share'])->toBe(50)
        ->and($shared['median_position'])->toBe(9.0)
        ->and($shared['your_best'])->toBe(3);

    $ads = collect($segmented['top_ads']['segments'])->firstWhere('segment', 'google_search');
    expect($ads['cohort'])->toBe(2)
        ->and($ads['peer_median_ctr'])->toBe(20.0)  // 10% and 30%
        ->and($ads['your_ctr'])->toBe(10.0)
        ->and($ads['peer_median_cpc'])->toBe(66.66); // median of 100c and 33.33c
});

it('benchmarks offers by completion rate and verticals by won-deal value', function () {
    $seedOffer = function (int $completed, int $total) {
        $page = BookingPage::create(['name' => 'Consult', 'slug' => 'bench-'.uniqid(), 'meeting_type' => 'consultation', 'duration_minutes' => 30, 'assignment' => 'fixed', 'is_active' => true]);
        foreach (range(1, $total) as $i) {
            Booking::create(['booking_page_id' => $page->id, 'name' => "B{$i}", 'email' => "b{$i}@x.com", 'status' => $i <= $completed ? 'completed' : 'no_show', 'scheduled_at' => now()->subDay()]);
        }
    };
    $seedVertical = function (int $value) {
        $company = Company::create(['name' => 'Mfg '.uniqid(), 'industry' => ' Manufacturing ']);
        $pipeline = Pipeline::where('is_default', true)->firstOrFail();
        $won = $pipeline->stages()->where('is_won', true)->firstOrFail();
        Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $won->id, 'name' => 'W', 'value' => $value, 'status' => 'won', 'company_id' => $company->id]);
    };

    inOrg($this->orgA, function () use ($seedOffer, $seedVertical) {
        $seedOffer(completed: 3, total: 4);   // 75%
        $seedVertical(100000);
    });
    inOrg($this->orgB, function () use ($seedOffer, $seedVertical) {
        $seedOffer(completed: 1, total: 4);   // 25%
        $seedVertical(300000);
    });

    app(CurrentOrganization::class)->set($this->orgA);
    $segmented = app(BenchmarkService::class)->segmented();

    $offer = collect($segmented['top_offers']['segments'])->firstWhere('segment', 'consultation');
    expect($offer['cohort'])->toBe(2)
        ->and($offer['peer_median_completion'])->toBe(50.0)
        ->and($offer['your_completion'])->toBe(75.0);

    // Industry strings normalize (' Manufacturing ' -> 'manufacturing').
    $vertical = collect($segmented['top_verticals']['segments'])->firstWhere('segment', 'manufacturing');
    expect($vertical['cohort'])->toBe(2)
        ->and($vertical['peer_median_deal_value'])->toBe(200000.0)
        ->and($vertical['your_deal_value'])->toBe(100000.0);
});

it('adds SEO conversion rate to the flat benchmarks and respects the floor everywhere', function () {
    inOrg($this->orgA, function () {
        foreach (range(1, 4) as $i) {
            Contact::create(['first_name' => "O{$i}", 'email' => "oa{$i}@x.com", 'lead_source' => 'organic', 'lifecycle_stage' => $i === 1 ? 'customer' : 'lead']);
        }
    });
    inOrg($this->orgB, function () {
        foreach (range(1, 2) as $i) {
            Contact::create(['first_name' => "P{$i}", 'email' => "ob{$i}@x.com", 'lead_source' => 'organic', 'lifecycle_stage' => 'customer']);
        }
    });

    app(CurrentOrganization::class)->set($this->orgA);
    $benchmark = app(BenchmarkService::class)->benchmark('seo_conversion_rate');

    expect($benchmark['cohort'])->toBe(2)
        ->and($benchmark['your_value'])->toBe(25.0)      // 1 of 4 organic leads became a customer
        ->and($benchmark['peer_median'])->toBe(62.5);    // 25% and 100%

    // Raise the floor above the cohort: the same metric is suppressed.
    config(['analytics.benchmark_min_cohort' => 3]);
    expect(app(BenchmarkService::class)->benchmark('seo_conversion_rate'))->toBeNull();
    config(['analytics.benchmark_min_cohort' => 2]);

    // The page carries the segmented prop for the analyst.
    app(CurrentOrganization::class)->forget();
    $props = $this->actingAs($this->ownerA)->get(route('analytics.benchmarks.index'))->assertOk()->viewData('page')['props'];
    expect($props['segmented'])->toHaveKeys(['cpc_by_service', 'cpc_by_region', 'top_keywords', 'top_offers', 'top_verticals', 'top_ads'])
        ->and($props['benchmarks'])->toHaveKey('seo_conversion_rate');
    app(CurrentOrganization::class)->set($this->orgA);
});
