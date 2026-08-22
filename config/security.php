<?php

$proxies = trim((string) env('TRUSTED_PROXIES', ''));

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Which proxies' X-Forwarded-* headers may be believed. Empty — the default —
    | trusts none, which is correct whenever browsers reach the app directly:
    | otherwise any client can spoof X-Forwarded-For and defeat the per-IP login
    | throttle, and X-Forwarded-Proto to make a plaintext request look secure.
    |
    | Behind a TLS-terminating load balancer or CDN, list the proxy addresses or
    | CIDR ranges, comma-separated, so HTTPS, host and client IP are still
    | detected correctly:
    |
    |     TRUSTED_PROXIES=10.0.0.0/8,192.168.0.0/16
    |
    | "*" trusts every hop. That is only safe when nothing untrusted can reach
    | the app directly — i.e. the proxy is the sole ingress.
    |
    */

    'trusted_proxies' => $proxies === '*'
        ? '*'
        : array_values(array_filter(array_map('trim', explode(',', $proxies)))),

    /*
    |--------------------------------------------------------------------------
    | Require two-factor authentication
    |--------------------------------------------------------------------------
    |
    | When true, a signed-in account that has not completed TOTP enrolment is
    | redirected to the setup page and can reach nothing else. Off by default so
    | local development and tests are unaffected, and so enabling it on a
    | deployment is a deliberate act rather than a surprise.
    |
    | Turn this on before the login page is reachable from the internet.
    |
    */

    'require_two_factor' => (bool) env('REQUIRE_TWO_FACTOR', false),

];
