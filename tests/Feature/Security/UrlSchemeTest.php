<?php

use Illuminate\Support\Facades\Route;

/**
 * Generated URLs follow the request, and are never forced to a scheme.
 *
 * This has now broken twice, in opposite directions. Keying `forceScheme` on the
 * environment name broke a production deployment that had no certificate yet:
 * every asset URL became https:// while the server only listened on http, so the
 * page rendered with no CSS or JS. Keying it on APP_URL instead broke the same
 * deployment from a different angle — with APP_URL set to the public https host,
 * reaching the app on its plain-HTTP LAN address produced redirects to https on
 * an HTTP port, and the browser's connection was closed with no error page.
 *
 * Both were the same mistake: one deployment is reachable by more than one
 * origin, so no single forced scheme can be correct. The request already knows.
 */
beforeEach(function () {
    Route::middleware('web')->get('/__scheme-probe', fn () => response(url('/login')));
});

it('generates http links for a request that arrived over http', function () {
    config(['app.url' => 'https://piotrack.com']);

    $this->get('http://192.168.1.230:8080/__scheme-probe')
        ->assertOk()
        ->assertSee('http://192.168.1.230:8080/login');
});

it('generates https links for a request that arrived over https', function () {
    config(['app.url' => 'https://piotrack.com']);

    $this->get('https://piotrack.com/__scheme-probe')
        ->assertOk()
        ->assertSee('https://piotrack.com/login');
});

it('redirects a guest back to the scheme they arrived on', function () {
    // The actual failure: a signed-out visit to a guarded page over plain HTTP
    // was redirected to https on port 8080, which speaks HTTP and hangs up.
    config(['app.url' => 'https://piotrack.com']);

    $location = (string) $this->get('http://192.168.1.230:8080/dashboard')
        ->assertRedirect()
        ->headers->get('Location');

    expect($location)->toStartWith('http://192.168.1.230:8080/')
        ->and($location)->not->toStartWith('https://192.168.1.230:8080/');
});
