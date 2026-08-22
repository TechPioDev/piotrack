<?php

/**
 * SEC-002 — the production CSP forbids inline script, and the app ships exactly
 * one: Ziggy's route table from the @routes directive. Without a matching nonce
 * the browser blocks it, `route()` is never defined, and every Inertia page dies
 * on render — the whole app, not one screen. These tests pin the two halves
 * together: the nonce in the policy and the nonce on the tag.
 */
it('allows the route table through the production policy', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $response = $this->get('/login')->assertOk();

    $csp = (string) $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("script-src 'self' 'nonce-");
    expect($csp)->not->toContain("'unsafe-inline' 'unsafe-eval'");

    preg_match("/'nonce-([^']+)'/", $csp, $matches);
    expect($matches)->toHaveCount(2);

    // The tag the browser sees must carry the same value the header authorises.
    $response->assertSee('nonce="'.$matches[1].'"', escape: false);
});

it('issues a different nonce per request', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $first = $this->get('/login')->headers->get('Content-Security-Policy');
    $second = $this->get('/login')->headers->get('Content-Security-Policy');

    expect($first)->not->toBe($second);
});

it('keeps the relaxed script policy in local development', function () {
    $csp = (string) $this->get('/login')->headers->get('Content-Security-Policy');

    expect($csp)->toContain("script-src 'self' 'unsafe-inline' 'unsafe-eval'");
    // A nonce here would switch the browser off 'unsafe-inline' and break Vite.
    expect($csp)->not->toContain('nonce-');
});
