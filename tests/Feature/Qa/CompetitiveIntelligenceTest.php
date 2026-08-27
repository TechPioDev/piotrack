<?php

declare(strict_types=1);

/**
 * Competitive Intelligence (Module 08). Head-to-head must never invent a
 * position, the daily tracker must record both sides idempotently, the AI
 * recommendation share must come from recorded checks, and the outrank alert
 * must fire only when a competitor genuinely ranks ahead.
 */

use App\Models\AiVisibilityCheck;
use App\Models\Competitor;
use App\Models\Keyword;
use App\Services\AlertSweep;
use App\Services\Analytics\CompetitiveService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Compete Org');
    subscribeOrganization($this->org, 'enterprise');
});

it('tracks our and competitor rankings daily, idempotently', function () {
    app(CurrentOrganization::class)->set($this->org);
    Keyword::create(['phrase' => 'managed it philadelphia', 'is_tracked' => true, 'mapped_url' => 'https://northwind-it.test/services/managed-it']);
    Keyword::create(['phrase' => 'unmapped keyword', 'is_tracked' => true]); // no mapped_url -> our side skipped
    Competitor::create(['name' => 'Rival IT', 'domain' => 'rival-it.test', 'is_tracked' => true]);
    Competitor::create(['name' => 'Untracked Co', 'domain' => 'untracked.test', 'is_tracked' => false]);
    app(CurrentOrganization::class)->forget();

    Artisan::call('seo:track-rankings');
    Artisan::call('seo:track-rankings'); // same day -> no duplicates

    app(CurrentOrganization::class)->set($this->org);
    $mapped = Keyword::where('phrase', 'managed it philadelphia')->firstOrFail();
    $unmapped = Keyword::where('phrase', 'unmapped keyword')->firstOrFail();

    // Mapped keyword: one own check + one tracked-competitor check.
    expect($mapped->rankings()->where('is_competitor', false)->count())->toBe(1)
        ->and($mapped->rankings()->where('is_competitor', true)->where('competitor_domain', 'rival-it.test')->count())->toBe(1)
        ->and($mapped->rankings()->where('competitor_domain', 'untracked.test')->count())->toBe(0)
        ->and($mapped->refresh()->current_position)->not->toBeNull();

    // Unmapped keyword: competitor side still tracked, our side honestly absent.
    expect($unmapped->rankings()->where('is_competitor', false)->count())->toBe(0)
        ->and($unmapped->rankings()->where('is_competitor', true)->count())->toBe(1);
    app(CurrentOrganization::class)->forget();
});

it('reports keyword head-to-head without inventing positions', function () {
    app(CurrentOrganization::class)->set($this->org);
    $keyword = Keyword::create(['phrase' => 'msp support', 'is_tracked' => true, 'current_position' => 4]);
    Competitor::create(['name' => 'Rival IT', 'domain' => 'rival-it.test', 'is_tracked' => true]);
    Competitor::create(['name' => 'Ghost MSP', 'domain' => 'ghost-msp.test', 'is_tracked' => true]);
    $keyword->rankings()->create(['engine' => 'google', 'is_competitor' => true, 'competitor_domain' => 'rival-it.test', 'position' => 2, 'checked_at' => now()]);
    // ghost-msp.test never checked -> null, not a number.

    $rows = app(CompetitiveService::class)->keywordHeadToHead();
    app(CurrentOrganization::class)->forget();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['our_position'])->toBe(4)
        ->and($rows[0]['competitors']['rival-it.test'])->toBe(2)
        ->and($rows[0]['competitors']['ghost-msp.test'])->toBeNull()
        ->and($rows[0]['leading'])->toBeFalse();
});

it('computes the share of AI recommendations from recorded checks', function () {
    app(CurrentOrganization::class)->set($this->org);
    $mk = fn (bool $mentioned, bool $recommended, array $competitors) => AiVisibilityCheck::create([
        'prompt' => 'best msp?', 'engine' => 'chatgpt', 'brand' => 'Us', 'provider' => 'fixture',
        'mentioned' => $mentioned, 'recommended' => $recommended, 'position' => $mentioned ? 1 : null,
        'cited_sources' => [], 'competitors' => $competitors, 'share_of_answer' => 0, 'checked_at' => now(),
    ]);
    $mk(true, true, ['Rival IT']);    // contested, ours
    $mk(true, false, []);             // contested, not recommended
    $mk(false, false, ['Rival IT']);  // contested, competitor only
    $mk(false, false, []);            // uncontested - excluded

    $share = app(CompetitiveService::class)->aiRecommendationShare();
    app(CurrentOrganization::class)->forget();

    expect($share['checks'])->toBe(4)
        ->and($share['contested'])->toBe(3)
        ->and($share['our_recommendations'])->toBe(1)
        ->and($share['share'])->toBe(33.3)
        ->and($share['competitor_appearances']['Rival IT'])->toBe(2);
});

it('alerts when a competitor ranks ahead, deduped, and stays quiet when we lead', function () {
    app(CurrentOrganization::class)->set($this->org);
    $behind = Keyword::create(['phrase' => 'we trail here', 'is_tracked' => true, 'current_position' => 9]);
    $behind->rankings()->create(['engine' => 'google', 'is_competitor' => true, 'competitor_domain' => 'rival-it.test', 'position' => 3, 'checked_at' => now()]);

    $leading = Keyword::create(['phrase' => 'we lead here', 'is_tracked' => true, 'current_position' => 1]);
    $leading->rankings()->create(['engine' => 'google', 'is_competitor' => true, 'competitor_domain' => 'rival-it.test', 'position' => 5, 'checked_at' => now()]);

    $sweep = app(AlertSweep::class);
    $first = $sweep->run($this->org);
    $second = $sweep->run($this->org);
    app(CurrentOrganization::class)->forget();

    expect($first['competitors'])->toBe(1)
        ->and($second['competitors'])->toBe(0);

    $rows = $this->owner->notifications()->where('data->title', 'like', '%ranks ahead%')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->data['body'])->toContain('rival-it.test is #3')
        ->and($rows[0]->data['body'])->toContain('we trail here');
});

it('serves head-to-head and AI share to the competitors page', function () {
    app(CurrentOrganization::class)->set($this->org);
    Keyword::create(['phrase' => 'msp', 'is_tracked' => true, 'current_position' => 2]);
    Competitor::create(['name' => 'Rival IT', 'domain' => 'rival-it.test', 'is_tracked' => true]);
    app(CurrentOrganization::class)->forget();

    $props = $this->actingAs($this->owner)->get(route('analytics.competitors.index'))->assertOk()->viewData('page')['props'];

    expect($props['headToHead'][0]['keyword'])->toBe('msp')
        ->and($props['headToHead'][0]['leading'])->toBeTrue() // nobody recorded ranks better
        ->and($props['aiShare'])->toHaveKeys(['checks', 'contested', 'share']);
});

it('keeps head-to-head inside the tenant', function () {
    [$orgB] = makeOrganization('Other Compete Org');
    app(CurrentOrganization::class)->set($orgB);
    Keyword::create(['phrase' => 'foreign keyword', 'is_tracked' => true, 'current_position' => 1]);
    app(CurrentOrganization::class)->forget();

    $props = $this->actingAs($this->owner)->get(route('analytics.competitors.index'))->assertOk()->viewData('page')['props'];
    expect(collect($props['headToHead'])->pluck('keyword'))->not->toContain('foreign keyword');
});
