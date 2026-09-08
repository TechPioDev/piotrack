<?php

namespace App\Services\Strategy;

use App\Models\BrandProfile;
use App\Models\Campaign;
use App\Models\ContentPiece;
use App\Models\SitePage;
use App\Models\Vertical;

/**
 * STRAT-016: messaging analysis — does what we publish actually carry the
 * messaging we decided on? Elements come from the brand profile (USP, value
 * proposition, differentiators) and the per-vertical messaging framework
 * (P39); assets are published content, published site pages and sent email
 * campaigns. Matching is a transparent significant-word heuristic, stated as
 * such in the UI — a presence check, not a claim of understanding.
 */
class MessagingAnalysisService
{
    /** An asset carries an element when at least half its significant words appear. */
    private const STOPWORDS = ['their', 'there', 'these', 'those', 'which', 'while', 'would', 'could', 'should', 'about', 'every', 'other', 'your'];

    private const MAX_WORDS = 8;

    /**
     * @return array{elements: list<array<string, mixed>>, assets_without_messaging: list<array{type: string, title: string}>, asset_counts: array<string, int>}
     */
    public function analysis(): array
    {
        $elements = $this->elements();

        $assets = array_merge(
            ContentPiece::whereNotNull('published_at')->get()
                ->map(fn (ContentPiece $p) => ['type' => 'content', 'title' => (string) $p->title, 'text' => mb_strtolower($p->title.' '.$p->excerpt.' '.$p->body)])->all(),
            SitePage::where('status', SitePage::STATUS_PUBLISHED)->get()
                ->map(fn (SitePage $p) => ['type' => 'page', 'title' => (string) $p->title, 'text' => mb_strtolower($p->title.' '.$p->meta_description.' '.$p->headline.' '.$p->subheadline)])->all(),
            Campaign::whereNotNull('sent_at')->get()
                ->map(fn (Campaign $c) => ['type' => 'campaign', 'title' => (string) $c->name, 'text' => mb_strtolower($c->subject.' '.$c->body_html.' '.$c->body_text)])->all(),
        );

        $carriedByAsset = array_fill(0, count($assets), false);
        $analysed = [];

        foreach ($elements as $element) {
            $words = $this->significantWords($element['text']);
            $carried = ['content' => 0, 'page' => 0, 'campaign' => 0];

            if ($words !== []) {
                foreach ($assets as $i => $asset) {
                    if ($this->carries($asset['text'], $words)) {
                        $carried[$asset['type']]++;
                        $carriedByAsset[$i] = true;
                    }
                }
            }

            $analysed[] = [
                'source' => $element['source'],
                'label' => $element['label'],
                'words' => $words,
                'carried_by' => $carried,
                'total' => array_sum($carried),
            ];
        }

        $orphans = [];
        if ($elements !== []) {
            foreach ($assets as $i => $asset) {
                if (! $carriedByAsset[$i]) {
                    $orphans[] = ['type' => $asset['type'], 'title' => $asset['title']];
                }
            }
        }

        $counts = ['content' => 0, 'page' => 0, 'campaign' => 0];
        foreach ($assets as $asset) {
            $counts[$asset['type']]++;
        }

        return [
            'elements' => $analysed,
            'assets_without_messaging' => array_slice($orphans, 0, 20),
            'asset_counts' => $counts,
        ];
    }

    /**
     * Every messaging element on record: brand-level plus the per-vertical
     * frameworks. Nothing is invented — no records, no elements.
     *
     * @return list<array{source: string, label: string, text: string}>
     */
    public function elements(): array
    {
        $elements = [];
        $brand = BrandProfile::first();

        foreach (['usp' => 'USP', 'value_proposition' => 'Value proposition'] as $field => $label) {
            $text = trim((string) ($brand?->{$field} ?? ''));
            if ($text !== '') {
                $elements[] = ['source' => 'brand', 'label' => $label, 'text' => $text];
            }
        }
        foreach ($brand->differentiators ?? [] as $differentiator) {
            $text = trim((string) $differentiator);
            if ($text !== '') {
                $elements[] = ['source' => 'brand', 'label' => __('Differentiator: :d', ['d' => $text]), 'text' => $text];
            }
        }

        foreach (Vertical::where('is_active', true)->whereNotNull('messaging')->get() as $vertical) {
            $messaging = is_array($vertical->messaging) ? $vertical->messaging : [];
            $text = trim((string) ($messaging['value_proposition'] ?? ''));
            if ($text !== '') {
                $elements[] = ['source' => $vertical->name, 'label' => __(':v value proposition', ['v' => $vertical->name]), 'text' => $text];
            }
        }

        return $elements;
    }

    /**
     * @return list<string>
     */
    private function significantWords(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', mb_strtolower($text)) ?: [];
        $significant = array_values(array_unique(array_filter(
            $words,
            fn (string $w) => mb_strlen($w) >= 5 && ! in_array($w, self::STOPWORDS, true)
        )));

        return array_slice($significant, 0, self::MAX_WORDS);
    }

    /**
     * @param  list<string>  $words
     */
    private function carries(string $haystack, array $words): bool
    {
        $found = 0;
        foreach ($words as $word) {
            if (str_contains($haystack, $word)) {
                $found++;
            }
        }

        return $found * 2 >= count($words);
    }
}
