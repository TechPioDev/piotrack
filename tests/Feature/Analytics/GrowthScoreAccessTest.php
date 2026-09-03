<?php

use App\Authorization\Role;
use App\Models\Competitor;
use App\Models\Contact;
use App\Models\GrowthScore;
use App\Models\Keyword;
use App\Models\KeywordRanking;
use App\Services\Analytics\CompetitiveService;
use App\Services\Analytics\GrowthScoreService;
use App\Services\Analytics\OmnichannelService;
use App\Support\CurrentOrganization;

it('scores only the modules that have data and leaves the rest null', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);

    Keyword::create(['phrase' => 'msp', 'is_tracked' => true, 'current_position' => 5]);
    Keyword::create(['phrase' => 'backup', 'is_tracked' => true, 'current_position' => 50]);

    $computed = app(GrowthScoreService::class)->compute();
    app(CurrentOrganization::class)->forget();

    expect($computed['breakdown']['seo'])->toBe(50)     // 1 of 2 keywords on page one
        ->and($computed['breakdown']['paid'])->toBeNull()   // no ad spend: not invented
        ->and($computed['breakdown']['content'])->toBeNull()
        ->and($computed['overall'])->toBeGreaterThan(0);
});

it('returns a zero overall when no module has data', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);
    $computed = app(GrowthScoreService::class)->compute();
    app(CurrentOrganization::class)->forget();

    expect($computed['overall'])->toBe(0)
        ->and(collect($computed['breakdown'])->filter()->all())->toBe([]);
});

it('weights the overall only across sub-scores that have data', function () {
    $service = app(GrowthScoreService::class);

    // Two present sub-scores of 80 and 40 -> renormalized weighted average.
    $overall = $service->overall(['seo' => 80, 'conversion' => 40, 'paid' => null, 'content' => null]);

    expect($overall)->toBeGreaterThan(40)->and($overall)->toBeLessThan(80);
});

it('recommends the weakest areas and flags unmeasured modules', function () {
    $service = app(GrowthScoreService::class);
    $recs = $service->recommendations(['seo' => 20, 'conversion' => 95, 'paid' => null]);

    expect($recs[0]['area'])->toBe('seo')          // lowest first
        ->and(collect($recs)->pluck('area'))->not->toContain('conversion') // healthy, no action
        ->and(collect($recs)->firstWhere('area', 'paid')['score'])->toBeNull();
});

it('snapshots one growth score per organization per day', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);
    Keyword::create(['phrase' => 'msp', 'is_tracked' => true, 'current_position' => 3]);

    $service = app(GrowthScoreService::class);
    $service->snapshot();
    $service->snapshot(); // same day -> updates, never duplicates

    $trend = $service->trend();
    app(CurrentOrganization::class)->forget();

    expect(GrowthScore::withoutGlobalScope('tenant')->where('organization_id', $org->id)->count())->toBe(1)
        ->and($trend)->toHaveCount(1);
});

it('stores a snapshot per tenant from the scheduled command', function () {
    [$orgA] = analyticsOrganization('A');
    [$orgB] = analyticsOrganization('B');

    $this->artisan('analytics:snapshot-growth-scores')->assertSuccessful();

    expect(GrowthScore::withoutGlobalScope('tenant')->where('organization_id', $orgA->id)->exists())->toBeTrue()
        ->and(GrowthScore::withoutGlobalScope('tenant')->where('organization_id', $orgB->id)->exists())->toBeTrue();
});

it('computes share of voice against tracked competitors', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);

    $keyword = Keyword::create(['phrase' => 'msp support', 'is_tracked' => true, 'current_position' => 1]); // 100
    KeywordRanking::create(['keyword_id' => $keyword->id, 'position' => 1, 'is_competitor' => true, 'competitor_domain' => 'rival.com']); // 100

    $sov = app(CompetitiveService::class)->shareOfVoice();
    app(CurrentOrganization::class)->forget();

    expect($sov['our_visibility'])->toBe(100)
        ->and($sov['our_share'])->toBe(50.0)
        ->and($sov['competitors'][0]['domain'])->toBe('rival.com')
        ->and($sov['competitors'][0]['share'])->toBe(50.0);
});

it('reports zero share of voice with no ranking data', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);
    $sov = app(CompetitiveService::class)->shareOfVoice();
    app(CurrentOrganization::class)->forget();

    expect($sov['our_share'])->toBe(0.0)->and($sov['competitors'])->toBe([]);
});

it('marks omnichannel channels active only where data exists', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);
    Keyword::create(['phrase' => 'msp', 'is_tracked' => true, 'current_position' => 4]);

    $channels = collect(app(OmnichannelService::class)->channels());
    app(CurrentOrganization::class)->forget();

    expect($channels->firstWhere('channel', 'seo')['active'])->toBeTrue()
        ->and($channels->firstWhere('channel', 'google_ads')['active'])->toBeFalse()
        ->and($channels->firstWhere('channel', 'google_ads')['value'])->toBe(0)
        ->and($channels)->toHaveCount(15);
});

it('builds a unified prospect journey', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'J', 'email' => 'j@x.com', 'lead_source' => 'organic', 'lifecycle_stage' => 'mql']);

    $journey = app(OmnichannelService::class)->journey($contact);
    app(CurrentOrganization::class)->forget();

    expect($journey['lifecycle_stage'])->toBe('mql')
        ->and($journey['first_touch'])->toBe('organic')
        ->and($journey['touchpoints'])->not->toBeEmpty();
});

it('lets a viewer read analytics but not manage competitors', function () {
    [$org] = analyticsOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)->get(route('analytics.competitors.index'))->assertOk();
    $this->actingAs($viewer)->post(route('analytics.competitors.store'), ['name' => 'Rival'])->assertForbidden();
});

it('stops a viewer from writing a growth-score snapshot', function () {
    [$org, $owner] = analyticsOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)->get(route('analytics.growth-score.index'))->assertOk();
    $this->actingAs($viewer)->post(route('analytics.growth-score.snapshot'))->assertForbidden();

    // An owner (manage permission) may store one.
    $this->actingAs($owner)->post(route('analytics.growth-score.snapshot'))->assertRedirect();
    expect(GrowthScore::withoutGlobalScope('tenant')->where('organization_id', $org->id)->exists())->toBeTrue();
});

it('gives the analyst role access to analytics', function () {
    // Regression: the Analyst role — whose whole purpose is analysis — was
    // initially left without analytics.view when Stage 11 mapped permissions.
    [$org] = analyticsOrganization();
    $analyst = addMember($org, Role::Analyst);

    $this->actingAs($analyst)->get(route('analytics.dashboard'))->assertOk();
    $this->actingAs($analyst)->get(route('analytics.benchmarks.index'))->assertOk();
    $this->actingAs($analyst)->post(route('analytics.growth-score.snapshot'))->assertRedirect();
});

it('blocks the analytics module without the plan feature', function () {
    [, $owner] = makeOrganization(); // Growth trial: no `analytics`

    $this->actingAs($owner)->get(route('analytics.dashboard'))->assertForbidden();
});

it('isolates analytics data across tenants', function () {
    [, $ownerA] = analyticsOrganization('Tenant A');
    [$orgB] = analyticsOrganization('Tenant B');

    app(CurrentOrganization::class)->set($orgB);
    $competitorB = Competitor::create(['name' => 'B rival', 'domain' => 'b.com']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($ownerA)->delete(route('analytics.competitors.destroy', $competitorB->id))->assertNotFound();
    expect(Competitor::withoutGlobalScope('tenant')->whereKey($competitorB->id)->exists())->toBeTrue();
});
