<?php

namespace App\Services\Ai;

use App\Models\AiScore;
use App\Models\Deal;

/**
 * Back-tests advisory AI scores against real outcomes (AISA-012/013): for every
 * deal that was AI-scored and has SINCE closed, the latest advisory score is
 * bucketed by outcome. Predictive quality is then the tenant's own measured
 * number — the platform never claims it. Guarded: too few closed, scored deals
 * per bucket and the report says so instead of computing fiction.
 */
class ScoreCalibration
{
    /** Fewer closed+scored deals than this per bucket proves nothing. */
    public const MIN_PER_BUCKET = 3;

    /**
     * @return array{insufficient_data: bool, required_per_bucket: int, won: array{count: int, avg_score: int|null}, lost: array{count: int, avg_score: int|null}, separation: int|null}
     */
    public function report(): array
    {
        $deals = Deal::whereIn('status', ['won', 'lost'])->get(['id', 'status']);

        // Latest advisory score per closed deal (scores after close still count:
        // what matters is the pairing of a score with a known outcome).
        $latest = AiScore::where('scoreable_type', 'deal')
            ->whereIn('scoreable_id', $deals->pluck('id'))
            ->orderBy('id')
            ->get(['scoreable_id', 'score'])
            ->keyBy('scoreable_id'); // later rows overwrite earlier => latest wins

        $buckets = ['won' => [], 'lost' => []];
        foreach ($deals as $deal) {
            $score = $latest->get($deal->id);
            if ($score !== null) {
                $buckets[$deal->status][] = $score->score;
            }
        }

        $insufficient = count($buckets['won']) < self::MIN_PER_BUCKET || count($buckets['lost']) < self::MIN_PER_BUCKET;
        $avg = fn (array $scores): ?int => $scores === [] ? null : (int) round(array_sum($scores) / count($scores));

        $wonAvg = $insufficient ? null : $avg($buckets['won']);
        $lostAvg = $insufficient ? null : $avg($buckets['lost']);

        return [
            'insufficient_data' => $insufficient,
            'required_per_bucket' => self::MIN_PER_BUCKET,
            'won' => ['count' => count($buckets['won']), 'avg_score' => $wonAvg],
            'lost' => ['count' => count($buckets['lost']), 'avg_score' => $lostAvg],
            'separation' => $wonAvg !== null && $lostAvg !== null ? $wonAvg - $lostAvg : null,
        ];
    }
}
