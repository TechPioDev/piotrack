<?php

declare(strict_types=1);

/**
 * The menu marks what a plan does not include (ENTL-012) - and can only do so
 * honestly if the map it reads agrees with what the server actually refuses.
 *
 * `entitlement:` middleware on the routes is the authority. PlanAreas is the
 * same knowledge keyed by where pages live, and the first test here walks the
 * real route table so the two cannot drift: gate a new page behind a feature,
 * or add a page to a gated area without the gate, and this fails.
 */

use App\Billing\Entitlements;
use App\Billing\PlanAreas;
use App\Billing\PlanCatalog;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
});

it('agrees with the feature gate on every page in the app', function () {
    $disagreements = [];

    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $gated = [];
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'entitlement:')) {
                $gated[] = substr($middleware, strlen('entitlement:'));
            }
        }
        $mapped = PlanAreas::featuresFor($route->uri());

        sort($gated);
        sort($mapped);

        if ($gated !== $mapped) {
            $disagreements[] = sprintf('/%s: routes gate it behind [%s], PlanAreas says [%s]', $route->uri(), implode(', ', $gated), implode(', ', $mapped));
        }
    }

    expect($disagreements)->toBe([]);
});

it('lets the most specific area decide', function () {
    expect(PlanAreas::featuresFor('/seo/keywords'))->toBe(['seo'])
        ->and(PlanAreas::featuresFor('/seo/llmo'))->toBe(['seo', 'ai_visibility'])
        // Under /ai, but its own feature alone.
        ->and(PlanAreas::featuresFor('/ai/visibility'))->toBe(['ai_visibility'])
        ->and(PlanAreas::featuresFor('/ai/agent'))->toBe(['ai'])
        // A prefix is a path, not a string: /aid is not under /ai.
        ->and(PlanAreas::featuresFor('/aid'))->toBe([])
        ->and(PlanAreas::featuresFor('/dashboard'))->toBe([])
        ->and(PlanAreas::featuresFor('/billing/plans'))->toBe([]);
});

it('tells the menu which areas the trial plan includes and which it leaves out', function () {
    // The Growth trial: chat and SEO are in, advertising and AI visibility are not.
    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('entitlements.areas./chat', true)
            ->where('entitlements.areas./seo', true)
            ->where('entitlements.areas./settings/teams', true)
            ->where('entitlements.areas./ads', false)
            ->where('entitlements.areas./seo/llmo', false)
            ->where('entitlements.areas./ai/visibility', false));
});

it('marks everything but the free tier once the plan has stopped', function () {
    $this->travel(PlanCatalog::TRIAL_DAYS + 1)->days();
    $this->artisan('subscriptions:expire-trials')->assertSuccessful();
    app(Entitlements::class)->forget($this->org);

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('entitlements.areas./crm', true)
            ->where('entitlements.areas./chat', false)
            ->where('entitlements.areas./seo', false)
            ->where('entitlements.areas./settings/teams', false));
});

it('says exactly what the server then does', function () {
    // Whatever the map says about an area is what opening a page there gets.
    $areas = app(PlanAreas::class)->included($this->org);

    foreach (['/chat' => 'chat.widgets.index', '/seo' => 'seo.local.index', '/ads' => 'ads.dashboard', '/settings/teams' => 'teams.index'] as $area => $page) {
        $response = $this->actingAs($this->owner)->get(route($page));

        $areas[$area] ? $response->assertOk() : $response->assertForbidden();
    }

    expect($areas['/chat'])->toBeTrue()->and($areas['/ads'])->toBeFalse();
});
