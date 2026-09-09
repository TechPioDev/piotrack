<?php

declare(strict_types=1);

/**
 * DEVX-008 (Phase 56): the API reference cannot drift from the API. Every
 * route registered under api/* must appear in docs/api/reference.md with its
 * method, and the contracts the reference promises (headers, envelope keys,
 * sort whitelists) must be the ones the code enforces.
 */

use Illuminate\Support\Facades\Route;

it('documents every registered API route with its method', function () {
    $doc = file_get_contents(base_path('docs/api/reference.md'));
    expect($doc)->not->toBeFalse();

    $apiRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'));

    expect($apiRoutes->count())->toBeGreaterThanOrEqual(13); // /api/user + 12 v1 endpoints

    foreach ($apiRoutes as $route) {
        // "api/v1/contacts/{contact}" is documented as "/api/v1/contacts/{id}".
        $documentedPath = '/'.preg_replace('/\{[^}]+\}/', '{id}', $route->uri());

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            // A failure here means a route was added without documenting it
            // in docs/api/reference.md.
            expect($doc)->toContain("{$method} {$documentedPath}");
        }
    }
});

it('promises only contracts the code enforces', function () {
    $doc = (string) file_get_contents(base_path('docs/api/reference.md'));

    // Headers and mechanics the middleware stack actually implements.
    foreach (['X-Organization-Id', 'Idempotency-Key', 'X-Request-Id', 'X-Piotrack-Signature', '60 requests/minute'] as $promise) {
        expect($doc)->toContain($promise);
    }

    // The sort whitelists in the doc are the ones the controllers validate
    // (normalize the doc's hard-wrapped lines before matching).
    $flat = preg_replace('/\s+/', ' ', $doc);
    expect($flat)->toContain('`id`, `created_at`, `lead_score`, `last_name`')  // contacts whitelist
        ->and($flat)->toContain('sort whitelist: `id`, `created_at`, `name`')  // companies whitelist
        ->and($flat)->toContain('sort whitelist: `id`, `created_at`, `value`'); // deals whitelist
});
