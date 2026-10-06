<?php

namespace App\Support;

use App\Billing\PlanStanding;
use App\Exceptions\FeatureNotInPlan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Throwable;

/**
 * Why a page answered 403, in terms the person looking at it can act on.
 *
 * Two different things refuse a page - the workspace's plan and the person's
 * role - and they are fixed by different people in different places. The error
 * page used to say the same sentence for both, so an owner whose free trial had
 * ended saw every module turn into "Not authorized, contact your administrator"
 * with nothing to say the plan was the cause or that Billing was the way out.
 */
class AccessDenied
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly PlanStanding $standing,
    ) {}

    /**
     * @return array<string, mixed>|null null when there is nothing more useful to say
     */
    public function describe(Throwable $e, Request $request): ?array
    {
        $organization = $this->currentOrganization->get();
        $user = $request->user();

        if ($organization === null || $user === null) {
            return null;
        }

        if ($e instanceof FeatureNotInPlan) {
            $standing = $this->standing->describe($organization, $organization->activeSubscription());

            return [
                'reason' => 'plan',
                'workspace' => $organization->name,
                // A plan that is running fine simply does not include this.
                'state' => $standing['state'] === 'active' ? 'not_included' : $standing['state'],
                'plan' => $standing['plan'],
                'ended_on' => $standing['ended_on'],
                // Only someone who can change the plan is sent to do it.
                'can_manage_billing' => $user->can('billing.manage'),
            ];
        }

        // A permission check said no (the `can:` middleware or a policy).
        if ($e->getPrevious() instanceof AuthorizationException || $e instanceof AuthorizationException) {
            return [
                'reason' => 'role',
                'workspace' => $organization->name,
                'role' => $user->roleIn($organization)?->label(),
            ];
        }

        return null;
    }
}
