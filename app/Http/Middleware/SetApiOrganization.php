<?php

namespace App\Http\Middleware;

use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes tenant context for API requests (API-002). Token auth carries no
 * session "current organization", so the tenant is selected from the
 * `X-Organization-Id` header (validated against the caller's active
 * memberships) and falls back to the user's stored current organization. A
 * caller who is not a member of the requested org — or who has no resolvable
 * org — gets a 400 with the standard error envelope.
 *
 * Runs before SubstituteBindings (see the priority list in bootstrap/app.php)
 * so route-model binding for {contact}/{company}/{deal} is tenant-scoped.
 */
class SetApiOrganization
{
    public function __construct(private CurrentOrganization $currentOrganization) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error(__('Unauthenticated.'), 401);
        }

        $header = $request->header('X-Organization-Id');

        if ($header !== null && $header !== '') {
            if (! ctype_digit((string) $header)) {
                return $this->error(__('X-Organization-Id must be a numeric organization id.'));
            }

            $organization = $user->activeOrganizations()
                ->where('organizations.id', (int) $header)->first();

            if ($organization === null) {
                return $this->error(__('You are not a member of the requested organization.'));
            }
        } else {
            $organization = $user->current_organization_id !== null
                ? $user->activeOrganizations()->where('organizations.id', $user->current_organization_id)->first()
                : $user->activeOrganizations()->first();

            if ($organization === null) {
                return $this->error(__('No organization context. Pass an X-Organization-Id header.'));
            }
        }

        $this->currentOrganization->set($organization);

        // ENTL-004: API-call metering. Over-limit requests get a 429 and are
        // not counted (the caller pays for served requests only).
        $meter = app(UsageMeter::class);
        if (! $meter->withinLimit($organization, Limit::ApiCalls)) {
            return $this->error(__('API call limit for the current period reached.'), 429);
        }
        $meter->increment($organization, Limit::ApiCalls);

        return $next($request);
    }

    private function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => []], $status);
    }
}
