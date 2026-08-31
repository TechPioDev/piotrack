<?php

namespace App\Services\Ai;

use App\Models\AiPrompt;
use App\Models\AiVisibilityCheck;
use App\Models\Competitor;
use App\Seo\Contracts\AiSearchProvider;
use App\Seo\SeoProviderManager;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;

/**
 * AI visibility dashboard (AIVIS). Runs the tenant's monitored prompt library
 * across AI engines through the Stage 7 {@see AiSearchProvider} (fixture driver
 * tested; live engines credential-gated), then reports share of voice, mention /
 * citation / recommendation frequency, competitor comparison, service / city /
 * vertical breakdowns, trend and change alerts.
 *
 * Every figure is computed from recorded checks — a dimension with no checks
 * reports zero rather than an invented number.
 */
class AiVisibilityDashboard
{
    /** Engines monitored when the caller does not specify. */
    public const ENGINES = ['chatgpt', 'gemini', 'perplexity', 'copilot', 'google_ai'];

    public function __construct(
        private AuditLogger $audit,
    ) {}

    /**
     * Run every active library prompt across the given engines, recording one
     * check per prompt/engine (AIVIS-001…006). Idempotent per prompt/engine/day.
     *
     * @param  list<string>|null  $engines
     */
    public function runLibrary(string $brand, ?array $engines = null): int
    {
        $engines ??= self::ENGINES;
        $recorded = 0;

        // Known competitor names sharpen position analysis (AIVM): the brand's
        // rank is measured against entities we can actually detect.
        $competitorNames = Competitor::query()->pluck('name')->filter()->values()->all();
        $manager = app(SeoProviderManager::class);

        foreach (AiPrompt::where('is_active', true)->get() as $prompt) {
            foreach ($engines as $engine) {
                $alreadyToday = AiVisibilityCheck::where('ai_prompt_id', $prompt->id)
                    ->where('engine', $engine)
                    ->whereDate('checked_at', now()->toDateString())
                    ->exists();

                if ($alreadyToday) {
                    continue;
                }

                // Engine-specific driver: ChatGPT/Gemini go live with a key,
                // the rest stay clearly simulated (AIVIS-002/003).
                $result = $manager->aiFor($engine)->query($prompt->text, $brand, $competitorNames);

                AiVisibilityCheck::create([
                    'ai_prompt_id' => $prompt->id,
                    'prompt' => $prompt->text,
                    'engine' => $engine,
                    'provider' => $manager->aiProviderNameFor($engine),
                    'brand' => $brand,
                    'mentioned' => $result->mentioned,
                    // A top-three placement is treated as an active recommendation.
                    'recommended' => $result->mentioned && $result->position !== null && $result->position <= 3,
                    'position' => $result->position,
                    'cited_sources' => $result->citedSources,
                    'competitors' => $result->competitors,
                    'share_of_answer' => $result->shareOfAnswer,
                    'answer_excerpt' => $result->answerExcerpt !== '' ? $result->answerExcerpt : null,
                    'checked_at' => now(),
                ]);

                $recorded++;
            }
        }

        $this->audit->log('ai.visibility.checked', context: ['brand' => $brand, 'checks' => $recorded]);

        return $recorded;
    }

    /**
     * Mention / citation / recommendation frequency as percentages of all checks
     * (AIVIS-008/009/010).
     *
     * @return array{checks: int, mention_rate: float, citation_rate: float, recommendation_rate: float}
     */
    public function frequencies(): array
    {
        $checks = AiVisibilityCheck::count();
        if ($checks === 0) {
            return ['checks' => 0, 'mention_rate' => 0.0, 'citation_rate' => 0.0, 'recommendation_rate' => 0.0];
        }

        $mentioned = AiVisibilityCheck::where('mentioned', true)->count();
        $recommended = AiVisibilityCheck::where('recommended', true)->count();
        $cited = AiVisibilityCheck::get(['cited_sources'])
            ->filter(fn (AiVisibilityCheck $c) => ! empty($c->cited_sources))
            ->count();

        return [
            'checks' => $checks,
            'mention_rate' => round($mentioned / $checks * 100, 2),
            'citation_rate' => round($cited / $checks * 100, 2),
            'recommendation_rate' => round($recommended / $checks * 100, 2),
        ];
    }

    /**
     * Our average share of answer across checks (AIVIS-007).
     */
    public function shareOfVoice(): float
    {
        $checks = AiVisibilityCheck::count();

        return $checks > 0 ? round((float) AiVisibilityCheck::avg('share_of_answer'), 2) : 0.0;
    }

    /**
     * Per-engine visibility (AIVIS-002…006).
     *
     * @return list<array{engine: string, checks: int, mention_rate: float, share_of_answer: float}>
     */
    public function byEngine(): array
    {
        return AiVisibilityCheck::query()
            ->selectRaw('engine, COUNT(*) AS checks, SUM(CASE WHEN mentioned THEN 1 ELSE 0 END) AS mentions, AVG(share_of_answer) AS share')
            ->groupBy('engine')->get()
            ->map(fn ($r) => [
                'engine' => (string) $r->engine,
                'checks' => (int) ($r->checks ?? 0),
                'mention_rate' => (int) ($r->checks ?? 0) > 0 ? round((int) ($r->mentions ?? 0) / (int) ($r->checks ?? 1) * 100, 2) : 0.0,
                'share_of_answer' => round((float) ($r->share ?? 0), 2),
            ])->all();
    }

    /**
     * How often each competitor appears alongside us (AIVIS-011).
     *
     * @return list<array{domain: string, appearances: int, share: float}>
     */
    public function competitorComparison(): array
    {
        $checks = AiVisibilityCheck::count();
        $counts = [];

        foreach (AiVisibilityCheck::get(['competitors']) as $check) {
            foreach ($check->competitors ?? [] as $domain) {
                $counts[$domain] = ($counts[$domain] ?? 0) + 1;
            }
        }

        $rows = [];
        foreach ($counts as $domain => $appearances) {
            $rows[] = [
                'domain' => (string) $domain,
                'appearances' => $appearances,
                'share' => $checks > 0 ? round($appearances / $checks * 100, 2) : 0.0,
            ];
        }
        usort($rows, fn ($a, $b) => $b['appearances'] <=> $a['appearances']);

        return $rows;
    }

    /**
     * Visibility broken down by a library dimension (AIVIS-012/013/014).
     *
     * @return list<array{value: string, checks: int, mention_rate: float}>
     */
    public function byDimension(string $dimension): array
    {
        if (! in_array($dimension, ['service', 'city', 'vertical'], true)) {
            return [];
        }

        $rows = [];
        foreach (AiPrompt::whereNotNull($dimension)->get() as $prompt) {
            $value = (string) $prompt->{$dimension};
            $checks = AiVisibilityCheck::where('ai_prompt_id', $prompt->id)->count();
            $mentions = AiVisibilityCheck::where('ai_prompt_id', $prompt->id)->where('mentioned', true)->count();

            if (! isset($rows[$value])) {
                $rows[$value] = ['value' => $value, 'checks' => 0, 'mentions' => 0];
            }
            $rows[$value]['checks'] += $checks;
            $rows[$value]['mentions'] += $mentions;
        }

        return array_values(array_map(fn (array $r) => [
            'value' => $r['value'],
            'checks' => $r['checks'],
            'mention_rate' => $r['checks'] > 0 ? round($r['mentions'] / $r['checks'] * 100, 2) : 0.0,
        ], $rows));
    }

    /**
     * Daily mention-rate trend (AIVIS-016).
     *
     * @return list<array{date: string, checks: int, mention_rate: float}>
     */
    public function trend(int $days = 30): array
    {
        $since = now()->subDays($days)->startOfDay();
        $buckets = [];

        foreach (AiVisibilityCheck::where('checked_at', '>=', $since)->get(['mentioned', 'checked_at']) as $check) {
            $date = $check->checked_at?->toDateString() ?? '';
            if (! isset($buckets[$date])) {
                $buckets[$date] = ['checks' => 0, 'mentions' => 0];
            }
            $buckets[$date]['checks']++;
            $buckets[$date]['mentions'] += $check->mentioned ? 1 : 0;
        }

        ksort($buckets);

        $out = [];
        foreach ($buckets as $date => $b) {
            $out[] = [
                'date' => (string) $date,
                'checks' => $b['checks'],
                'mention_rate' => round($b['mentions'] / $b['checks'] * 100, 2),
            ];
        }

        return $out;
    }

    /**
     * Alert when the mention rate moves by more than `threshold` points between
     * the previous window and the current one (AIVIS-017).
     *
     * @return array{changed: bool, direction: string, delta: float, current: float, previous: float}
     */
    public function alert(int $windowDays = 7, float $threshold = 10.0): array
    {
        $current = $this->mentionRateBetween(now()->subDays($windowDays), now());
        $previous = $this->mentionRateBetween(now()->subDays($windowDays * 2), now()->subDays($windowDays));

        $delta = round($current - $previous, 2);

        return [
            'changed' => abs($delta) >= $threshold,
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
            'delta' => $delta,
            'current' => $current,
            'previous' => $previous,
        ];
    }

    private function mentionRateBetween(Carbon $from, Carbon $to): float
    {
        $checks = AiVisibilityCheck::whereBetween('checked_at', [$from, $to])->count();
        if ($checks === 0) {
            return 0.0;
        }

        $mentions = AiVisibilityCheck::whereBetween('checked_at', [$from, $to])->where('mentioned', true)->count();

        return round($mentions / $checks * 100, 2);
    }
}
