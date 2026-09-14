<?php

namespace App\Http\Controllers\Advertising;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\RetargetingAudience;
use App\Services\Advertising\AdMetricsService;
use App\Services\Analytics\PeriodComparison;
use Inertia\Inertia;
use Inertia\Response;

class AdDashboardController extends Controller
{
    public function __construct(private AdMetricsService $metrics) {}

    public function __invoke(): Response
    {
        // Daily spend and clicks for the trend charts (design-shell module).
        // ad_metrics is one row per campaign per day, so group across campaigns.
        $since = now()->subDays(29)->startOfDay();
        $daily = AdMetric::query()
            ->where('date', '>=', $since)
            ->selectRaw('date, sum(spend) as spend, sum(clicks) as clicks')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn (AdMetric $row) => [
                'label' => $row->date->format('M j'),
                'spend' => (int) $row->spend,
                'clicks' => (int) $row->clicks,
            ])
            ->values();

        // The KPI window is the same 30 days the trend draws, compared with
        // the 30 before; ratios (CTR, CPA, ROAS) are shown, not delta'd.
        $period = new PeriodComparison;
        $kpi = $this->metrics->organizationKpi($period->windowStart(1));
        $previous = $this->metrics->organizationKpi($period->windowStart(2), $period->windowStart(1));

        return Inertia::render('advertising/dashboard', [
            'trend' => $daily,
            'kpi' => $kpi->toArray(),
            'flows' => [
                'spend' => PeriodComparison::of($kpi->spend, $previous->spend),
                'impressions' => PeriodComparison::of($kpi->impressions, $previous->impressions),
                'clicks' => PeriodComparison::of($kpi->clicks, $previous->clicks),
                'conversions' => PeriodComparison::of($kpi->conversions, $previous->conversions),
                'revenue' => PeriodComparison::of($kpi->revenue, $previous->revenue),
            ],
            'stats' => [
                'campaigns' => AdCampaign::count(),
                'active' => AdCampaign::where('status', 'active')->count(),
                'audiences' => RetargetingAudience::count(),
            ],
            'campaigns' => AdCampaign::latest('id')->limit(8)->get()->map(fn (AdCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'platform' => $c->platform,
                'status' => $c->status,
                'kpi' => $this->metrics->campaignKpi($c, $period->windowStart(1))->toArray(),
            ]),
        ]);
    }
}
