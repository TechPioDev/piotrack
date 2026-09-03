<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\CommandCenterService;
use App\Services\Analytics\GrowthScoreService;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use App\Support\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF performance report export (IMEX-004): the Command Center's numbers as a
 * clean one-page PDF, generated natively — no rendering dependency. Every
 * figure comes from the same services the dashboard reads.
 */
class ReportExportController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    public function __invoke(
        CommandCenterService $commandCenter,
        AnalyticsService $analytics,
        GrowthScoreService $growthScore,
    ): StreamedResponse {
        $organization = $this->currentOrganization->get();
        $kpis = $commandCenter->kpis();
        $funnel = $analytics->funnel();
        $score = $growthScore->compute();

        $money = fn (int $minor): string => '$'.number_format($minor / 100, 2);
        $delta = function (array $kpi): string {
            if ($kpi['delta_pct'] === null) {
                return $kpi['value'] > 0 ? ' (new)' : '';
            }

            return sprintf(' (%+.1f%%)', $kpi['delta_pct']);
        };

        $lines = [
            ['text' => 'Generated '.now()->toDayDateTimeString().' (UTC) - last 30 days vs the 30 before', 'size' => 9],
            ['text' => ''],
            ['text' => 'Key performance', 'size' => 13, 'bold' => true],
            ['text' => 'New leads: '.$kpis['new_leads']['value'].$delta($kpis['new_leads'])],
            ['text' => 'Meetings booked: '.$kpis['meetings']['value'].$delta($kpis['meetings'])],
            ['text' => 'Deals won: '.$kpis['deals_won']['value'].$delta($kpis['deals_won'])],
            ['text' => 'New MRR: '.$money($kpis['new_mrr']['value']).$delta($kpis['new_mrr'])],
            ['text' => 'Qualified pipeline: '.$money($kpis['qualified_pipeline'])],
            ['text' => 'ARR: '.$money($kpis['arr'])],
            ['text' => ''],
            ['text' => 'Funnel', 'size' => 13, 'bold' => true],
            ['text' => sprintf('Leads %d  >  MQLs %d  >  SQLs %d  >  Meetings %d  >  Opportunities %d  >  Won %d',
                $funnel['leads'], $funnel['mqls'], $funnel['sqls'], $funnel['meetings'], $funnel['opportunities'], $funnel['closed_won'])],
            ['text' => ''],
            ['text' => 'Growth score: '.$score['overall'].' / 100', 'size' => 13, 'bold' => true],
        ];

        foreach (array_slice($score['recommendations'], 0, 3) as $recommendation) {
            $lines[] = ['text' => '- '.$recommendation['action'], 'size' => 9];
        }

        $pdf = Pdf::document(($organization->name ?? 'Piotrack').' - Growth Report', $lines);

        $this->audit->log('data.exported', context: ['resource' => 'growth_report', 'format' => 'pdf'], organizationId: $this->currentOrganization->id());

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, 'growth-report-'.now()->format('Y-m-d').'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
