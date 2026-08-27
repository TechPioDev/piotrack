<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\AttributionService;
use App\Services\Analytics\CommandCenterService;
use App\Services\Analytics\GrowthScoreService;
use App\Services\OnboardingChecklist;
use App\Support\CurrentOrganization;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        CurrentOrganization $currentOrganization,
        OnboardingChecklist $checklist,
        AnalyticsService $analytics,
        CommandCenterService $commandCenter,
        GrowthScoreService $growthScore,
        AttributionService $attribution,
    ): Response {
        // The landing page is deliberately ungated (CMDC module): funnel,
        // revenue and score are the tenant's own CRM-derived data. Entitlement
        // gating stays on the Analytics module's own routes.
        $funnel = $analytics->funnel();
        $score = $growthScore->compute();

        return Inertia::render('dashboard', [
            'onboarding' => $checklist->for($currentOrganization->get()),
            'kpis' => $commandCenter->kpis(),
            'leadTrend' => $commandCenter->leadTrend(),
            'mrrTrend' => $commandCenter->mrrTrend(),
            'growthScore' => [
                'overall' => $score['overall'],
                'recommendations' => array_slice($score['recommendations'], 0, 2),
                // Snapshots (the daily scheduler) feed the trend; the current
                // score is computed live so a young tenant still sees today.
                'history' => array_map(
                    fn (array $point) => ['label' => $point['date'], 'value' => $point['overall']],
                    $growthScore->trend(30),
                ),
            ],
            'funnel' => [
                ['label' => 'Leads', 'value' => $funnel['leads']],
                ['label' => 'MQLs', 'value' => $funnel['mqls']],
                ['label' => 'SQLs', 'value' => $funnel['sqls']],
                ['label' => 'Meetings', 'value' => $funnel['meetings']],
                ['label' => 'Opportunities', 'value' => $funnel['opportunities']],
                ['label' => 'Won', 'value' => $funnel['closed_won']],
            ],
            'channels' => collect($attribution->channelRevenue())
                ->map(fn (int $revenue, string $channel) => ['label' => $channel, 'value' => $revenue])
                ->values()
                ->all(),
            'attention' => $commandCenter->attention(),
            'topDeals' => $commandCenter->topDeals(),
            'sources' => $analytics->sourceBreakdown(),
        ]);
    }
}
