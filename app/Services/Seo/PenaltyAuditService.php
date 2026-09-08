<?php

namespace App\Services\Seo;

use App\Models\ContentPiece;
use App\Models\Keyword;
use App\Models\KeywordRanking;
use App\Models\SitePage;
use App\Seo\Contracts\SearchConsoleProvider;

/**
 * TSEO-024/025: penalty auditing and recovery. Five signals, each citing its
 * numbers and its source; "ok" rows stay ok — a finding exists only when a
 * threshold is actually crossed. The recovery plan is derived exclusively
 * from triggered findings and names the in-platform tool for each step; a
 * clean audit yields an explicit "no recovery needed", never invented work.
 */
class PenaltyAuditService
{
    /** A ranking fall of at least this many positions counts as a drop. */
    public const DROP_POSITIONS = 10;

    /** Share of measured keywords dropping together that suggests an algorithmic hit. */
    public const ALGO_SHARE = 0.5;

    /** Toxic share of the link profile that flags the profile itself. */
    public const TOXIC_SHARE = 0.2;

    public const THIN_WORDS = 300;

    public function __construct(
        private SearchConsoleProvider $searchConsole,
        private BacklinkAuditService $backlinks,
    ) {}

    /**
     * @return array{findings: list<array{key: string, label: string, status: string, evidence: string, source: string}>, risk: int}
     */
    public function audit(): array
    {
        $findings = [
            $this->manualActionFinding(),
            $this->toxicLinkFinding(),
            $this->rankingDropFinding(),
            $this->thinContentFinding(),
            $this->duplicateFinding(),
        ];

        return [
            'findings' => $findings,
            'risk' => count(array_filter($findings, fn (array $f) => $f['status'] !== 'ok')),
        ];
    }

    /**
     * Recovery steps derived ONLY from triggered findings.
     *
     * @return array{needed: bool, steps: list<array{step: string, reason: string, tool: string}>}
     */
    public function recoveryPlan(): array
    {
        $steps = [];

        foreach ($this->audit()['findings'] as $finding) {
            if ($finding['status'] === 'ok') {
                continue;
            }

            $steps[] = match ($finding['key']) {
                'manual_actions' => [
                    'step' => __('Fix the flagged pattern, then request reconsideration in Search Console.'),
                    'reason' => $finding['evidence'],
                    'tool' => __('Search Console reconsideration request'),
                ],
                'toxic_links' => [
                    'step' => __('Review the flagged domains and upload the generated disavow file.'),
                    'reason' => $finding['evidence'],
                    'tool' => __('Links page → disavow.txt export'),
                ],
                'ranking_drops' => [
                    'step' => __('Compare the dropped keywords against recent content and link changes before touching anything.'),
                    'reason' => $finding['evidence'],
                    'tool' => __('Keyword rankings history'),
                ],
                'thin_content' => [
                    'step' => __('Expand or consolidate the thin pieces; noindex what cannot be improved.'),
                    'reason' => $finding['evidence'],
                    'tool' => __('Content editor'),
                ],
                'duplicates' => [
                    'step' => __('Differentiate the duplicated titles and descriptions.'),
                    'reason' => $finding['evidence'],
                    'tool' => __('Website page editor'),
                ],
                default => [
                    'step' => __('Review the finding.'),
                    'reason' => $finding['evidence'],
                    'tool' => __('Manual review'),
                ],
            };
        }

        return ['needed' => $steps !== [], 'steps' => $steps];
    }

    /** @return array{key: string, label: string, status: string, evidence: string, source: string} */
    private function manualActionFinding(): array
    {
        $domain = $this->backlinks->ownDomain();
        if ($domain === null) {
            return $this->finding('manual_actions', __('Manual actions'), 'ok', __('No website domain on the brand profile yet.'), $this->searchConsole->name());
        }

        $actions = $this->searchConsole->manualActions($domain);
        if ($actions === []) {
            return $this->finding('manual_actions', __('Manual actions'), 'ok', __('No manual actions reported.'), $this->searchConsole->name());
        }

        return $this->finding(
            'manual_actions',
            __('Manual actions'),
            'risk',
            __(':count manual action(s): :types', ['count' => count($actions), 'types' => implode('; ', array_column($actions, 'type'))]),
            $this->searchConsole->name(),
        );
    }

    /** @return array{key: string, label: string, status: string, evidence: string, source: string} */
    private function toxicLinkFinding(): array
    {
        $audit = $this->backlinks->audit();
        $total = count($audit['links']);

        if ($total === 0) {
            return $this->finding('toxic_links', __('Link profile toxicity'), 'ok', __('No link-index rows to assess.'), $audit['provider']);
        }

        $share = $audit['toxic'] / $total;
        $pct = round($share * 100, 1);

        return $share >= self::TOXIC_SHARE
            ? $this->finding('toxic_links', __('Link profile toxicity'), 'warning', __(':toxic of :total links flagged toxic (:pct%).', ['toxic' => $audit['toxic'], 'total' => $total, 'pct' => $pct]), $audit['provider'])
            : $this->finding('toxic_links', __('Link profile toxicity'), 'ok', __(':toxic of :total links flagged (:pct%), below the :limit% threshold.', ['toxic' => $audit['toxic'], 'total' => $total, 'pct' => $pct, 'limit' => self::TOXIC_SHARE * 100]), $audit['provider']);
    }

    /**
     * Simultaneous drops across the tenant's own REAL ranking history — the
     * signature of an algorithmic impact, computed from records.
     *
     * @return array{key: string, label: string, status: string, evidence: string, source: string}
     */
    private function rankingDropFinding(): array
    {
        $measured = 0;
        $dropped = 0;

        foreach (Keyword::where('is_tracked', true)->get() as $keyword) {
            $positions = KeywordRanking::where('keyword_id', $keyword->id)
                ->where('is_competitor', false)
                ->whereNotNull('position')
                ->orderBy('checked_at')
                ->pluck('position')
                ->map(fn ($p) => (int) $p);

            if ($positions->count() < 2) {
                continue;
            }

            $measured++;
            $latest = $positions->last();
            $bestBefore = $positions->slice(0, -1)->min();
            if ($latest - $bestBefore >= self::DROP_POSITIONS) {
                $dropped++;
            }
        }

        if ($measured === 0) {
            return $this->finding('ranking_drops', __('Ranking drops'), 'ok', __('Not enough ranking history to assess.'), 'rankings');
        }

        return $dropped >= max(1, (int) ceil($measured * self::ALGO_SHARE))
            ? $this->finding('ranking_drops', __('Ranking drops'), 'risk', __(':dropped of :measured measured keywords fell :n+ positions from their best - the pattern of an algorithmic impact.', ['dropped' => $dropped, 'measured' => $measured, 'n' => self::DROP_POSITIONS]), 'rankings')
            : $this->finding('ranking_drops', __('Ranking drops'), 'ok', __(':dropped of :measured measured keywords dropped materially.', ['dropped' => $dropped, 'measured' => $measured]), 'rankings');
    }

    /** @return array{key: string, label: string, status: string, evidence: string, source: string} */
    private function thinContentFinding(): array
    {
        $published = ContentPiece::whereNotNull('published_at')->get();
        if ($published->isEmpty()) {
            return $this->finding('thin_content', __('Thin content'), 'ok', __('No published content to assess.'), 'content');
        }

        $thin = $published->filter(fn (ContentPiece $p) => str_word_count(strip_tags((string) $p->body)) < self::THIN_WORDS);

        return $thin->isNotEmpty()
            ? $this->finding('thin_content', __('Thin content'), 'warning', __(':thin of :total published pieces under :words words: :titles', ['thin' => $thin->count(), 'total' => $published->count(), 'words' => self::THIN_WORDS, 'titles' => $thin->take(3)->pluck('title')->implode(', ')]), 'content')
            : $this->finding('thin_content', __('Thin content'), 'ok', __('All :total published pieces meet the :words-word floor.', ['total' => $published->count(), 'words' => self::THIN_WORDS]), 'content');
    }

    /** @return array{key: string, label: string, status: string, evidence: string, source: string} */
    private function duplicateFinding(): array
    {
        $pages = SitePage::where('status', SitePage::STATUS_PUBLISHED)->get();
        if ($pages->count() < 2) {
            return $this->finding('duplicates', __('Duplicate titles/descriptions'), 'ok', __('Fewer than two published pages.'), 'pages');
        }

        $duplicateTitles = $pages->groupBy(fn (SitePage $p) => mb_strtolower(trim((string) $p->title)))
            ->filter(fn ($group, $key) => $key !== '' && $group->count() > 1);
        $duplicateDescriptions = $pages->groupBy(fn (SitePage $p) => mb_strtolower(trim((string) $p->meta_description)))
            ->filter(fn ($group, $key) => $key !== '' && $group->count() > 1);

        if ($duplicateTitles->isEmpty() && $duplicateDescriptions->isEmpty()) {
            return $this->finding('duplicates', __('Duplicate titles/descriptions'), 'ok', __('All :count published pages have distinct titles and descriptions.', ['count' => $pages->count()]), 'pages');
        }

        return $this->finding(
            'duplicates',
            __('Duplicate titles/descriptions'),
            'warning',
            __(':titles duplicated title(s), :descriptions duplicated description(s) across :count published pages.', ['titles' => $duplicateTitles->count(), 'descriptions' => $duplicateDescriptions->count(), 'count' => $pages->count()]),
            'pages',
        );
    }

    /** @return array{key: string, label: string, status: string, evidence: string, source: string} */
    private function finding(string $key, string $label, string $status, string $evidence, string $source): array
    {
        return compact('key', 'label', 'status', 'evidence', 'source');
    }
}
