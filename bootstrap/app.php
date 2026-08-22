<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureEntitled;
use App\Http\Middleware\EnsureHasOrganization;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetApiOrganization;
use App\Http\Middleware\SetCurrentOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Behind a load balancer / platform proxy in production, trust the
        // forwarded headers so HTTPS, host and client IP are detected correctly.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB);

        $middleware->prepend(AssignRequestId::class);

        // Baseline security headers on every response, API and web alike (SEC-002).
        $middleware->append(SecurityHeaders::class);

        // Inbound billing webhooks authenticate via provider signature, not CSRF.
        // Public marketing form submits + unsubscribes are unauthenticated,
        // cross-origin capture endpoints protected by honeypot + throttling.
        // wc/* is the public chat-widget API: cross-origin + session-less, so CSRF
        // does not apply; it is protected by throttling, honeypot and origin checks.
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'f/*', 'e/*', 'b/*', 's/*', 'wc/*']);

        $middleware->web(append: [
            SetCurrentOrganization::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Tenant context MUST be established before route-model binding, so the
        // tenant scope is active when {team}/{invitation} are resolved —
        // otherwise binding could load another tenant's record. The API sets its
        // tenant from a header, so its middleware is ordered the same way.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetCurrentOrganization::class,
        );
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetApiOrganization::class,
        );

        $middleware->alias([
            'organization' => EnsureHasOrganization::class,
            'entitlement' => EnsureEntitled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->context(fn () => array_filter([
            'request_id' => request()->attributes->get('request_id'),
        ]));

        // Inertia treats any non-Inertia response as an error and renders it in a
        // blank modal overlay. Left unhandled, an expired session (419), a
        // permission block (403) or a server error (500) each pops that white box
        // over the app. Return proper Inertia responses instead.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            // Inertia requests are themselves AJAX, so only bypass genuine API /
            // JSON clients (which never carry the Inertia header) - otherwise the
            // Inertia visit gets a raw JSON error and pops the modal we're fixing.
            $isInertia = (bool) $request->header('X-Inertia');

            if (! $isInertia && ($request->is('api/*') || $request->expectsJson())) {
                return $response;
            }

            $status = $response->getStatusCode();

            // Session/CSRF expired: send the user back with a message rather than
            // rendering Laravel's "Page Expired" page inside the modal.
            if ($status === 419) {
                return back()->with('message', 'Your session expired — please try again.');
            }

            // Render hard error statuses as a real Inertia page. Keep the detailed
            // developer error page for 500s while debugging locally.
            if (in_array($status, [403, 404, 500, 503], true)) {
                if ($status === 500 && config('app.debug')) {
                    return $response;
                }

                return Inertia::render('errors/error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
