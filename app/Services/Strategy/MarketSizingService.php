<?php

namespace App\Services\Strategy;

use App\Models\Deal;
use App\Models\StrategyItem;

/**
 * STRAT-005: TAM/SAM/SOM analysis the honest way. The platform never invents
 * market data — the market count is always human-entered. Its contribution is
 * everything computable: the tenant's own economics derived from real won
 * deals (median MRR, real win rate), server-side math, per-input provenance,
 * and an auditable saved artefact (the P44 ROI-calculator pattern).
 */
class MarketSizingService
{
    /** Closed deals required before the real win rate is used as a default. */
    public const MIN_CLOSED_FOR_RATE = 5;

    /**
     * Defaults derived from the tenant's own records, each labeled with where
     * it came from so the calculator form can say so.
     *
     * @return array{avg_mrr: ?int, avg_mrr_source: string, win_rate_pct: ?float, win_rate_source: string, won_deals: int, closed_deals: int}
     */
    public function derivedDefaults(): array
    {
        $mrrs = Deal::where('status', 'won')->pluck('mrr')->map(fn ($m) => (int) $m)->sort()->values();
        $won = $mrrs->count();
        $lost = Deal::where('status', 'lost')->count();
        $closed = $won + $lost;

        $median = null;
        if ($won > 0) {
            $mid = intdiv($won, 2);
            $median = $won % 2 === 1 ? $mrrs[$mid] : (int) (($mrrs[$mid - 1] + $mrrs[$mid]) / 2);
        }

        $winRate = $closed >= self::MIN_CLOSED_FOR_RATE ? round($won / $closed * 100, 1) : null;

        return [
            'avg_mrr' => $median,
            'avg_mrr_source' => $median !== null ? 'derived_from_records' : 'none',
            'win_rate_pct' => $winRate,
            'win_rate_source' => $winRate !== null ? 'derived_from_records' : 'none',
            'won_deals' => $won,
            'closed_deals' => $closed,
        ];
    }

    /**
     * Compute the model. Money is minor units throughout.
     *
     * @param  array{market_businesses: int, addressable_pct?: float|int|null, reachable_pct?: float|int|null, avg_mrr?: float|int|null, win_rate_pct?: float|int|null}  $inputs
     * @return array<string, mixed>
     */
    public function model(array $inputs): array
    {
        $defaults = $this->derivedDefaults();

        $market = (int) $inputs['market_businesses'];
        $addressablePct = (float) ($inputs['addressable_pct'] ?? 100);
        $reachablePct = (float) ($inputs['reachable_pct'] ?? 10);

        $avgMrr = isset($inputs['avg_mrr']) && $inputs['avg_mrr'] !== null
            ? (int) round((float) $inputs['avg_mrr'] * 100)
            : $defaults['avg_mrr'];
        $mrrSource = isset($inputs['avg_mrr']) && $inputs['avg_mrr'] !== null ? 'entered' : $defaults['avg_mrr_source'];

        $winRatePct = isset($inputs['win_rate_pct']) && $inputs['win_rate_pct'] !== null
            ? (float) $inputs['win_rate_pct']
            : $defaults['win_rate_pct'];
        $rateSource = isset($inputs['win_rate_pct']) && $inputs['win_rate_pct'] !== null ? 'entered' : $defaults['win_rate_source'];

        $tamAccounts = $market;
        $samAccounts = (int) round($market * $addressablePct / 100);
        $somAccounts = $winRatePct !== null
            ? (int) round($samAccounts * $reachablePct / 100 * $winRatePct / 100)
            : null;

        $mrrFor = fn (?int $accounts) => $accounts !== null && $avgMrr !== null ? $accounts * $avgMrr : null;

        return [
            'inputs' => [
                'market_businesses' => $market,
                'addressable_pct' => $addressablePct,
                'reachable_pct' => $reachablePct,
                'avg_mrr' => $avgMrr,
                'win_rate_pct' => $winRatePct,
            ],
            // Where each figure came from — entered by the rep, derived from
            // real records, or unavailable. The market count is ALWAYS entered.
            'provenance' => [
                'market_businesses' => 'entered',
                'addressable_pct' => 'entered',
                'reachable_pct' => 'entered',
                'avg_mrr' => $mrrSource,
                'win_rate_pct' => $rateSource,
            ],
            'tam_accounts' => $tamAccounts,
            'sam_accounts' => $samAccounts,
            'som_accounts' => $somAccounts,
            'tam_mrr' => $mrrFor($tamAccounts),
            'sam_mrr' => $mrrFor($samAccounts),
            'som_mrr' => $mrrFor($somAccounts),
            'som_arr' => $mrrFor($somAccounts) !== null ? $mrrFor($somAccounts) * 12 : null,
            'evidence' => [
                'won_deals' => $defaults['won_deals'],
                'closed_deals' => $defaults['closed_deals'],
            ],
        ];
    }

    /**
     * Persist the analysis as an auditable research item: every figure and its
     * provenance in the findings, reproducible from the recorded inputs.
     *
     * @param  array<string, mixed>  $model
     */
    public function save(array $model, string $title): StrategyItem
    {
        $int = fn (mixed $v): ?int => $v === null ? null : (int) $v;
        $fmt = fn (?int $minor) => $minor === null ? 'n/a' : '$'.number_format($minor / 100, 2);

        /** @var array{market_businesses: int, addressable_pct: float, reachable_pct: float, avg_mrr: ?int, win_rate_pct: ?float} $in */
        $in = $model['inputs'];
        /** @var array<string, string> $prov */
        $prov = $model['provenance'];
        /** @var array{won_deals: int, closed_deals: int} $evidence */
        $evidence = $model['evidence'];

        $lines = [
            sprintf('Market businesses: %d (%s)', $in['market_businesses'], $prov['market_businesses']),
            sprintf('Addressable: %.1f%% -> SAM %d accounts', $in['addressable_pct'], (int) $model['sam_accounts']),
            sprintf('Reachable: %.1f%%', $in['reachable_pct']),
            sprintf('Avg MRR: %s (%s)', $fmt($in['avg_mrr']), $prov['avg_mrr']),
            sprintf('Win rate: %s (%s)', $in['win_rate_pct'] !== null ? $in['win_rate_pct'].'%' : 'n/a', $prov['win_rate_pct']),
            sprintf('TAM: %d accounts / %s MRR', (int) $model['tam_accounts'], $fmt($int($model['tam_mrr']))),
            sprintf('SAM: %d accounts / %s MRR', (int) $model['sam_accounts'], $fmt($int($model['sam_mrr']))),
            sprintf('SOM: %s accounts / %s MRR / %s ARR',
                $int($model['som_accounts']) ?? 'n/a', $fmt($int($model['som_mrr'])), $fmt($int($model['som_arr']))),
            sprintf('Derived from %d won / %d closed deals on record.', $evidence['won_deals'], $evidence['closed_deals']),
        ];

        return StrategyItem::create([
            'type' => 'research',
            'title' => $title,
            'findings' => implode("\n", $lines),
            'priority' => 'medium',
            'status' => 'complete',
            'source_module' => 'strategy',
        ]);
    }
}
