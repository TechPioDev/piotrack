<?php

namespace App\Http\Controllers\Advertising;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\RetargetingAudience;
use App\Services\Advertising\AdMetricsService;
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

        return Inertia::render('advertising/dashboard', [
            'trend' => $daily,
            'kpi' => $this->metrics->organizationKpi(now()->subDays(30))->toArray(),
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
                'kpi' => $this->metrics->campaignKpi($c, now()->subDays(30))->toArray(),
            ]),
        ]);
    }
}
