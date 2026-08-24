<?php

declare(strict_types=1);

/**
 * Every page reachable from the navigation must actually render.
 *
 * Feature tests cover each module's behaviour, but nothing checked that the
 * whole menu opens for a signed-in owner. A page that throws returns a
 * non-Inertia response, which the client renders as a blank white overlay with
 * no error text — the failure is invisible from the code and obvious to anyone
 * clicking around, which is the worst way round.
 *
 * This walks every parameterless authenticated GET route on the top plan and
 * fails on the first that errors, naming it.
 */

use App\Billing\Entitlements;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Smoke Test Ltd');
    subscribeOrganization($this->org, 'enterprise');
    app(Entitlements::class)->forget($this->org);
    app(CurrentOrganization::class)->set($this->org);
});

/**
 * @return list<array{0: string, 1: string}> [name, uri]
 */
function navigableRoutes(): array
{
    $skip = [
        // Leaves the session, so it would end the walk.
        'logout',
        // Streams or downloads rather than rendering a page.
        'files.download',
    ];

    $out = [];
    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }
        $uri = $route->uri();
        $name = $route->getName() ?? $uri;

        // Parameterised routes need a fixture each; the menu itself is flat.
        if (str_contains($uri, '{') || in_array($name, $skip, true)) {
            continue;
        }
        // Only pages behind the session guard — public marketing surfaces and
        // the widget API are covered by their own suites.
        // gatherMiddleware() returns the aliases as written on the routes.
        $middleware = array_filter($route->gatherMiddleware(), 'is_string');
        if (! in_array('auth', $middleware, true)) {
            continue;
        }

        $out[] = [$name, $uri];
    }

    usort($out, fn ($a, $b) => $a[0] <=> $b[0]);

    return $out;
}

it('opens every page in the navigation', function () {
    $routes = navigableRoutes();
    expect($routes)->not->toBeEmpty();

    $broken = [];
    foreach ($routes as [$name, $uri]) {
        try {
            $response = $this->actingAs($this->owner)->get('/'.ltrim($uri, '/'));
            $status = $response->getStatusCode();
        } catch (Throwable $e) {
            $broken[] = sprintf('%s (/%s) threw %s: %s', $name, $uri, class_basename($e), $e->getMessage());

            continue;
        }

        // The platform console belongs to platform staff, not to a tenant's own
        // owner. 403 there is the isolation working, not a broken page — a
        // tenant admin reaching it was a P0 in an earlier audit, so it is
        // asserted rather than skipped.
        if (str_starts_with($name, 'platform.')) {
            if ($status !== 403) {
                $broken[] = sprintf('%s (/%s) returned %d — expected 403 for a tenant owner', $name, $uri, $status);
            }

            continue;
        }

        // A redirect is fine — an unmet precondition sends you somewhere useful.
        // Anything from 400 up is a page a user cannot open.
        if ($status >= 400) {
            $broken[] = sprintf('%s (/%s) returned %d', $name, $uri, $status);
        }
    }

    expect($broken)->toBe([], "Pages that do not open:\n  ".implode("\n  ", $broken));
});
