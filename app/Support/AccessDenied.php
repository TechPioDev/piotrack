<?php

namespace App\Support;

use App\Exceptions\FeatureNotInPlan;
use App\Models\Subscription;
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
    public function __construct(private readonly CurrentOrganization $currentOrganization) {}

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
            $subscription = $organization->activeSubscription()
                ?? $organization->subscriptions()->latest('id')->first();

            $state = $this->planState($subscription);
            // A date only when something actually ended on it.
            $ended = $subscription !== null && in_array($state, ['trial_ended', 'ended'], true)
                ? ($subscription->ends_at ?? $subscription->trial_ends_at)
                : null;

            return [
                'reason' => 'plan',
                'workspace' => $organization->name,
                'state' => $state,
                'plan' => $subscription?->plan?->name,
                'ended_on' => $ended?->toDateString(),
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

    private function planState(?Subscription $subscription): string
    {
        return match (true) {
            $subscription === null => 'none',
            in_array($subscription->status, Subscription::ACTIVE_STATES, true) => 'not_included',
            $subscription->status === 'expired' && $subscription->trial_ends_at !== null => 'trial_ended',
            $subscription->status === 'suspended' => 'suspended',
            default => 'ended',
        };
    }
}
