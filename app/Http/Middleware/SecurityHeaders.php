<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers (SEC-002).
 *
 * The CSP is deliberately conservative but Vite-compatible: Inertia ships a
 * small inline bootstrap and Vite injects inline styles, so `unsafe-inline` is
 * permitted for styles and, in local development only, for scripts. Framing is
 * denied outright (the product has no embeddable surface), and the browser is
 * told not to sniff content types or leak full referrers cross-origin.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Every page ships exactly one inline <script>: Ziggy's route table, from
        // the @routes directive. A per-request nonce is what lets it run under a
        // policy that otherwise forbids inline script, and the view has to stamp
        // the same value on the tag — so it is minted before $next() renders it.
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => $this->contentSecurityPolicy($nonce),
        ];

        // HSTS is only meaningful over TLS, and asserting it in local dev would
        // pin developers' browsers to https on localhost.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $header => $value) {
            if (! $response->headers->has($header)) {
                $response->headers->set($header, $value);
            }
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        // A nonce and 'unsafe-inline' are mutually exclusive to the browser: once a
        // nonce is present the keyword is ignored, so the two are never emitted
        // together. Local development keeps the keyword because Vite's dev client
        // and React refresh inject inline script this middleware never sees.
        $scriptSrc = app()->isProduction()
            ? "'self' 'nonce-{$nonce}'"
            : "'self' 'unsafe-inline' 'unsafe-eval'";

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src {$scriptSrc}",
            "connect-src 'self'".(app()->isProduction() ? '' : ' ws: http: https:'),
        ]);
    }
}
