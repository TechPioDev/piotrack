<?php

namespace App\Services\Analytics;

use App\Models\AdMetric;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Keyword;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;

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
    public const METRICS = ['cpl', 'conversion_rate', 'lead_to_sql', 'sql_to_meeting', 'meeting_to_proposal', 'proposal_to_win', 'avg_mrr', 'cac', 'time_to_close', 'seo_conversion_rate'];

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
            // BENCH-005: organic-sourced contacts who became customers.
            'seo_conversion_rate' => $this->ratio($this->organicCustomersByOrg(), $this->organicLeadsByOrg(), asPercent: true),
            default => [],
        };
    }

    /**
     * Segmented benchmarks (BENCH-003/004/013/014/015/016): the same
     * k-anonymity floor applied PER SEGMENT — a segment contributed to by
     * fewer orgs than the floor is omitted entirely, never blurred into an
     * average that could be reverse-engineered.
     *
     * @return array<string, array{label: string, unit: string, columns: list<string>, segments: list<array<string, mixed>>}>
     */
    public function segmented(): array
    {
        return [
            'cpc_by_service' => [
                'label' => 'CPC by service', 'unit' => 'cents', 'columns' => ['peer_median_cpc', 'your_cpc'],
                'segments' => $this->cpcSegments('service_lines.key', fn ($q) => $q
                    ->join('service_lines', 'service_lines.id', '=', 'ad_campaigns.service_line_id')),
            ],
            'cpc_by_region' => [
                'label' => 'CPC by city / region', 'unit' => 'cents', 'columns' => ['peer_median_cpc', 'your_cpc'],
                // Region when the branch has one, city otherwise.
                'segments' => $this->cpcSegments(
                    "COALESCE(NULLIF(seo_locations.region, ''), seo_locations.city)",
                    fn ($q) => $q->join('seo_locations', 'seo_locations.id', '=', 'ad_campaigns.seo_location_id'),
                ),
            ],
            'top_keywords' => ['label' => 'Best-performing keywords', 'unit' => 'position', 'columns' => ['page_one_share', 'median_position'], 'segments' => $this->keywordSegments()],
            'top_offers' => ['label' => 'Best-performing offers', 'unit' => 'percent', 'columns' => ['peer_median_completion', 'your_completion'], 'segments' => $this->offerSegments()],
            'top_verticals' => ['label' => 'Best-performing verticals', 'unit' => 'cents', 'columns' => ['peer_median_deal_value', 'your_deal_value'], 'segments' => $this->verticalSegments()],
            'top_ads' => ['label' => 'Best-performing ads', 'unit' => 'percent', 'columns' => ['peer_median_ctr', 'peer_median_cpc', 'your_ctr'], 'segments' => $this->adSegments()],
        ];
    }

    /**
     * CPC per segment: spend/clicks per (segment, org) from the metrics of the
     * campaigns bound to that dimension; only orgs with clicks contribute.
     *
     * @param  string  $segmentSql  raw SQL for the segment key (trusted, in-code)
     * @param  callable(Builder<AdMetric>): mixed  $joinDimension
     * @return list<array<string, mixed>>
     */
    private function cpcSegments(string $segmentSql, callable $joinDimension): array
    {
        $query = AdMetric::withoutGlobalScope('tenant')
            ->join('ad_campaigns', 'ad_campaigns.id', '=', 'ad_metrics.ad_campaign_id');
        $joinDimension($query);

        $rows = $query
            ->toBase()
            ->selectRaw($segmentSql.' AS segment, ad_metrics.organization_id')
            ->selectRaw('SUM(ad_metrics.spend) AS spend, SUM(ad_metrics.clicks) AS clicks')
            ->groupBy('segment', 'ad_metrics.organization_id')
            ->get();

        $bySegment = [];
        foreach ($rows as $row) {
            if ((int) $row->clicks > 0 && (string) $row->segment !== '') {
                $bySegment[(string) $row->segment][(int) $row->organization_id] = round((int) $row->spend / (int) $row->clicks, 2);
            }
        }

        return $this->emit($bySegment, 'peer_median_cpc', 'your_cpc');
    }

    /**
     * BENCH-013: keyword phrases tracked by enough orgs — page-one share and
     * the cohort's median best position, best first.
     *
     * @return list<array<string, mixed>>
     */
    private function keywordSegments(): array
    {
        $rows = Keyword::withoutGlobalScope('tenant')
            ->where('is_tracked', true)->whereNotNull('current_position')
            ->toBase()
            ->selectRaw('LOWER(TRIM(phrase)) AS segment, organization_id, MIN(current_position) AS best')
            ->groupBy('segment', 'organization_id')
            ->get();

        $bySegment = [];
        foreach ($rows as $row) {
            $bySegment[(string) $row->segment][(int) $row->organization_id] = (float) $row->best;
        }

        $out = [];
        foreach ($bySegment as $segment => $byOrg) {
            if (count($byOrg) < $this->minCohort()) {
                continue;
            }
            $positions = array_values($byOrg);
            sort($positions);
            $out[] = [
                'segment' => $segment,
                'cohort' => count($byOrg),
                'page_one_share' => (int) round(count(array_filter($positions, fn ($p) => $p <= 10)) / count($positions) * 100),
                'median_position' => $this->percentile($positions, 50),
                'your_best' => isset($byOrg[$this->current->id()]) ? (int) $byOrg[$this->current->id()] : null,
            ];
        }
        usort($out, fn ($a, $b) => [$b['page_one_share'], $a['median_position']] <=> [$a['page_one_share'], $b['median_position']]);

        return $out;
    }

    /**
     * BENCH-014: booking-page meeting types (the canonical bottom-of-funnel
     * offers) by completion rate.
     *
     * @return list<array<string, mixed>>
     */
    private function offerSegments(): array
    {
        $rows = Booking::withoutGlobalScope('tenant')
            ->join('booking_pages', 'booking_pages.id', '=', 'bookings.booking_page_id')
            ->toBase()
            ->selectRaw('booking_pages.meeting_type AS segment, bookings.organization_id')
            ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN bookings.status = 'completed' THEN 1 ELSE 0 END) AS done")
            ->groupBy('segment', 'bookings.organization_id')
            ->get();

        $bySegment = [];
        foreach ($rows as $row) {
            if ((int) $row->total > 0) {
                $bySegment[(string) $row->segment][(int) $row->organization_id] = round((int) $row->done / (int) $row->total * 100, 2);
            }
        }

        return $this->emit($bySegment, 'peer_median_completion', 'your_completion');
    }

    /**
     * BENCH-015: industries (normalized) by average won-deal value.
     *
     * @return list<array<string, mixed>>
     */
    private function verticalSegments(): array
    {
        $rows = Deal::withoutGlobalScope('tenant')
            ->join('companies', 'companies.id', '=', 'deals.company_id')
            ->where('deals.status', 'won')
            ->whereNotNull('companies.industry')->where('companies.industry', '!=', '')
            ->toBase()
            ->selectRaw('LOWER(TRIM(companies.industry)) AS segment, deals.organization_id, AVG(deals.value) AS avg_value')
            ->groupBy('segment', 'deals.organization_id')
            ->get();

        $bySegment = [];
        foreach ($rows as $row) {
            $bySegment[(string) $row->segment][(int) $row->organization_id] = round((float) $row->avg_value, 2);
        }

        return $this->emit($bySegment, 'peer_median_deal_value', 'your_deal_value');
    }

    /**
     * BENCH-016: ad platforms by click-through rate, with median CPC alongside.
     *
     * @return list<array<string, mixed>>
     */
    private function adSegments(): array
    {
        $rows = AdMetric::withoutGlobalScope('tenant')
            ->join('ad_campaigns', 'ad_campaigns.id', '=', 'ad_metrics.ad_campaign_id')
            ->toBase()
            ->selectRaw('ad_campaigns.platform AS segment, ad_metrics.organization_id')
            ->selectRaw('SUM(ad_metrics.impressions) AS impressions, SUM(ad_metrics.clicks) AS clicks, SUM(ad_metrics.spend) AS spend')
            ->groupBy('segment', 'ad_metrics.organization_id')
            ->get();

        $ctr = [];
        $cpc = [];
        foreach ($rows as $row) {
            if ((int) $row->impressions > 0) {
                $ctr[(string) $row->segment][(int) $row->organization_id] = round((int) $row->clicks / (int) $row->impressions * 100, 2);
            }
            if ((int) $row->clicks > 0) {
                $cpc[(string) $row->segment][(int) $row->organization_id] = round((int) $row->spend / (int) $row->clicks, 2);
            }
        }

        $out = $this->emit($ctr, 'peer_median_ctr', 'your_ctr');
        foreach ($out as &$segment) {
            $values = array_values($cpc[$segment['segment']] ?? []);
            sort($values);
            $segment['peer_median_cpc'] = $values === [] ? null : $this->percentile($values, 50);
        }

        return $out;
    }

    /**
     * Emit segments above the k-floor: cohort, peer median, the current
     * tenant's own value — never any single peer's raw number.
     *
     * @param  array<string, array<int, float>>  $bySegment
     * @return list<array<string, mixed>>
     */
    private function emit(array $bySegment, string $medianKey, string $yourKey): array
    {
        $out = [];
        foreach ($bySegment as $segment => $byOrg) {
            if (count($byOrg) < $this->minCohort()) {
                continue; // suppressed: too few contributors to anonymize
            }
            $values = array_values($byOrg);
            sort($values);
            $out[] = [
                'segment' => $segment,
                'cohort' => count($byOrg),
                $medianKey => $this->percentile($values, 50),
                $yourKey => $byOrg[$this->current->id()] ?? null,
            ];
        }
        usort($out, fn ($a, $b) => [$b['cohort'], $a['segment']] <=> [$a['cohort'], $b['segment']]);

        return $out;
    }

    /** @return array<int, int> */
    private function organicLeadsByOrg(): array
    {
        return Contact::withoutGlobalScope('tenant')->where('lead_source', 'organic')
            ->selectRaw('organization_id, COUNT(*) AS v')->groupBy('organization_id')
            ->pluck('v', 'organization_id')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<int, int> */
    private function organicCustomersByOrg(): array
    {
        return Contact::withoutGlobalScope('tenant')->where('lead_source', 'organic')
            ->where('lifecycle_stage', 'customer')
            ->selectRaw('organization_id, COUNT(*) AS v')->groupBy('organization_id')
            ->pluck('v', 'organization_id')->map(fn ($v) => (int) $v)->all();
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
