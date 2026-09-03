<?php

namespace App\Services\Ai;

use App\Support\UrlGuard;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads a prospect's own public website for AI research grounding
 * (AISA-005/007) — the in-house crawler discipline (no vendor, SSRF-guarded,
 * same as the competitor monitor): what their site says about them is real
 * external evidence the model may cite with provenance, unlike anything it
 * would otherwise have to invent.
 */
class ProspectSiteReader
{
    /** Keep the excerpt small: grounding, not ingestion. */
    private const EXCERPT_CHARS = 1200;

    public function __construct(private UrlGuard $urls) {}

    /**
     * Fetch and distill one page of the prospect's site. Null on ANY failure —
     * research must degrade to CRM-only honestly, never block or fabricate.
     *
     * @return array{url: string, title: string, description: string, headings: list<string>, excerpt: string}|null
     */
    public function read(?string $url): ?array
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = str_starts_with($url, 'http') ? $url : 'https://'.$url;

        if (! $this->urls->isFetchable($url)) {
            return null;
        }

        try {
            $response = Http::timeout(10)->withoutRedirecting()->get($url);
            if (! $response->successful()) {
                return null;
            }

            return $this->distill($url, $response->body());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{url: string, title: string, description: string, headings: list<string>, excerpt: string}|null
     */
    private function distill(string $url, string $html): ?array
    {
        if (trim($html) === '') {
            return null;
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);

        $title = trim((string) $xpath->evaluate('string(//title)'));

        $description = '';
        foreach ($xpath->query('//meta[@name="description"]') ?: [] as $meta) {
            if ($meta instanceof \DOMElement) {
                $description = trim($meta->getAttribute('content'));
            }
        }

        $headings = [];
        foreach ($xpath->query('//h1 | //h2') ?: [] as $node) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
            if ($text !== '' && count($headings) < 8) {
                $headings[] = $text;
            }
        }

        // Visible text, scripts and styles stripped, flattened to one excerpt.
        foreach ($xpath->query('//script | //style | //noscript') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }
        $body = trim(preg_replace('/\s+/', ' ', (string) $xpath->evaluate('string(//body)')) ?? '');

        return [
            'url' => $url,
            'title' => $title,
            'description' => $description,
            'headings' => $headings,
            'excerpt' => mb_substr($body, 0, self::EXCERPT_CHARS),
        ];
    }
}
