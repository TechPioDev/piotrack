<?php

namespace App\Services\Analytics;

use App\Models\AdMetric;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Deal;
use App\Support\CurrentOrganization;

/**
 * Proprietary benchmark data layer (BENCH). Computes anonymized peer benchmarks
 * ACROSS tenants (deliberately crossing the tenant scope) and returns only
 * aggregates — never a single tenant's raw value — guarded by a k-anonymity
 * minimum-cohort floor: a metric computed from fewer than the threshold of
 * organizations is suppressed (returns null).
 */
class BenchmarkService
{
    /** Metrics with a real cross-tenant computation today. */
    public const METRICS = ['cpl', 'conversion_rate', 'lead_to_sql', 'sql_to_meeting', 'meeting_to_proposal', 'proposal_to_win', 'avg_mrr', 'cac', 'time_to_close'];

    public function __construct(private CurrentOrganization $current) {}

    public function minCohort(): int
    {
        return max(1, (int) config('analytics.benchmark_min_cohort', 3));
    }

    /**
     * A single anonymized benchmark + the current tenant's standing. Returns null
     * when the contributing cohort is below the k-anonymity floor.
     *
     * @return array{metric: string, cohort: int, peer_median: float, top_quartile: float, peer_average: float, your_value: float|null, your_percentile: int|null}|null
     */
    public function benchmark(string $metric): ?array
    {
        $byOrg = $this->perOrgValues($metric);
        $cohort = count($byOrg);
        if ($cohort < $this->minCohort()) {
            return null; // suppressed: too few contributors to anonymize
        }

        $values = array_values($byOrg);
        sort($values);

        $yourValue = $byOrg[$this->current->id()] ?? null;

        return [
            'metric' => $metric,
            'cohort' => $cohort,
            'peer_median' => $this->percentile($values, 50),
            'top_quartile' => $this->percentile($values, 75),
            'peer_average' => round(array_sum($values) / $cohort, 2),
            'your_value' => $yourValue !== null ? round($yourValue, 2) : null,
            'your_percentile' => $yourValue !== null ? $this->percentileRank($values, $yourValue) : null,
        ];
    }

    /**
     * Every benchmark, suppressed entries omitted.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $out = [];
        foreach (self::METRICS as $metric) {
            $result = $this->benchmark($metric);
            if ($result !== null) {
                $out[$metric] = $result;
            }
        }

        return $out;
    }

    /**
     * Per-organization metric values across all tenants.
     *
     * @return array<int, float>
     */
    private function perOrgValues(string $metric): array
    {
        return match ($metric) {
            'cpl' => $this->ratio($this->spendByOrg(), $this->leadsByOrg()),
            'conversion_rate' => $this->ratio($this->wonCountByOrg(), $this->leadsByOrg(), asPercent: true),
            'lead_to_sql' => $this->ratio($this->sqlsByOrg(), $this->leadsByOrg(), asPercent: true),
            'sql_to_meeting' => $this->ratio($this->meetingsByOrg(), $this->sqlsByOrg(), asPercent: true),
            // BENCH-008/009: the proposal-stage convention (is_proposal stage,
            // first-entry proposal_sent_at stamp) makes these honest.
            'meeting_to_proposal' => $this->ratio($this->proposalsByOrg(), $this->meetingsByOrg(), asPercent: true),
            'proposal_to_win' => $this->ratio($this->wonWithProposalByOrg(), $this->proposalsByOrg(), asPercent: true),
            'avg_mrr' => $this->wonMrrByOrg(),
            'cac' => $this->ratio($this->spendByOrg(), $this->wonCountByOrg()),
            'time_to_close' => $this->timeToCloseByOrg(),
            default => [],
        };
    }

    /**
     * Divide two per-org maps; only orgs with a positive denominator contribute.
     *
     * @param  array<int, float|int>  $num
     * @param  array<int, float|int>  $den
     * @return array<int, float>
     */
    private function ratio(array $num, array $den, bool $asPercent = false): array
    {
        $out = [];
        foreach ($den as $orgId => $d) {
            if ($d > 0) {
                $value = ($num[$orgId] ?? 0) / $d;
                $out[$orgId] = $asPercent ? round($value * 100, 2) : round($value, 2);
            }
        }

        return $out;
    }

    /** @return array<int, int> */
    private function leadsByOrg(): array
    {
        return Contact::withoutGlobalScope('tenant')
            ->selectRaw('organization_id, COUNT(*) AS v')->groupBy('organization_id')
            ->pluck('v', 'organization_id')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<int, int> */
    private function sqlsByOrg(): array
    {
        return Contact::withoutGlobalScope('tenant')->where('lifecycle_stage', 'sql')
            ->selectRaw('organization_id, COUNT(*) AS v')->groupBy('organization_id')
            ->pluck('v', 'organization_id')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<int, int> */
    private function spendByOrg(): array
    {
        return AdMetric::withoutGlobalScope('tenant')
            ->selectRaw('organization_id, COALESCE(SUM(spend),0) AS v')->groupBy('organization_id')
            ->pluck('v', 'organization_id')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<int, int> */
    private function meetingsByOrg(): array
    {
        return Booking::withoutGlobalScope('tenant')
            ->selectRaw('organization_id, COUNT(*) AS v')->groupBy('organization_id')
            ->pluck('v', 'organization_id')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<int, int> */
    private function proposalsByOrg(): array
    {
        return Deal::withoutGlobalScope('tenant')->whereNotNull('proposal_sent_at')
            ->selectRaw('organization_id, COUNT(*) AS v')->groupBy('organization_id')
            ->pluck('v', 'organization_id')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<int, int> */
    private function wonWithProposalByOrg(): array
    {
        return Deal::withoutGlobalScope('tenant')->whereNotNull('proposal_sent_at')
            ->whereHas('stage', fn ($q) => $q->where('is_won', true))
            ->selectRaw('organization_id, COUNT(*) AS v')->groupBy('organization_id')
            ->pluck('v', 'organization_id')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<int, int> */
    private function wonCountByOrg(): array
    {
        return Deal::withoutGlobalScope('tenant')->whereHas('stage', fn ($q) => $q->where('is_won', true))
            ->selectRaw('organization_id, COUNT(*) AS v')->groupBy('organization_id')
            ->pluck('v', 'organization_id')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<int, int> */
    private function wonMrrByOrg(): array
    {
        return Deal::withoutGlobalScope('tenant')->whereHas('stage', fn ($q) => $q->where('is_won', true))
            ->selectRaw('organization_id, COALESCE(SUM(mrr),0) AS v')->groupBy('organization_id')
            ->pluck('v', 'organization_id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Average days from deal creation to close, per org, over won deals.
     *
     * @return array<int, float>
     */
    private function timeToCloseByOrg(): array
    {
        $out = [];
        Deal::withoutGlobalScope('tenant')
            ->whereHas('stage', fn ($q) => $q->where('is_won', true))
            ->whereNotNull('closed_at')
            ->get(['organization_id', 'created_at', 'closed_at'])
            ->groupBy('organization_id')
            ->each(function ($deals, $orgId) use (&$out) {
                $days = $deals->map(fn ($d) => $d->created_at->diffInDays($d->closed_at));
                $out[(int) $orgId] = round($days->avg(), 2);
            });

        return $out;
    }

    /**
     * @param  list<float|int>  $sorted  ascending
     */
    private function percentile(array $sorted, int $p): float
    {
        $count = count($sorted);
        if ($count === 0) {
            return 0.0;
        }
        $rank = ($p / 100) * ($count - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);
        if ($low === $high) {
            return round((float) $sorted[$low], 2);
        }

        return round($sorted[$low] + ($sorted[$high] - $sorted[$low]) * ($rank - $low), 2);
    }

    /**
     * @param  list<float|int>  $sorted  ascending
     */
    private function percentileRank(array $sorted, float $value): int
    {
        $count = count($sorted);
        if ($count === 0) {
            return 0;
        }
        $atOrBelow = count(array_filter($sorted, fn ($v) => $v <= $value));

        return (int) round(($atOrBelow / $count) * 100);
    }
}
