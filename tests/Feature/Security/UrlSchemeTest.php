<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

/**
 * Generated URLs follow the scheme APP_URL declares, not the environment name.
 *
 * Keying this on `isProduction()` broke a production deployment that had not got
 * a certificate yet: every asset URL became https:// while the server only
 * listened on http, so the page loaded with no CSS or JS at all.
 */
afterEach(function () {
    URL::forceScheme(null);
});

it('does not force https when APP_URL is plain http', function () {
    config(['app.url' => 'http://192.168.1.230:8080']);
    URL::forceScheme(null);

    (new AppServiceProvider(app()))->boot();

    expect(url('/login'))->toStartWith('http://');
});

it('forces https when APP_URL declares it', function () {
    config(['app.url' => 'https://app.piotrack.com']);
    URL::forceScheme(null);

    (new AppServiceProvider(app()))->boot();

    expect(url('/login'))->toStartWith('https://');
});
