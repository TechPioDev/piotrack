<?php

namespace App\Http\Middleware;

use App\Billing\Entitlements;
use App\Exceptions\FeatureNotInPlan;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backend feature gating (ENTL-003): blocks a route unless the current
 * organization's plan includes the named feature. Usage: `entitlement:teams`.
 * The frontend receives the same entitlement map to show upgrade prompts, but
 * this middleware is the security boundary.
 */
class EnsureEntitled
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private Entitlements $entitlements,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $organization = $this->currentOrganization->get();

        if ($organization === null || ! $this->entitlements->feature($organization, $feature)) {
            throw new FeatureNotInPlan($feature);
        }

        return $next($request);
    }
}
