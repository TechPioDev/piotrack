<?php

declare(strict_types=1);

/**
 * MSP Keyword SEO close-out (Phase 6 — KSEO-001..012).
 *
 * The curated research library IS the product's keyword research for the MSP
 * niche: typed (service/industry/vertical/problem/solution/long-tail/
 * bottom-funnel) and intent-classified (commercial/transactional buyer
 * targeting), geo-multiplied across the tenant's own markets. Honesty
 * contract: volume and difficulty are never invented (null until a data
 * provider), seeds arrive untracked for review, and the competitor steal list
 * is computed only from recorded rankings.
 */

use App\Models\Competitor;
use App\Models\Keyword;
use App\Models\KeywordRanking;
use App\Models\SeoLocation;
use App\Seo\MspKeywordLibrary;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('KSEO Org');
    subscribeOrganization($this->org, 'enterprise');
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('seeds the typed, intent-classified MSP research library without inventing data', function () {
    $this->actingAs($this->owner)
        ->post(route('seo.keywords.seed'), [])
        ->assertRedirect()->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($this->org);
    $keywords = Keyword::get();

    expect($keywords->count())->toBe(count(MspKeywordLibrary::entries()));

    // Every register category is represented (KSEO-005..007, 009..012).
    foreach (['service', 'industry', 'vertical', 'problem', 'solution', 'long_tail', 'bottom_funnel'] as $type) {
        expect($keywords->where('type', $type)->count())->toBeGreaterThan(0);
    }

    // Buyer-intent targeting (KSEO-002/003/004): commercial and transactional sets exist.
    expect($keywords->where('intent', 'commercial')->count())->toBeGreaterThan(0)
        ->and($keywords->where('intent', 'transactional')->count())->toBeGreaterThan(0)
        // Bottom-funnel phrases are transactional by classification.
        ->and($keywords->where('type', 'bottom_funnel')->pluck('intent')->unique()->all())->toBe(['transactional']);

    // Nothing invented, nothing auto-tracked.
    expect($keywords->whereNotNull('search_volume')->count())->toBe(0)
        ->and($keywords->whereNotNull('difficulty')->count())->toBe(0)
        ->and($keywords->where('is_tracked', true)->count())->toBe(0);
});

it('is idempotent and multiplies buying-stage phrases across the tenant markets', function () {
    app(CurrentOrganization::class)->set($this->org);
    SeoLocation::create(['name' => 'Philly HQ', 'city' => 'Philadelphia', 'region' => 'PA', 'is_active' => true]);
    SeoLocation::create(['name' => 'Inactive', 'city' => 'Ghosttown', 'is_active' => false]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->post(route('seo.keywords.seed'), ['with_geo' => true]);

    app(CurrentOrganization::class)->set($this->org);
    $geoCount = count(MspKeywordLibrary::entries());
    $geoTypes = collect(MspKeywordLibrary::entries())->whereIn('type', MspKeywordLibrary::GEO_TYPES)->count();
    expect(Keyword::count())->toBe($geoCount + $geoTypes); // one variant per geo-type entry, active city only

    $geo = Keyword::where('phrase', 'managed it services philadelphia')->firstOrFail();
    expect($geo->location)->toBe('Philadelphia, PA')
        ->and(Keyword::where('phrase', 'like', '%ghosttown%')->count())->toBe(0);
    app(CurrentOrganization::class)->forget();

    // Re-seeding creates nothing new.
    $this->actingAs($this->owner)->post(route('seo.keywords.seed'), ['with_geo' => true]);
    app(CurrentOrganization::class)->set($this->org);
    expect(Keyword::count())->toBe($geoCount + $geoTypes);
});

it('computes the competitor steal list from recorded rankings only', function () {
    app(CurrentOrganization::class)->set($this->org);
    Competitor::create(['name' => 'Rival', 'domain' => 'rival.test', 'is_tracked' => true]);

    $losing = Keyword::create(['phrase' => 'managed it services', 'intent' => 'commercial', 'is_tracked' => true, 'current_position' => 8]);
    KeywordRanking::create(['keyword_id' => $losing->id, 'engine' => 'google', 'position' => 3, 'is_competitor' => true, 'competitor_domain' => 'rival.test', 'provider' => 'fixture', 'checked_at' => now()]);

    $winning = Keyword::create(['phrase' => 'co-managed it', 'intent' => 'commercial', 'is_tracked' => true, 'current_position' => 2]);
    KeywordRanking::create(['keyword_id' => $winning->id, 'engine' => 'google', 'position' => 6, 'is_competitor' => true, 'competitor_domain' => 'rival.test', 'provider' => 'fixture', 'checked_at' => now()]);
    app(CurrentOrganization::class)->forget();

    $props = $this->actingAs($this->owner)->get(route('seo.keywords.index'))->assertOk()
        ->viewData('page')['props'];

    $steal = collect($props['steal']);
    expect($steal)->toHaveCount(1)
        ->and($steal[0]['keyword'])->toBe('managed it services')
        ->and($steal[0]['best_competitor'])->toBe('rival.test')
        ->and($steal[0]['best_position'])->toBe(3);
});

it('lets a reviewer enable tracking on a research keyword', function () {
    $this->actingAs($this->owner)->post(route('seo.keywords.seed'), []);

    app(CurrentOrganization::class)->set($this->org);
    $keyword = Keyword::where('phrase', 'managed it services pricing')->firstOrFail();
    expect($keyword->is_tracked)->toBeFalse();
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)
        ->patch(route('seo.keywords.update', $keyword->id), [
            'phrase' => $keyword->phrase, 'intent' => $keyword->intent, 'is_tracked' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

    expect($keyword->fresh()->is_tracked)->toBeTrue();
});
