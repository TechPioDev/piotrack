<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require two-factor authentication once the app is reachable from the internet.
 *
 * The product already ships TOTP enrolment, recovery codes and a login challenge,
 * but all of it is opt-in. On a LAN that is a reasonable default; on a public
 * login page in front of multi-tenant CRM data, "everyone was asked to enrol" is
 * a policy that decays the first time somebody joins or turns it back off.
 *
 * Enforcement is off by default (REQUIRE_TWO_FACTOR) so local development and
 * tests are unaffected, and so switching it on is a deliberate act.
 *
 * Registered in the web group, so it covers interactive sessions only. API
 * tokens are deliberately out of scope: a token is a separate credential the
 * user created while already authenticated, and it is revocable on its own — the
 * same reason personal access tokens bypass 2FA elsewhere. Enforcing here would
 * also break every token the moment the flag is switched on.
 */
class RequireTwoFactor
{
    /**
     * Routes a user without 2FA must still reach: the enrolment flow itself, the
     * password confirmation guarding it, email verification, and the way out.
     * Without these the redirect below would be a loop with no escape.
     *
     * @var list<string>
     */
    private const EXEMPT = [
        'two-factor.show',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.recovery-codes',
        'two-factor.challenge',
        'password.confirm',
        'password.confirm.store',
        'logout',
        'verification.notice',
        'verification.verify',
        'verification.send',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.require_two_factor')) {
            return $next($request);
        }

        $user = $request->user();

        // Guests are the login flow's problem, not this middleware's. An account
        // that has completed enrolment has a confirmation timestamp; a secret on
        // its own only means enrolment was started.
        if ($user === null || $user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        $route = $request->route();
        if ($route !== null && in_array($route->getName(), self::EXEMPT, true)) {
            return $next($request);
        }

        // A fetch() against a web route cannot follow a redirect to an enrolment
        // screen usefully, so say so plainly instead. (Inertia visits carry their
        // own header and do follow redirects, so they are excluded here.)
        if (! $request->header('X-Inertia') && $request->expectsJson()) {
            abort(403, 'This account must complete two-factor authentication setup.');
        }

        return redirect()->route('two-factor.show')->with(
            'status',
            'Two-factor authentication is required. Set it up to continue.',
        );
    }
}
