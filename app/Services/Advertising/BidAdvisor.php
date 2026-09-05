<?php

namespace App\Services\Advertising;

use App\Models\AdCampaign;
use App\Models\AdGroup;
use App\Services\Ai\AiGateway;

/**
 * PPC-013/014: bid management advice from the campaign's OWN recorded metrics.
 * Rule-based recommendations run on every campaign page; the AI advisory runs
 * on demand through the governed gateway. Both are advisory only — the rep
 * applies changes to the ad groups' bid fields, and automated live bidding
 * stays behind the Ads API seam (ADR-0006). Below the data floor nothing is
 * recommended: insufficient data says so instead of guessing.
 */
class BidAdvisor
{
    /** Minimum 30-day clicks before any recommendation is made. */
    public const MIN_CLICKS = 20;

    public function __construct(
        private AdMetricsService $metrics,
        private AiGateway $gateway,
    ) {}

    /**
     * Rule-based recommendations, each citing the number that triggered it.
     *
     * @return array{sufficient: bool, items: list<array{rule: string, message: string}>}
     */
    public function recommendations(AdCampaign $campaign): array
    {
        $kpi = $this->metrics->campaignKpi($campaign, now()->subDays(30));

        if ($kpi->clicks < self::MIN_CLICKS) {
            return ['sufficient' => false, 'items' => []];
        }

        $items = [];
        $ctrPct = round($kpi->ctr * 100, 2);

        if ($kpi->ctr < 0.01) {
            $items[] = [
                'rule' => 'low_ctr',
                'message' => __('CTR is :ctr% over :clicks clicks — below 1%. Tighten keywords and ad copy before raising bids; low relevance makes every click cost more.', ['ctr' => $ctrPct, 'clicks' => $kpi->clicks]),
            ];
        }

        if ($kpi->conversions === 0 && $kpi->spend > 0) {
            $items[] = [
                'rule' => 'spend_without_conversions',
                'message' => __(':spend spent over 30 days with 0 conversions. Lower manual bids or pause the weakest ad groups until tracking or targeting is fixed.', ['spend' => number_format($kpi->spend / 100, 2)]),
            ];
        }

        $days = max(1, (int) now()->subDays(30)->diffInDays(now()));
        $dailySpend = (int) round($kpi->spend / $days);
        if ($campaign->daily_budget > 0 && $dailySpend > $campaign->daily_budget) {
            $items[] = [
                'rule' => 'over_pacing',
                'message' => __('Average daily spend (:spend) exceeds the daily budget (:budget). Lower bids or budget to stop over-pacing.', ['spend' => number_format($dailySpend / 100, 2), 'budget' => number_format($campaign->daily_budget / 100, 2)]),
            ];
        } elseif ($campaign->daily_budget > 0 && $dailySpend < (int) ($campaign->daily_budget * 0.5) && $kpi->conversions > 0) {
            $items[] = [
                'rule' => 'under_pacing',
                'message' => __('Spend is :spend/day against a :budget/day budget with :conversions conversions — headroom exists. Raising bids on converting ad groups can buy more volume.', ['spend' => number_format($dailySpend / 100, 2), 'budget' => number_format($campaign->daily_budget / 100, 2), 'conversions' => $kpi->conversions]),
            ];
        }

        return ['sufficient' => true, 'items' => $items];
    }

    /**
     * On-demand AI advisory over the same first-party numbers (PPC-014).
     * Returns null below the data floor — the gateway is never called to guess.
     */
    public function aiAdvice(AdCampaign $campaign): ?string
    {
        $kpi = $this->metrics->campaignKpi($campaign, now()->subDays(30));

        if ($kpi->clicks < self::MIN_CLICKS) {
            return null;
        }

        $bids = $campaign->groups()->get()
            ->map(fn (AdGroup $g) => $g->name.': '.($g->bid_strategy ?? 'manual_cpc').' @ '.number_format(((int) $g->bid_amount) / 100, 2))
            ->implode('; ');

        return $this->gateway->run('ads.bidding', 'ads.bidding', [
            'name' => $campaign->name,
            'platform' => $campaign->platform,
            'objective' => (string) $campaign->objective,
            'daily_budget' => $campaign->daily_budget,
            'metrics' => sprintf(
                'impressions %d, clicks %d, CTR %.2f%%, spend %d, CPC %d, conversions %d, CPA %d',
                $kpi->impressions, $kpi->clicks, $kpi->ctr * 100, $kpi->spend, (int) round($kpi->cpc), $kpi->conversions, (int) round($kpi->cpa),
            ),
            'bids' => $bids === '' ? 'none' : $bids,
        ])->text;
    }
}
