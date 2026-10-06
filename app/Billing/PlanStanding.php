<?php

namespace App\Billing;

use App\Models\Organization;
use App\Models\Subscription;

/**
 * Where a workspace stands with its plan, in words a person can act on: still
 * active, trial ended, subscription ended, on hold, or never had one.
 *
 * Entitlements answers "may this workspace use X". This answers the question a
 * person asks next - "why not, and since when" - and is the one place that
 * reads a subscription's status for that purpose, for the page that was
 * refused and for the notice that says so before anything is refused.
 */
class PlanStanding
{
    /**
     * @param  Subscription|null  $active  the workspace's active subscription when the caller
     *                                     has already looked it up; null means it has none
     * @return array{state: 'active'|'trial_ended'|'ended'|'suspended'|'none', plan: string|null, ended_on: string|null}
     */
    public function describe(Organization $organization, ?Subscription $active): array
    {
        $subscription = $active ?? $organization->subscriptions()->latest('id')->first();

        $state = match (true) {
            $subscription === null => 'none',
            in_array($subscription->status, Subscription::ACTIVE_STATES, true) => 'active',
            $subscription->status === 'expired' && $subscription->trial_ends_at !== null => 'trial_ended',
            $subscription->status === 'suspended' => 'suspended',
            default => 'ended',
        };

        // A date only when something actually ended on it.
        $ended = $subscription !== null && in_array($state, ['trial_ended', 'ended'], true)
            ? ($subscription->ends_at ?? $subscription->trial_ends_at)
            : null;

        return [
            'state' => $state,
            'plan' => $subscription?->plan?->name,
            'ended_on' => $ended?->toDateString(),
        ];
    }
}
