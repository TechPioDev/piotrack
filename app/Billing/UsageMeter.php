<?php

namespace App\Billing;

use App\Models\Competitor;
use App\Models\Contact;
use App\Models\File;
use App\Models\Keyword;
use App\Models\Organization;
use App\Models\SeoLocation;
use App\Models\UsageCounter;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Usage metering & reporting (ENTL-005/006/007). Two kinds of meter:
 *  - live meters computed from current state (e.g. members = active members +
 *    pending invitations, i.e. seats consumed),
 *  - counter meters accumulated in usage_counters for the current period
 *    (emails, API calls, … as their modules land).
 */
class UsageMeter
{
    public function __construct(private Entitlements $entitlements) {}

    public function usage(Organization $organization, Limit|string $key): int
    {
        $key = $key instanceof Limit ? $key->value : $key;

        // ENTL-004: stock resources meter LIVE from current state — the truth
        // is the row count, not an accumulator that can drift. Flow resources
        // (emails, sms, api_calls, ai_credits, workflow_executions) stay on
        // period counters.
        return match ($key) {
            Limit::Members->value => $this->memberSeatsUsed($organization),
            Limit::Contacts->value => $this->tenantCount($organization, Contact::class),
            Limit::Keywords->value => $this->tenantCount($organization, Keyword::class),
            Limit::Competitors->value => $this->tenantCount($organization, Competitor::class),
            Limit::Locations->value => $this->tenantCount($organization, SeoLocation::class),
            Limit::Automations->value => $this->tenantCount($organization, Workflow::class),
            Limit::StorageMb->value => (int) ceil((float) File::withoutGlobalScope('tenant')
                ->where('organization_id', $organization->id)->sum('size') / 1_048_576),
            default => $this->counterUsage($organization, $key),
        };
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function tenantCount(Organization $organization, string $model): int
    {
        return $model::withoutGlobalScope('tenant')
            ->where('organization_id', $organization->id)->count();
    }

    /**
     * Remaining allowance (null = unlimited).
     */
    public function remaining(Organization $organization, Limit|string $key): ?int
    {
        $limit = $this->entitlements->limit($organization, $key);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->usage($organization, $key));
    }

    /**
     * Whether $additional more units stay within the limit.
     */
    public function withinLimit(Organization $organization, Limit|string $key, int $additional = 1): bool
    {
        $limit = $this->entitlements->limit($organization, $key);

        if ($limit === null) {
            return true; // unlimited
        }

        return $this->usage($organization, $key) + $additional <= $limit;
    }

    /**
     * ENTL-004/007: the choke-point guard — refuses the action with a clear,
     * plan-upgrade-pointing message when it would exceed the limit.
     *
     * @throws ValidationException
     */
    public function assertWithin(Organization $organization, Limit $key, int $additional = 1, string $errorKey = 'limit'): void
    {
        if ($this->withinLimit($organization, $key, $additional)) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => __('The plan\'s :limit limit (:n) is reached — upgrade the plan or remove unused items.', [
                'limit' => str_replace('_', ' ', $key->value),
                'n' => (int) $this->entitlements->limit($organization, $key),
            ]),
        ]);
    }

    public function increment(Organization $organization, Limit|string $key, int $by = 1): void
    {
        $key = $key instanceof Limit ? $key->value : $key;
        [$start, $end] = $this->currentPeriod($organization);

        $counter = UsageCounter::firstOrCreate(
            ['organization_id' => $organization->id, 'key' => $key, 'period_start' => $start],
            ['period_end' => $end, 'used' => 0],
        );

        $counter->increment('used', $by);
    }

    /**
     * A display-ready summary for the billing portal: every limit the plan
     * defines, with used / allowance / remaining.
     *
     * @return list<array{key: string, used: int, limit: int|null, remaining: int|null}>
     */
    public function summary(Organization $organization): array
    {
        $rows = [];

        foreach ($this->entitlements->limits($organization) as $key => $limit) {
            $used = $this->usage($organization, $key);
            $rows[] = [
                'key' => $key,
                'used' => $used,
                'limit' => $limit,
                'remaining' => $limit === null ? null : max(0, $limit - $used),
            ];
        }

        return $rows;
    }

    private function memberSeatsUsed(Organization $organization): int
    {
        $members = $organization->members()->wherePivot('status', 'active')->count();
        $pending = $organization->invitations()->pending()->count();

        return $members + $pending;
    }

    private function counterUsage(Organization $organization, string $key): int
    {
        [$start] = $this->currentPeriod($organization);

        return (int) UsageCounter::query()
            ->where('organization_id', $organization->id)
            ->where('key', $key)
            ->where('period_start', $start)
            ->value('used');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function currentPeriod(Organization $organization): array
    {
        $subscription = $organization->activeSubscription();

        if ($subscription === null) {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }

        return [
            $subscription->current_period_start ?? now()->startOfMonth(),
            $subscription->current_period_end ?? now()->endOfMonth(),
        ];
    }
}
