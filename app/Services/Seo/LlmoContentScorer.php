<?php

namespace App\Services\Seo;

use DOMDocument;
use DOMXPath;

/**
 * LLM-optimization content scoring (LLMO-012/013/014): the six machine-
 * readability factors from ContentReadinessScorer plus three factors specific
 * to being quoted by answer engines — citation-friendliness, concise
 * definitions, and fact density. All deterministic DOM/regex heuristics: the
 * scorer measures what is in the HTML and never invents anything.
 */
class LlmoContentScorer
{
    /** Numeric facts (percentages, currency, counts, years) before the fact-rich factor passes. */
    public const FACT_THRESHOLD = 5;

    public function __construct(private ContentReadinessScorer $readiness) {}

    /**
     * @return array{score: int, factors: list<array{key: string, label: string, ok: bool, detail: string}>}
     */
    public function score(string $html): array
    {
        $base = $this->readiness->score($html);

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);

        $bodyNodes = $xpath->query('//body');
        $text = $bodyNodes !== false && $bodyNodes->item(0) !== null
            ? trim($bodyNodes->item(0)->textContent)
            : '';

        $factors = array_merge($base['factors'], [
            $this->citations($xpath),
            $this->definitions($xpath),
            $this->facts($text),
        ]);

        $passed = count(array_filter($factors, fn (array $f) => $f['ok']));

        return ['score' => (int) round($passed / count($factors) * 100), 'factors' => $factors];
    }

    /**
     * LLMO-012: content that cites sources is content an engine can trust and
     * quote — outbound links, cite elements or attributed quotes.
     *
     * @return array{key: string, label: string, ok: bool, detail: string}
     */
    private function citations(DOMXPath $xpath): array
    {
        $links = $xpath->query('//a[starts-with(@href, "http")]');
        $cites = $xpath->query('//cite | //blockquote');
        $count = ($links !== false ? $links->length : 0) + ($cites !== false ? $cites->length : 0);
        $ok = $count >= 2;

        return [
            'key' => 'citations', 'label' => 'Citation-friendly', 'ok' => $ok,
            'detail' => $ok
                ? "{$count} outbound links/citations back up the claims."
                : 'No cited sources — link the statistics and claims to where they come from.',
        ];
    }

    /**
     * LLMO-013: a concise "X is …" definition near a heading is exactly the
     * sentence an answer engine lifts. Detected as a <dfn> element or an
     * opening paragraph in definitional form.
     *
     * @return array{key: string, label: string, ok: bool, detail: string}
     */
    private function definitions(DOMXPath $xpath): array
    {
        $dfn = $xpath->query('//dfn');
        $found = $dfn !== false && $dfn->length > 0;

        if (! $found) {
            $paragraphs = $xpath->query('//p');
            foreach ($paragraphs !== false ? iterator_to_array($paragraphs) : [] as $p) {
                $opening = mb_substr(trim($p->textContent), 0, 160);
                if (preg_match('/^[A-Z][^.!?]{2,90}\s(is|are|means|refers to)\s/u', $opening) === 1) {
                    $found = true;
                    break;
                }
            }
        }

        return [
            'key' => 'definitions', 'label' => 'Concise definitions', 'ok' => $found,
            'detail' => $found
                ? 'Liftable "X is …" definition present.'
                : 'Open with a one-sentence definition of the topic — the sentence engines quote.',
        ];
    }

    /**
     * LLMO-014: fact-rich content carries verifiable figures — percentages,
     * currency, counts, years.
     *
     * @return array{key: string, label: string, ok: bool, detail: string}
     */
    private function facts(string $text): array
    {
        $count = preg_match_all('/\d+(?:[.,]\d+)?\s*%|[$€£]\s?\d[\d,.]*|\b\d[\d,.]*\b/u', $text);
        $count = $count === false ? 0 : $count;
        $ok = $count >= self::FACT_THRESHOLD;

        return [
            'key' => 'facts', 'label' => 'Fact-rich', 'ok' => $ok,
            'detail' => $ok
                ? "{$count} verifiable figures in the copy."
                : "Only {$count} figures — add concrete numbers (uptime %, response times, counts) engines can verify and quote.",
        ];
    }
}
