<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * SEC-002 — forwarded headers.
 *
 * X-Forwarded-For and X-Forwarded-Proto are client input until a proxy is
 * explicitly trusted. Believing them unconditionally lets any caller choose the
 * IP the login throttle counts against, and claim a plaintext request arrived
 * over TLS. The default is therefore to trust nothing; a deployment behind a
 * load balancer opts in through TRUSTED_PROXIES.
 */
beforeEach(function () {
    // Pin the documented default so the result does not depend on whatever the
    // developer happens to have in their own .env.
    TrustProxies::at([]);

    Route::middleware('web')->get('/__forwarded-probe', fn (Request $request) => [
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
    ]);
});

afterEach(function () {
    // The trusted list lives in a static, so put back what the app booted with.
    TrustProxies::at(config('security.trusted_proxies'));

    putenv('TRUSTED_PROXIES');
    unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
});

it('ignores a spoofed client ip when no proxy is trusted', function () {
    $this->get('/__forwarded-probe', ['X-Forwarded-For' => '203.0.113.9'])
        ->assertOk()
        ->assertJsonPath('ip', '127.0.0.1');
});

it('ignores a spoofed https claim when no proxy is trusted', function () {
    $this->get('/__forwarded-probe', ['X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertJsonPath('secure', false);
});

it('honours forwarded headers once the proxy is trusted', function () {
    TrustProxies::at('*');

    $this->get('/__forwarded-probe', [
        'X-Forwarded-For' => '203.0.113.9',
        'X-Forwarded-Proto' => 'https',
    ])
        ->assertOk()
        ->assertJsonPath('ip', '203.0.113.9')
        ->assertJsonPath('secure', true);
});

it('reads the proxy list from the environment', function (string $raw, array|string $expected) {
    putenv("TRUSTED_PROXIES=$raw");
    $_ENV['TRUSTED_PROXIES'] = $raw;
    $_SERVER['TRUSTED_PROXIES'] = $raw;

    $config = require base_path('config/security.php');

    expect($config['trusted_proxies'])->toBe($expected);
})->with([
    'unset trusts nothing' => ['', []],
    'wildcard trusts every hop' => ['*', '*'],
    'a single proxy' => ['10.0.0.1', ['10.0.0.1']],
    'a list, whitespace tolerated' => ['10.0.0.0/8, 192.168.0.0/16', ['10.0.0.0/8', '192.168.0.0/16']],
]);
