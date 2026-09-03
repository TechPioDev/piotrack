<?php

namespace App\Services\Strategy;

use App\Models\BrandProfile;
use App\Models\Competitor;
use App\Models\CompetitorSnapshot;
use App\Models\Deal;
use App\Models\ScoringRule;
use App\Models\SeoLocation;
use App\Models\ServiceLine;
use App\Models\SitePage;
use App\Models\Vertical;

/**
 * Brand positioning computed from real records (BRAND-001/002/004/008..012),
 * the Strategy-module discipline: every claim traces to data the tenant can
 * see, and what the data cannot support is guarded, never invented. The
 * consulting conversation stays human; this is the evidence it runs on.
 */
class BrandPositioningService
{
    /**
     * BRAND-001: which discovery questions the brand profile actually answers.
     *
     * @return array{answered: int, total: int, items: list<array{key: string, label: string, ok: bool, detail: string}>}
     */
    public function discovery(): array
    {
        $brand = BrandProfile::first();
        $hasIcp = ScoringRule::where('name', 'like', 'ICP:%')->exists();

        $items = [
            $this->item('positioning', 'Positioning statement', ($brand->positioning_statement ?? '') !== '', 'Who you are for and why you win, in one statement.'),
            $this->item('usp', 'Unique selling proposition', ($brand->usp ?? '') !== '', 'The one claim competitors cannot copy.'),
            $this->item('differentiators', 'Differentiators', ($brand->differentiators ?? []) !== [], 'The concrete reasons buyers pick you.'),
            $this->item('narrative', 'Brand narrative', ($brand->narrative ?? '') !== '', 'The story behind the business.'),
            $this->item('tone', 'Tone of voice', ($brand->tone_of_voice ?? '') !== '', 'How the brand talks.'),
            $this->item('icp', 'Ideal customer profile', $hasIcp, 'Defined in setup — it scores leads live.'),
            $this->item('palette', 'Visual identity captured', ($brand->palette ?? []) !== [], 'Palette, typography and imagery direction on record.'),
        ];

        return [
            'answered' => count(array_filter($items, fn (array $i) => $i['ok'])),
            'total' => count($items),
            'items' => $items,
        ];
    }

    /**
     * BRAND-002: competitors' own public messaging — the page titles captured
     * by the content monitor ARE their claims. No snapshot, no invention.
     *
     * @return list<array{competitor: string, checked_at: string|null, titles: list<string>}>
     */
    public function competitorMessaging(): array
    {
        return Competitor::where('is_tracked', true)->orderBy('name')->get()
            ->map(function (Competitor $competitor) {
                $snapshot = CompetitorSnapshot::where('competitor_id', $competitor->id)->latest('id')->first();

                return [
                    'competitor' => $competitor->name,
                    'checked_at' => $snapshot?->created_at?->toIso8601String(),
                    'titles' => collect($snapshot->pages ?? [])
                        ->pluck('title')
                        ->filter(fn ($t) => $t !== '' && ! str_starts_with((string) $t, '('))
                        ->take(8)
                        ->values()
                        ->all(),
                ];
            })->all();
    }

    /**
     * BRAND-004: each stated differentiator, checked against reality — does it
     * appear on OUR published pages, and do competitors claim it too (then it
     * is a table stake, not a differentiator).
     *
     * @return list<array{differentiator: string, on_our_site: bool, claimed_by: list<string>}>
     */
    public function differentiators(): array
    {
        $stated = BrandProfile::first()->differentiators ?? [];
        if ($stated === []) {
            return [];
        }

        $ourCopy = mb_strtolower(SitePage::where('status', SitePage::STATUS_PUBLISHED)
            ->get(['title', 'meta_description', 'headline', 'subheadline'])
            ->map(fn (SitePage $p) => implode(' ', [$p->title, $p->meta_description, $p->headline, $p->subheadline]))
            ->implode(' '));

        $competitorTitles = Competitor::where('is_tracked', true)->get()
            ->mapWithKeys(function (Competitor $competitor) {
                $snapshot = CompetitorSnapshot::where('competitor_id', $competitor->id)->latest('id')->first();

                return [$competitor->name => mb_strtolower(collect($snapshot->pages ?? [])->pluck('title')->implode(' '))];
            });

        return collect($stated)->map(function ($differentiator) use ($ourCopy, $competitorTitles) {
            $needle = mb_strtolower(trim((string) $differentiator));

            return [
                'differentiator' => (string) $differentiator,
                'on_our_site' => $needle !== '' && str_contains($ourCopy, $needle),
                'claimed_by' => $needle === '' ? [] : $competitorTitles
                    ->filter(fn (string $titles) => str_contains($titles, $needle))
                    ->keys()->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * BRAND-008: the stated ICP against the observed win profile. Guarded when
     * there are no wins — alignment against nothing would be fiction.
     *
     * @return array{insufficient_data: bool, checks: list<array{attribute: string, stated: string, observed: string, aligned: bool}>}
     */
    public function icpAlignment(): array
    {
        $rules = ScoringRule::where('name', 'like', 'ICP:%')->get()->keyBy('attribute');
        $wonDeals = Deal::with('company:id,industry,size,region')->where('status', 'won')->get();

        if ($wonDeals->isEmpty() || $rules->isEmpty()) {
            return ['insufficient_data' => true, 'checks' => []];
        }

        $top = fn (string $field) => (string) $wonDeals
            ->groupBy(fn (Deal $d) => (string) ($d->company->{$field} ?? ''))
            ->map->count()->sortDesc()->keys()->first(fn ($k) => $k !== '');

        $checks = [];
        foreach (['company_industry' => 'industry', 'company_size' => 'size', 'company_region' => 'region'] as $attribute => $field) {
            $rule = $rules->get($attribute);
            if ($rule === null) {
                continue;
            }
            $observed = $top($field);
            $checks[] = [
                'attribute' => $field,
                'stated' => (string) $rule->value,
                'observed' => $observed !== '' ? $observed : '(no data on won companies)',
                'aligned' => $observed !== '' && mb_strtolower($observed) === mb_strtolower((string) $rule->value)
                    || ($observed !== '' && str_contains(mb_strtolower($observed), mb_strtolower((string) $rule->value))),
            ];
        }

        return ['insufficient_data' => false, 'checks' => $checks];
    }

    /**
     * BRAND-009..012: what the records evidence per positioning axis.
     *
     * @return array<string, array<string, mixed>>
     */
    public function positioningEvidence(): array
    {
        $wonDeals = Deal::where('status', 'won')->get();
        $verticals = Vertical::where('is_active', true)->get();
        $services = ServiceLine::where('is_active', true)->get();
        $locations = SeoLocation::where('is_active', true)->get();

        $pageCount = fn (string $column, $ids) => SitePage::where('status', SitePage::STATUS_PUBLISHED)
            ->whereIn($column, $ids)->distinct($column)->count($column);

        return [
            // BRAND-009: premium positioning is a number, not an adjective.
            'premium' => [
                'won_deals' => $wonDeals->count(),
                'avg_deal_value' => $wonDeals->isNotEmpty() ? (int) round($wonDeals->avg('value')) : null,
                'median_mrr' => $wonDeals->isNotEmpty() ? (int) $wonDeals->pluck('mrr')->map(fn ($m) => (int) $m)->median() : null,
            ],
            // BRAND-010: verticals you claim vs verticals you publish for.
            'vertical' => [
                'active' => $verticals->count(),
                'with_published_page' => $verticals->isEmpty() ? 0 : $pageCount('vertical_id', $verticals->pluck('id')),
            ],
            // BRAND-011: same for service lines.
            'service' => [
                'active' => $services->count(),
                'with_published_page' => $services->isEmpty() ? 0 : $pageCount('service_line_id', $services->pluck('id')),
            ],
            // BRAND-012: markets you serve vs markets you show up in.
            'geographic' => [
                'branches' => $locations->count(),
                'with_published_page' => $locations->isEmpty() ? 0 : $pageCount('seo_location_id', $locations->pluck('id')),
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, ok: bool, detail: string}
     */
    private function item(string $key, string $label, bool $ok, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'ok' => $ok, 'detail' => $detail];
    }
}
