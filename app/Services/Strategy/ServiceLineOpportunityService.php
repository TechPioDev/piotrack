<?php

namespace App\Services\Strategy;

use App\Models\AdCampaign;
use App\Models\Deal;
use App\Models\SalesAsset;
use App\Models\ServiceLine;
use App\Models\SitePage;

/**
 * STRAT-014: service-line opportunity analysis over hard bindings — deals,
 * site pages, ad campaigns and sales assets all carry service_line_id, so
 * every number is a join on records, not name matching. Recommendations cite
 * the numbers that triggered them (the P43 pattern) and the analysis says how
 * many deals remain unbound rather than guessing where they belong.
 */
class ServiceLineOpportunityService
{
    /**
     * @return array{lines: list<array<string, mixed>>, unbound_deals: int}
     */
    public function analysis(): array
    {
        $deals = Deal::whereNotNull('service_line_id')->get()->groupBy('service_line_id');
        $pages = SitePage::whereNotNull('service_line_id')->get()->groupBy('service_line_id');
        $campaigns = AdCampaign::whereNotNull('service_line_id')->get()->groupBy('service_line_id');
        $assets = SalesAsset::whereNotNull('service_line_id')->get()->groupBy('service_line_id');

        $lines = ServiceLine::where('is_active', true)->orderBy('name')->get()
            ->map(function (ServiceLine $line) use ($deals, $pages, $campaigns, $assets) {
                $lineDeals = $deals->get($line->id, collect());
                $won = $lineDeals->where('status', 'won');
                $openDeals = $lineDeals->where('status', 'open');
                $closed = $lineDeals->whereIn('status', ['won', 'lost']);

                $wonMrr = (int) $won->sum('mrr');
                $openPipeline = (int) $openDeals->sum('value');
                $pageCount = $pages->get($line->id, collect())->count();
                $campaignCount = $campaigns->get($line->id, collect())->count();
                $assetCount = $assets->get($line->id, collect())->count();

                $recommendations = [];
                if ($lineDeals->isEmpty()) {
                    $recommendations[] = __('No deals are bound to this service line yet — bind them on the deal form to measure it.');
                }
                if ($wonMrr > 0 && $pageCount === 0) {
                    $recommendations[] = __(':mrr MRR is won on this line but no site page sells it — publish one.', ['mrr' => '$'.number_format($wonMrr / 100, 2)]);
                }
                if ($openPipeline > 0 && $campaignCount === 0) {
                    $recommendations[] = __(':value open pipeline has no ad campaign behind it.', ['value' => '$'.number_format($openPipeline / 100, 2)]);
                }
                if ($won->count() > 0 && $assetCount === 0) {
                    $recommendations[] = __(':count deals won without any sales collateral bound to this line.', ['count' => $won->count()]);
                }

                return [
                    'id' => $line->id,
                    'name' => $line->name,
                    'category' => $line->category,
                    'deals' => $lineDeals->count(),
                    'won' => $won->count(),
                    'win_rate' => $closed->isEmpty() ? null : round($won->count() / $closed->count() * 100, 1),
                    'won_mrr' => $wonMrr,
                    'open_pipeline' => $openPipeline,
                    'pages' => $pageCount,
                    'campaigns' => $campaignCount,
                    'sales_assets' => $assetCount,
                    'recommendations' => $recommendations,
                ];
            })
            ->sortByDesc('won_mrr')->values()->all();

        return [
            'lines' => $lines,
            'unbound_deals' => Deal::whereNull('service_line_id')->count(),
        ];
    }
}
