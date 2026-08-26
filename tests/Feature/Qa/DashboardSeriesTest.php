<?php

declare(strict_types=1);

/**
 * Design-shell module: the chart series the five module dashboards now send.
 * Each series must be computed from the tenant's real records — seeded rows
 * appear where they should, and an empty tenant gets an empty/zero series,
 * never invented numbers.
 */

use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Keyword;
use App\Models\Pipeline;
use App\Models\SeoAudit;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Charts Org');
    subscribeOrganization($this->org, 'enterprise');
});

function seriesProps($test, string $routeName): array
{
    return $test->actingAs($test->owner)->get(route($routeName))->assertOk()->viewData('page')['props'];
}

it('puts new contacts on the marketing trend, day by day', function () {
    app(CurrentOrganization::class)->set($this->org);
    Contact::create(['first_name' => 'A', 'email' => 'a@x.test']);
    Contact::create(['first_name' => 'B', 'email' => 'b@x.test']);
    app(CurrentOrganization::class)->forget();

    $trend = seriesProps($this, 'marketing.dashboard')['trend'];

    expect($trend)->toHaveCount(30)
        ->and(end($trend)['value'])->toBe(2)
        ->and(end($trend)['label'])->toBe(now()->format('M j'))
        ->and(collect($trend)->sum('value'))->toBe(2);
});

it('buckets tracked keywords into the seo ranking distribution', function () {
    app(CurrentOrganization::class)->set($this->org);
    foreach ([2 => 'alpha', 7 => 'beta', 15 => 'gamma', 30 => 'delta', null => 'epsilon'] as $position => $phrase) {
        Keyword::create(['phrase' => $phrase.' msp', 'is_tracked' => true, 'current_position' => $position ?: null]);
    }
    SeoAudit::create(['url' => 'https://a.test', 'score' => 60, 'issues_count' => 4]);
    SeoAudit::create(['url' => 'https://a.test', 'score' => 80, 'issues_count' => 2]);
    app(CurrentOrganization::class)->forget();

    $props = seriesProps($this, 'seo.dashboard');

    expect(collect($props['distribution'])->pluck('value', 'label')->all())
        ->toBe(['Top 3' => 1, 'Page 1' => 1, '11–20' => 1, '21+' => 1, 'Unranked' => 1]);
    // Oldest first, so the line reads left to right in time.
    expect(collect($props['auditTrend'])->pluck('value')->all())->toBe([60, 80]);
});

it('sums daily ad metrics across campaigns for the spend trend', function () {
    app(CurrentOrganization::class)->set($this->org);
    $a = AdCampaign::create(['platform' => 'google', 'name' => 'A', 'status' => 'active']);
    $b = AdCampaign::create(['platform' => 'linkedin', 'name' => 'B', 'status' => 'active']);
    $day = now()->subDays(2)->startOfDay();
    AdMetric::create(['ad_campaign_id' => $a->id, 'date' => $day, 'impressions' => 100, 'clicks' => 10, 'spend' => 5000, 'conversions' => 1, 'revenue' => 0]);
    AdMetric::create(['ad_campaign_id' => $b->id, 'date' => $day, 'impressions' => 200, 'clicks' => 5, 'spend' => 2500, 'conversions' => 0, 'revenue' => 0]);
    app(CurrentOrganization::class)->forget();

    $trend = seriesProps($this, 'ads.dashboard')['trend'];

    expect($trend)->toHaveCount(1)
        ->and($trend[0]['label'])->toBe($day->format('M j'))
        ->and($trend[0]['spend'])->toBe(7500)
        ->and($trend[0]['clicks'])->toBe(15);
});

it('reports open pipeline value by stage and leaves won deals out', function () {
    app(CurrentOrganization::class)->set($this->org);
    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $stages = $pipeline->stages;
    $first = $stages->first();
    $won = $stages->firstWhere('is_won', true);
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $first->id, 'name' => 'Open A', 'value' => 100000, 'status' => 'open']);
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $first->id, 'name' => 'Open B', 'value' => 50000, 'status' => 'open']);
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $won->id, 'name' => 'Won', 'value' => 999999, 'status' => 'won']);
    app(CurrentOrganization::class)->forget();

    $rows = collect(seriesProps($this, 'sales.dashboard')['pipeline']);

    expect($rows->firstWhere('label', $first->name)['value'])->toBe(150000)
        ->and($rows->firstWhere('label', $first->name)['hint'])->toBe('2 deals')
        ->and($rows->pluck('label'))->not->toContain($won->name)
        ->and($rows->sum('value'))->toBe(150000);
});

it('sends empty or zero series for a tenant with no data, never invented ones', function () {
    $marketing = seriesProps($this, 'marketing.dashboard');
    expect(collect($marketing['trend'])->sum('value'))->toBe(0);

    $seo = seriesProps($this, 'seo.dashboard');
    expect(collect($seo['distribution'])->sum('value'))->toBe(0)
        ->and($seo['auditTrend'])->toBeEmpty();

    $ads = seriesProps($this, 'ads.dashboard');
    expect($ads['trend'])->toBeEmpty();

    $sales = seriesProps($this, 'sales.dashboard');
    expect(collect($sales['pipeline'])->sum('value'))->toBe(0);
});
