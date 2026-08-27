<?php

namespace App\Services\Analytics;

use App\Models\AiVisibilityCheck;
use App\Models\Competitor;
use App\Models\Keyword;
use App\Models\KeywordRanking;

/**
 * Competitive intelligence (CINT). Computes share-of-voice / market-share-of-
 * search across the tenant's tracked keywords from our own ranking data: our
 * organic visibility vs each competitor's captured positions. Deeper competitor
 * monitoring (PPC, ads, backlinks, content, maps, reviews, social) needs external
 * SERP/Ahrefs/SEMrush providers and is Planned.
 */
class CompetitiveService
{
    /**
     * Search visibility contributed by a ranking position (0..100): higher for
     * better positions, zero past the first hundred results.
     */
    private function visibility(int $position): int
    {
        return $position >= 1 && $position <= 100 ? (101 - $position) : 0;
    }

    /**
     * Our own organic visibility across tracked keywords.
     */
    public function ourVisibility(): int
    {
        return (int) Keyword::where('is_tracked', true)
            ->whereNotNull('current_position')
            ->get(['current_position'])
            ->sum(fn (Keyword $k) => $this->visibility((int) $k->current_position));
    }

    /**
     * Competitor visibility per domain from captured competitor rankings.
     *
     * @return array<string, int>
     */
    public function competitorVisibility(): array
    {
        $out = [];
        KeywordRanking::where('is_competitor', true)
            ->whereNotNull('competitor_domain')
            ->get(['competitor_domain', 'position'])
            ->each(function (KeywordRanking $r) use (&$out) {
                $domain = (string) $r->competitor_domain;
                $out[$domain] = ($out[$domain] ?? 0) + $this->visibility((int) $r->position);
            });

        return $out;
    }

    /**
     * Share of voice: our share of total search visibility vs all tracked
     * competitors, plus each competitor's share (percentages).
     *
     * @return array{our_visibility: int, our_share: float, competitors: list<array{domain: string, visibility: int, share: float}>}
     */
    public function shareOfVoice(): array
    {
        $ours = $this->ourVisibility();
        $competitors = $this->competitorVisibility();
        $total = $ours + array_sum($competitors);

        $share = fn (int $v) => $total > 0 ? round(($v / $total) * 100, 2) : 0.0;

        $competitorRows = [];
        foreach ($competitors as $domain => $visibility) {
            $competitorRows[] = ['domain' => $domain, 'visibility' => $visibility, 'share' => $share($visibility)];
        }
        usort($competitorRows, fn ($a, $b) => $b['visibility'] <=> $a['visibility']);

        return [
            'our_visibility' => $ours,
            'our_share' => $share($ours),
            'competitors' => $competitorRows,
        ];
    }

    /**
     * Keyword head-to-head (CINT-001): our current position vs each tracked
     * competitor's latest recorded position, per tracked keyword. A null means
     * "no recorded position" — never invented.
     *
     * @return list<array{keyword: string, our_position: int|null, competitors: array<string, int|null>, leading: bool|null}>
     */
    public function keywordHeadToHead(): array
    {
        $domains = Competitor::where('is_tracked', true)->whereNotNull('domain')->pluck('domain')->all();

        return Keyword::where('is_tracked', true)->orderBy('phrase')->get()
            ->map(function (Keyword $keyword) use ($domains) {
                $theirs = [];
                foreach ($domains as $domain) {
                    $latest = KeywordRanking::where('keyword_id', $keyword->id)
                        ->where('is_competitor', true)->where('competitor_domain', $domain)
                        ->orderByDesc('checked_at')->value('position');
                    $theirs[$domain] = $latest !== null ? (int) $latest : null;
                }

                $our = $keyword->current_position !== null ? (int) $keyword->current_position : null;
                $best = collect($theirs)->filter()->min();

                return [
                    'keyword' => $keyword->phrase,
                    'our_position' => $our,
                    'competitors' => $theirs,
                    // Leading = we rank and nobody recorded ranks better; null when unmeasurable.
                    'leading' => $our === null ? null : ($best === null ? true : $our <= $best),
                ];
            })->values()->all();
    }

    /**
     * Share of AI recommendations (CINT-011), from recorded checks: of every
     * check where we or a known competitor appeared in the answer, the share
     * where WE were the active recommendation.
     *
     * @return array{checks: int, contested: int, our_recommendations: int, share: float, competitor_appearances: array<string, int>}
     */
    public function aiRecommendationShare(): array
    {
        $checks = AiVisibilityCheck::query()->get(['mentioned', 'recommended', 'competitors']);

        $contested = 0;
        $ourRecommendations = 0;
        $appearances = [];

        foreach ($checks as $check) {
            $competitors = is_array($check->competitors) ? $check->competitors : [];
            foreach ($competitors as $name) {
                $appearances[(string) $name] = ($appearances[(string) $name] ?? 0) + 1;
            }

            if ($check->mentioned || $competitors !== []) {
                $contested++;
                if ($check->recommended) {
                    $ourRecommendations++;
                }
            }
        }

        arsort($appearances);

        return [
            'checks' => $checks->count(),
            'contested' => $contested,
            'our_recommendations' => $ourRecommendations,
            'share' => $contested > 0 ? round($ourRecommendations / $contested * 100, 1) : 0.0,
            'competitor_appearances' => $appearances,
        ];
    }
}
