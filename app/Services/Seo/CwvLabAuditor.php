<?php

namespace App\Services\Seo;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * TSEO-019: Core Web Vitals LAB analysis — a pure function of (html, url)
 * in the TechnicalSeoAuditor mould. Every check names the metric it moves
 * (LCP / CLS / INP) and the fix. These are lab factors and the UI says so;
 * field metrics come from the WebVitalsProvider seam.
 */
class CwvLabAuditor
{
    /** Bytes of HTML above which the document itself slows first paint. */
    public const HTML_BUDGET_BYTES = 100 * 1024;

    /** Script tags beyond this suggest main-thread contention. */
    public const SCRIPT_BUDGET = 15;

    /**
     * @return array{checks: list<array{key: string, label: string, metric: string, status: string, detail: string, fix: string}>, issues: int, by_metric: array<string, int>}
     */
    public function analyze(string $html, string $url): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);

        $checks = [
            $this->htmlSizeCheck($html),
            $this->renderBlockingCssCheck($xpath),
            $this->syncScriptCheck($xpath),
            $this->heroImageCheck($xpath),
            $this->fontDisplayCheck($xpath),
            $this->unsizedImageCheck($xpath),
            $this->unsizedIframeCheck($xpath),
            $this->scriptWeightCheck($xpath),
        ];

        $byMetric = ['LCP' => 0, 'CLS' => 0, 'INP' => 0];
        foreach ($checks as $check) {
            if ($check['status'] !== 'pass') {
                $byMetric[$check['metric']]++;
            }
        }

        return [
            'checks' => $checks,
            'issues' => array_sum($byMetric),
            'by_metric' => $byMetric,
        ];
    }

    /** @return array{key: string, label: string, metric: string, status: string, detail: string, fix: string} */
    private function htmlSizeCheck(string $html): array
    {
        $bytes = strlen($html);
        $kb = (int) round($bytes / 1024);

        return $bytes > self::HTML_BUDGET_BYTES
            ? $this->result('html_size', 'Document size', 'LCP', 'warn', "HTML is {$kb} KB (budget 100 KB).", 'Trim inline markup and defer non-critical content.')
            : $this->result('html_size', 'Document size', 'LCP', 'pass', "HTML is {$kb} KB.", '');
    }

    /** @return array{key: string, label: string, metric: string, status: string, detail: string, fix: string} */
    private function renderBlockingCssCheck(DOMXPath $xpath): array
    {
        $blocking = 0;
        foreach ($this->elements($xpath, '//head//link[@rel="stylesheet"]') as $link) {
            $media = mb_strtolower($link->getAttribute('media'));
            if ($media === '' || $media === 'all' || $media === 'screen') {
                $blocking++;
            }
        }

        return $blocking > 2
            ? $this->result('blocking_css', 'Render-blocking CSS', 'LCP', 'warn', "{$blocking} render-blocking stylesheets.", 'Inline critical CSS and load the rest with media/print-swap or preload.')
            : $this->result('blocking_css', 'Render-blocking CSS', 'LCP', 'pass', "{$blocking} blocking stylesheet(s).", '');
    }

    /** @return array{key: string, label: string, metric: string, status: string, detail: string, fix: string} */
    private function syncScriptCheck(DOMXPath $xpath): array
    {
        $sync = 0;
        foreach ($this->elements($xpath, '//head//script[@src]') as $script) {
            if (! $script->hasAttribute('defer') && ! $script->hasAttribute('async') && $script->getAttribute('type') !== 'module') {
                $sync++;
            }
        }

        return $sync > 0
            ? $this->result('sync_scripts', 'Blocking head scripts', 'LCP', 'fail', "{$sync} synchronous script(s) in <head>.", 'Add defer/async or move scripts before </body>.')
            : $this->result('sync_scripts', 'Blocking head scripts', 'LCP', 'pass', 'No synchronous head scripts.', '');
    }

    /** @return array{key: string, label: string, metric: string, status: string, detail: string, fix: string} */
    private function heroImageCheck(DOMXPath $xpath): array
    {
        $images = $this->elements($xpath, '//img');
        $first = $images[0] ?? null;

        if ($first === null) {
            return $this->result('hero_image', 'Above-the-fold image', 'LCP', 'pass', 'No images on the page.', '');
        }
        if (mb_strtolower($first->getAttribute('loading')) === 'lazy') {
            return $this->result('hero_image', 'Above-the-fold image', 'LCP', 'warn', 'The first image is lazy-loaded.', 'Remove loading="lazy" from the likely LCP image (or add fetchpriority="high").');
        }

        return $this->result('hero_image', 'Above-the-fold image', 'LCP', 'pass', 'First image loads eagerly.', '');
    }

    /** @return array{key: string, label: string, metric: string, status: string, detail: string, fix: string} */
    private function fontDisplayCheck(DOMXPath $xpath): array
    {
        $bad = 0;
        foreach ($this->elements($xpath, '//link[@rel="stylesheet"]') as $link) {
            $href = $link->getAttribute('href');
            if (str_contains($href, 'fonts.googleapis.com') && ! str_contains($href, 'display=swap')) {
                $bad++;
            }
        }

        return $bad > 0
            ? $this->result('font_display', 'Web font loading', 'LCP', 'warn', "{$bad} font stylesheet(s) without display=swap.", 'Append &display=swap so text renders before fonts arrive.')
            : $this->result('font_display', 'Web font loading', 'LCP', 'pass', 'Font loading is non-blocking.', '');
    }

    /** @return array{key: string, label: string, metric: string, status: string, detail: string, fix: string} */
    private function unsizedImageCheck(DOMXPath $xpath): array
    {
        $images = $this->elements($xpath, '//img');
        if ($images === []) {
            return $this->result('unsized_images', 'Image dimensions', 'CLS', 'pass', 'No images to check.', '');
        }

        $unsized = 0;
        foreach ($images as $img) {
            if ($img->getAttribute('width') === '' || $img->getAttribute('height') === '') {
                $unsized++;
            }
        }
        $total = count($images);

        return $unsized > 0
            ? $this->result('unsized_images', 'Image dimensions', 'CLS', 'warn', "{$unsized} of {$total} images without width/height.", 'Set width and height attributes so the browser reserves the space.')
            : $this->result('unsized_images', 'Image dimensions', 'CLS', 'pass', "All {$total} images are sized.", '');
    }

    /** @return array{key: string, label: string, metric: string, status: string, detail: string, fix: string} */
    private function unsizedIframeCheck(DOMXPath $xpath): array
    {
        $unsized = 0;
        foreach ($this->elements($xpath, '//iframe') as $iframe) {
            if ($iframe->getAttribute('width') === '' || $iframe->getAttribute('height') === '') {
                $unsized++;
            }
        }

        return $unsized > 0
            ? $this->result('unsized_iframes', 'Embed dimensions', 'CLS', 'warn', "{$unsized} iframe(s) without dimensions.", 'Give embeds explicit width/height or an aspect-ratio box.')
            : $this->result('unsized_iframes', 'Embed dimensions', 'CLS', 'pass', 'All embeds are sized.', '');
    }

    /** @return array{key: string, label: string, metric: string, status: string, detail: string, fix: string} */
    private function scriptWeightCheck(DOMXPath $xpath): array
    {
        $count = count($this->elements($xpath, '//script'));

        return $count > self::SCRIPT_BUDGET
            ? $this->result('script_weight', 'Script count', 'INP', 'warn', "{$count} script tags (budget ".self::SCRIPT_BUDGET.').', 'Bundle, remove unused third-party tags, and lazy-load what the first interaction does not need.')
            : $this->result('script_weight', 'Script count', 'INP', 'pass', "{$count} script tag(s).", '');
    }

    /** @return array{key: string, label: string, metric: string, status: string, detail: string, fix: string} */
    private function result(string $key, string $label, string $metric, string $status, string $detail, string $fix): array
    {
        return compact('key', 'label', 'metric', 'status', 'detail', 'fix');
    }

    /**
     * @return list<DOMElement>
     */
    private function elements(DOMXPath $xpath, string $expr): array
    {
        $nodes = $xpath->query($expr);
        if ($nodes === false) {
            return [];
        }

        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }
}
