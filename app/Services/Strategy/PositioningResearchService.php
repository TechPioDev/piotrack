<?php

namespace App\Services\Strategy;

use App\Services\Analytics\CompetitiveService;

/**
 * STRAT-015: competitive positioning research composed from what the platform
 * has already measured — share of voice, keyword head-to-head, AI answer
 * share (CINT), verified differentiators and recorded competitor messaging
 * (BRAND/P22). Every angle cites the numbers behind it and is marked lead or
 * gap; the positioning prose a strategist writes from these stays human.
 */
class PositioningResearchService
{
    public function __construct(
        private CompetitiveService $competitive,
        private BrandPositioningService $positioning,
    ) {}

    /**
     * @return array{angles: list<array{angle: string, evidence: string, strength: string}>, inputs: array<string, int>}
     */
    public function research(): array
    {
        $angles = [];

        $headToHead = $this->competitive->keywordHeadToHead();
        $measured = array_values(array_filter($headToHead, fn (array $row) => $row['leading'] !== null));
        $leading = count(array_filter($measured, fn (array $row) => $row['leading'] === true));
        if ($measured !== []) {
            $angles[] = [
                'angle' => __('Search visibility'),
                'evidence' => __('Leading on :leading of :measured measured keywords against tracked competitors.', ['leading' => $leading, 'measured' => count($measured)]),
                'strength' => $leading * 2 >= count($measured) ? 'lead' : 'gap',
            ];
        }

        $sov = $this->competitive->shareOfVoice();
        $topCompetitor = $sov['competitors'][0] ?? null;
        if ($topCompetitor !== null || $sov['our_visibility'] > 0) {
            $angles[] = [
                'angle' => __('Share of voice'),
                'evidence' => $topCompetitor !== null
                    ? __('Our share :ours% vs :name at :theirs%.', ['ours' => $sov['our_share'], 'name' => $topCompetitor['domain'], 'theirs' => $topCompetitor['share']])
                    : __('Our share :ours% with no competitor visibility recorded yet.', ['ours' => $sov['our_share']]),
                'strength' => $topCompetitor === null || $sov['our_share'] >= $topCompetitor['share'] ? 'lead' : 'gap',
            ];
        }

        $ai = $this->competitive->aiRecommendationShare();
        if ($ai['contested'] > 0) {
            $angles[] = [
                'angle' => __('AI answer presence'),
                'evidence' => __('Recommended in :ours of :contested contested AI answers (:share%).', ['ours' => $ai['our_recommendations'], 'contested' => $ai['contested'], 'share' => $ai['share']]),
                'strength' => $ai['share'] >= 50 ? 'lead' : 'gap',
            ];
        }

        foreach ($this->positioning->differentiators() as $check) {
            if ($check['on_our_site'] && $check['claimed_by'] === []) {
                $angles[] = [
                    'angle' => __('Ownable differentiator: :d', ['d' => $check['differentiator']]),
                    'evidence' => __('Live on our published pages and claimed by no tracked competitor.'),
                    'strength' => 'lead',
                ];
            } elseif ($check['claimed_by'] !== []) {
                $angles[] = [
                    'angle' => __('Table stake: :d', ['d' => $check['differentiator']]),
                    'evidence' => __('Also claimed by :names — it cannot carry the positioning alone.', ['names' => implode(', ', $check['claimed_by'])]),
                    'strength' => 'gap',
                ];
            }
        }

        $messaging = $this->positioning->competitorMessaging();
        $snapshotted = count(array_filter($messaging, fn (array $row) => $row['titles'] !== []));

        return [
            'angles' => $angles,
            'inputs' => [
                'keywords_measured' => count($measured),
                'ai_checks' => $ai['checks'],
                'competitors_snapshotted' => $snapshotted,
            ],
        ];
    }
}
