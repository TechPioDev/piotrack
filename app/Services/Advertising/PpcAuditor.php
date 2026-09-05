<?php

namespace App\Services\Advertising;

use App\Models\AdCampaign;
use App\Models\AdGroup;

/**
 * PPC-010: account audit over the structure and metrics the platform holds
 * first-party. Structural gaps (missing ads, keywords, negatives, extensions,
 * destination URLs) and performance findings that cite their numbers. Auditing
 * an EXTERNAL live Google/Microsoft account needs the Ads API (ADR-0006) and
 * is not claimed here.
 */
class PpcAuditor
{
    private const SEARCH_PLATFORMS = ['google_search', 'microsoft'];

    public function __construct(private AdMetricsService $metrics) {}

    /**
     * @return list<array{campaign_id: int, campaign: string, severity: string, finding: string}>
     */
    public function audit(): array
    {
        $findings = [];

        $campaigns = AdCampaign::with(['groups.ads', 'groups.keywords', 'extensions'])
            ->whereIn('status', ['draft', 'active', 'paused'])
            ->orderBy('id')
            ->get();

        foreach ($campaigns as $campaign) {
            $add = function (string $severity, string $finding) use (&$findings, $campaign): void {
                $findings[] = [
                    'campaign_id' => $campaign->id,
                    'campaign' => $campaign->name,
                    'severity' => $severity,
                    'finding' => $finding,
                ];
            };

            if ($campaign->groups->isEmpty()) {
                $add('error', __('No ad groups — the campaign cannot serve.'));

                continue;
            }

            foreach ($campaign->groups as $group) {
                if ($group->ads->isEmpty()) {
                    $add('error', __('Ad group ":group" has no ads.', ['group' => $group->name]));
                }
                if (in_array($campaign->platform, self::SEARCH_PLATFORMS, true) && $group->keywords->where('is_negative', false)->isEmpty()) {
                    $add('error', __('Ad group ":group" has no keywords.', ['group' => $group->name]));
                }
            }

            $adsWithoutDestination = $campaign->groups->flatMap(fn (AdGroup $g) => $g->ads)->whereNull('destination_url')->count();
            if ($adsWithoutDestination > 0) {
                $add('warning', __(':n ad(s) have no destination URL.', ['n' => $adsWithoutDestination]));
            }

            $negatives = $campaign->groups->flatMap(fn (AdGroup $g) => $g->keywords)->where('is_negative', true)->count();
            if (in_array($campaign->platform, self::SEARCH_PLATFORMS, true) && $negatives === 0) {
                $add('warning', __('No negative keywords — irrelevant queries will spend the budget.'));
            }

            if (in_array($campaign->platform, self::SEARCH_PLATFORMS, true) && $campaign->extensions->isEmpty()) {
                $add('warning', __('No ad extensions — sitelinks and callouts lift CTR at no extra cost.'));
            }

            // Performance findings only where there is data to cite.
            $kpi = $this->metrics->campaignKpi($campaign, now()->subDays(30));
            if ($kpi->impressions >= 500 && $kpi->ctr < 0.01) {
                $add('warning', __('CTR :ctr% over :impressions impressions (30 days) — below the 1% floor.', ['ctr' => round($kpi->ctr * 100, 2), 'impressions' => $kpi->impressions]));
            }
            if ($campaign->isActive() && $kpi->impressions === 0) {
                $add('warning', __('Active with no recorded impressions in 30 days — check delivery.'));
            }
        }

        return $findings;
    }
}
