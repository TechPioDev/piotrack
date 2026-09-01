<?php

declare(strict_types=1);

/**
 * Local SEO (Phase 2 of the road to 100 — LSEO-002..008, 014, 017, 020..022).
 *
 * The geo pipeline is one mechanism at every granularity: a keyword carries a
 * location (city, state, service area or neighborhood), every rank check for
 * it — manual and the daily sweep — passes that location to the rank provider
 * and stamps it on the snapshot, so per-market history is real. Location
 * landing pages generate from a branch's NAP data, and geo funnels compose
 * those pages per market. GBP/Maps/reviews stay out: they need the Google
 * Business Profile API (external).
 */

use App\Authorization\Role;
use App\Models\Funnel;
use App\Models\Keyword;
use App\Models\KeywordRanking;
use App\Models\LandingPage;
use App\Models\SeoLocation;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Local SEO Org');
    subscribeOrganization($this->org, 'enterprise');
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('stores geo keywords at every granularity and tracks rank per market', function () {
    $markets = [
        'city' => 'Philadelphia, PA',
        'state' => 'Pennsylvania',
        'service area' => 'Greater Philadelphia Metro',
        'neighborhood' => 'Center City, Philadelphia',
    ];

    foreach ($markets as $kind => $location) {
        $this->actingAs($this->owner)->post(route('seo.keywords.store'), [
            'phrase' => "managed it services {$kind}",
            'intent' => 'commercial',
            'type' => 'local',
            'location' => $location,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    app(CurrentOrganization::class)->set($this->org);
    expect(Keyword::where('type', 'local')->count())->toBe(4)
        ->and(Keyword::where('phrase', 'managed it services city')->value('location'))->toBe('Philadelphia, PA');
    $keyword = Keyword::where('phrase', 'managed it services city')->firstOrFail();
    app(CurrentOrganization::class)->forget();

    // A manual rank check for the city market stamps the market on the snapshot.
    $this->actingAs($this->owner)->post(route('seo.keywords.rank', $keyword->id), [
        'domain' => 'localmsp.test',
        'location' => 'Philadelphia, PA',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $snapshot = KeywordRanking::withoutGlobalScopes()->where('keyword_id', $keyword->id)->firstOrFail();
    expect($snapshot->location)->toBe('Philadelphia, PA')
        ->and($snapshot->provider)->not->toBeNull()
        ->and($snapshot->position)->toBeGreaterThan(0);
});

it('passes the keyword location through the daily ranking sweep', function () {
    app(CurrentOrganization::class)->set($this->org);
    $keyword = Keyword::create([
        'phrase' => 'msp philadelphia',
        'intent' => 'commercial',
        'type' => 'local',
        'location' => 'Philadelphia, PA',
        'mapped_url' => 'https://localmsp.test/philadelphia',
        'is_tracked' => true,
    ]);
    app(CurrentOrganization::class)->forget();

    $this->artisan('seo:track-rankings')->assertSuccessful();

    $snapshot = KeywordRanking::withoutGlobalScopes()
        ->where('keyword_id', $keyword->id)->where('is_competitor', false)->firstOrFail();
    expect($snapshot->location)->toBe('Philadelphia, PA');
});

it('generates a draft landing page from a location, NAP included', function () {
    $this->actingAs($this->owner)->post(route('seo.local.store'), [
        'name' => 'Philadelphia HQ',
        'street' => '1650 Market St',
        'city' => 'Philadelphia',
        'region' => 'PA',
        'postal_code' => '19103',
        'phone' => '+1 215 555 0100',
    ])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    $location = SeoLocation::firstOrFail();
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)
        ->post(route('seo.local.page.create', $location->id), ['service' => 'Managed IT Services'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $page = LandingPage::withoutGlobalScope('tenant')->firstOrFail();
    expect($page->status)->toBe('draft')
        ->and((int) $page->organization_id)->toBe((int) $this->org->id)
        ->and($page->headline)->toBe('Managed IT Services in Philadelphia, PA')
        ->and($page->slug)->toBe('managed-it-services-philadelphia')
        ->and($page->body_html)->toContain('1650 Market St')->toContain('+1 215 555 0100')
        ->and($page->body_html)->toContain('Service area');

    // Same city + service again: the slug stays unique instead of colliding.
    $this->actingAs($this->owner)
        ->post(route('seo.local.page.create', $location->id), ['service' => 'Managed IT Services'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(LandingPage::withoutGlobalScope('tenant')->where('slug', 'managed-it-services-philadelphia-2')->exists())->toBeTrue();

    // The generator is manage-gated.
    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)
        ->post(route('seo.local.page.create', $location->id), ['service' => 'X'])
        ->assertForbidden();
});

it('composes a geo funnel per market from location pages', function () {
    app(CurrentOrganization::class)->set($this->org);
    $funnel = Funnel::create(['name' => 'Philadelphia Market Funnel']);
    $stage = $funnel->stages()->create(['name' => 'City interest', 'position' => 1, 'category' => 'tof', 'lifecycle_stage' => 'lead']);
    $page = LandingPage::create([
        'name' => 'Managed IT — Philadelphia', 'slug' => 'managed-it-philadelphia-funnel',
        'headline' => 'Managed IT Services in Philadelphia, PA', 'status' => 'published',
    ]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)
        ->post(route('marketing.funnels.assets.attach', [$funnel->id, $stage->id]), [
            'asset_type' => 'landing_page',
            'asset_id' => $page->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

    $props = $this->actingAs($this->owner)
        ->get(route('marketing.funnels.show', $funnel->id))->assertOk()
        ->viewData('page')['props'];

    $assets = collect($props['stages'])->firstWhere('id', $stage->id)['assets'];
    expect(collect($assets)->firstWhere('type', 'landing_page')['name'])->toBe('Managed IT — Philadelphia');
});
