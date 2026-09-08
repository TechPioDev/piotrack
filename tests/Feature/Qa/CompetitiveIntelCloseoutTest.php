<?php

declare(strict_types=1);

/**
 * Competitive Intelligence close-out (Phase 54 — CINT-002/003/004/006/007/009).
 *
 * Every provider-gated panel now rides a tested seam: ad-transparency data
 * through the new AdLibraryProvider, backlinks through the P45 link seam,
 * local-pack positions through the P52 rank seam, reviews through the
 * ADR-0007 seam, and social mention volume through the P46 listening seam —
 * each labeled with its driver, fixtures marked simulated.
 */

use App\Analytics\Contracts\AdLibraryProvider;
use App\Models\Competitor;
use App\Models\Keyword;
use App\Services\Analytics\CompetitorIntelService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('CintClose Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('monitors competitor ads through the transparency seam, deterministically (CINT-002/003)', function () {
    $provider = app(AdLibraryProvider::class);
    $ads = $provider->ads('TechRival MSP');

    expect($provider->name())->toBe('fixture')
        ->and($ads)->not->toBeEmpty()
        ->and($ads[0])->toHaveKeys(['platform', 'headline', 'body', 'status', 'first_seen'])
        // Deterministic: the same advertiser always yields the same set.
        ->and($provider->ads('TechRival MSP'))->toBe($ads)
        ->and(collect($ads)->pluck('platform')->unique()->diff(['google', 'meta']))->toBeEmpty();
});

it('summarizes competitor backlinks, map positions, reviews and social through existing seams (CINT-004/006/007/009)', function () {
    $competitor = Competitor::create(['name' => 'TechRival MSP', 'domain' => 'techrival.example', 'is_tracked' => true]);
    Keyword::create(['phrase' => 'msp philadelphia', 'intent' => 'commercial', 'is_tracked' => true, 'location' => 'Philadelphia, PA']);

    $panels = app(CompetitorIntelService::class)->panels($competitor);

    // CINT-004: the P45 link seam serves competitor domains.
    expect($panels['backlinks']['links'])->toBeGreaterThan(0)
        ->and($panels['backlinks']['referring_domains'])->toBeGreaterThan(0)
        ->and($panels['backlinks']['avg_da'])->not->toBeNull();

    // CINT-006: local-pack position (or an honest null) per located keyword.
    expect($panels['map'])->toHaveCount(1)
        ->and($panels['map'][0]['keyword'])->toBe('msp philadelphia');
    if ($panels['map'][0]['position'] !== null) {
        expect($panels['map'][0]['position'])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3);
    }

    // CINT-007: the ADR-0007 review seam by competitor identifier.
    expect($panels['reviews']['count'])->toBe(3)
        ->and($panels['reviews']['avg_rating'])->toBeGreaterThanOrEqual(3.0);

    // CINT-009: listening-seam mention volume with transparent sentiment.
    expect($panels['social']['mentions'])->toBeGreaterThan(0)
        ->and($panels['social']['by_network'])->not->toBeEmpty()
        ->and($panels['social']['negative'] + $panels['social']['positive'])->toBeGreaterThanOrEqual(0);

    // No domain: the backlink panel says so instead of guessing.
    $bare = Competitor::create(['name' => 'No Domain Rival', 'is_tracked' => true]);
    expect(app(CompetitorIntelService::class)->panels($bare)['backlinks'])->toBeNull();
});

it('serves the intel on the competitors page, every panel labeled with its driver', function () {
    Competitor::create(['name' => 'TechRival MSP', 'domain' => 'techrival.example', 'is_tracked' => true]);
    Competitor::create(['name' => 'Paused Rival', 'domain' => 'paused.example', 'is_tracked' => false]);

    $props = $this->actingAs($this->owner)->get(route('analytics.competitors.index'))->assertOk()->viewData('page')['props'];

    $tracked = collect($props['competitors'])->firstWhere('name', 'TechRival MSP');
    $paused = collect($props['competitors'])->firstWhere('name', 'Paused Rival');

    expect($tracked['intel']['ads']['total'])->toBeGreaterThan(0)
        ->and($paused['intel'])->toBeNull()          // paused competitors are not queried
        ->and($props['intel_providers'])->toBe([
            'ads' => 'fixture', 'backlinks' => 'fixture', 'map' => 'fixture',
            'reviews' => 'fixture', 'social' => 'fixture',
        ]);
});
