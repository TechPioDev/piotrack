<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\FunnelInsights;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsDashboardController extends Controller
{
    public function __invoke(AnalyticsService $analytics, FunnelInsights $insights): Response
    {
        return Inertia::render('analytics/dashboard', [
            'metrics' => $analytics->dashboard(),
            // CRO-012/013/015/016: step drop-off, winning paths and the
            // recommendations they trigger — every line cites its number.
            'funnel_insights' => [
                'drop_off' => $insights->dropOff(),
                'paths' => $insights->conversionPaths(),
                'recommendations' => $insights->recommendations(),
            ],
        ]);
    }
}
