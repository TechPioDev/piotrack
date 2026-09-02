<?php

namespace App\Services\Seo;

use App\Models\SiteCrawl;
use App\Support\AuditLogger;
use App\Support\UrlGuard;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Bounded technical-SEO site crawler (TSEO-002/003/004/010/013/014/016/017/
 * 018/021/022). Same philosophy as the single-URL auditor: PHP DOM + Laravel
 * Http, no crawler SaaS (ADR-0005) — extended into a same-host BFS crawl.
 *
 * robots.txt and sitemap.xml are fetched first (sitemap URLs join the queue,
 * which is how orphan pages become discoverable); every URL passes the SSRF
 * guard; redirects are recorded rather than blindly followed, and only
 * same-host targets are ever enqueued. The report claims only verified facts:
 * a sitemap URL the page budget never reached is "not crawled", never
 * "broken".
 */
class SiteCrawler
{
    /** Page budget: enough for an MSP marketing site, bounded for our server. */
    public const MAX_PAGES = 20;

    /** Click depth beyond which a page is effectively buried (TSEO-022). */
    public const DEPTH_LIMIT = 3;

    /** Heuristic HTML weight ceiling before a page is flagged (TSEO-021). */
    public const HTML_BYTES_LIMIT = 150_000;

    public function __construct(
        private UrlGuard $urls,
        private AuditLogger $audit,
    ) {}

    /**
     * Crawl, analyse, persist. The start URL is SSRF-asserted up front (a
     * refusal throws to the caller); every discovered URL is checked the same
     * way before its fetch.
     */
    public function crawl(string $startUrl, int $maxPages = self::MAX_PAGES): SiteCrawl
    {
        $this->urls->assertFetchable($startUrl);

        $origin = $this->origin($startUrl);
        $robots = $this->fetchRobots($origin);
        $sitemapUrls = $this->fetchSitemap($origin);

        /** @var array<string, array<string, mixed>> $pages */
        $pages = [];
        /** @var array<string, list<string>> $inlinks target => source urls */
        $inlinks = [];
        /** @var array<string, string> $redirects from => to */
        $redirects = [];

        $queue = [[$this->normalize($startUrl, $startUrl), 0]];
        foreach ($sitemapUrls as $u) {
            $queue[] = [$u, 1];
        }

        while ($queue !== [] && count($pages) < $maxPages) {
            [$url, $depth] = array_shift($queue);
            if (isset($pages[$url]) || $this->origin($url) !== $origin) {
                continue;
            }

            $pages[$url] = $this->fetchPage($url, $depth);

            $to = $pages[$url]['redirect_to'];
            if (is_string($to) && $to !== '') {
                $redirects[$url] = $to;
                if ($this->origin($to) === $origin && ! isset($pages[$to])) {
                    $queue[] = [$to, $depth];
                }

                continue;
            }

            foreach ($pages[$url]['links'] as $link) {
                $inlinks[$link['url']][] = $url;
                if (! isset($pages[$link['url']])) {
                    $queue[] = [$link['url'], $depth + 1];
                }
            }
        }

        $report = $this->report($pages, $inlinks, $redirects, $robots, $sitemapUrls, $startUrl);

        $crawl = SiteCrawl::create([
            'start_url' => $startUrl,
            'pages_crawled' => count($pages),
            'issues_count' => $report['issues_count'],
            'report' => $report,
        ]);

        $this->audit->log('seo.crawl.run', context: ['url' => $startUrl, 'pages' => count($pages), 'issues' => $report['issues_count']], resourceType: 'site_crawl', resourceId: (string) $crawl->id, organizationId: $crawl->organization_id);

        return $crawl;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPage(string $url, int $depth): array
    {
        $page = [
            'url' => $url, 'depth' => $depth, 'status' => null, 'redirect_to' => null,
            'title' => '', 'description' => '', 'canonical' => '', 'noindex' => false,
            'content_hash' => '', 'bytes' => 0, 'scripts' => 0, 'images' => 0,
            'links' => [],
        ];

        try {
            $this->urls->assertFetchable($url);
            $response = Http::timeout(10)->withoutRedirecting()->get($url);
            $page['status'] = $response->status();

            if ($response->redirect()) {
                $location = (string) $response->header('Location');
                $page['redirect_to'] = $location !== '' ? $this->normalize($location, $url) : null;

                return $page;
            }

            if ($response->successful()) {
                $page = $this->parse($response->body(), $url) + $page;
            }
        } catch (Throwable) {
            // status stays null: unfetchable — reported, never guessed at.
        }

        return $page;
    }

    /**
     * @return array<string, mixed>
     */
    private function parse(string $html, string $url): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);

        $first = function (string $expr) use ($xpath): ?\DOMNode {
            $nodes = $xpath->query($expr);

            return $nodes !== false ? $nodes->item(0) : null;
        };
        $count = function (string $expr) use ($xpath): int {
            $nodes = $xpath->query($expr);

            return $nodes !== false ? $nodes->length : 0;
        };
        $text = function (string $expr) use ($first): string {
            $node = $first($expr);

            return $node !== null ? trim($node->textContent) : '';
        };
        $attr = function (string $expr) use ($first): string {
            $node = $first($expr);

            return $node !== null ? trim($node->nodeValue ?? '') : '';
        };

        $links = [];
        foreach (iterator_to_array($xpath->query('//a[@href]') ?: []) as $a) {
            if (! $a instanceof \DOMElement) {
                continue;
            }
            $href = trim($a->getAttribute('href'));
            $resolved = $this->resolveLink($href, $url);
            if ($resolved !== null && $this->origin($resolved) === $this->origin($url) && $resolved !== $url) {
                $links[] = ['url' => $resolved, 'anchor' => mb_substr(trim($a->textContent), 0, 80)];
            }
        }

        $bodyText = preg_replace('/\s+/u', ' ', $text('//body')) ?? '';

        return [
            'title' => $text('//title'),
            'description' => $attr('//meta[@name="description"]/@content'),
            'canonical' => $attr('//link[@rel="canonical"]/@href'),
            'noindex' => str_contains(mb_strtolower($attr('//meta[@name="robots"]/@content')), 'noindex'),
            'content_hash' => md5($bodyText),
            'bytes' => strlen($html),
            'scripts' => $count('//script[@src]'),
            'images' => $count('//img'),
            'links' => array_values(array_unique($links, SORT_REGULAR)),
        ];
    }

    /**
     * Assemble the findings. Every item is a plain sentence naming its page,
     * so the report reads as a work list rather than a data dump.
     *
     * @param  array<string, array<string, mixed>>  $pages
     * @param  array<string, list<string>>  $inlinks
     * @param  array<string, string>  $redirects
     * @param  array{found: bool, disallows: list<string>, sitemaps: list<string>}  $robots
     * @param  list<string>  $sitemapUrls
     * @return array<string, mixed>
     */
    private function report(array $pages, array $inlinks, array $redirects, array $robots, array $sitemapUrls, string $startUrl): array
    {
        $html = array_filter($pages, fn (array $p) => $p['status'] === 200);

        // -- Indexation (TSEO-003) ----------------------------------------
        $indexation = [];
        foreach ($html as $p) {
            if ($p['noindex']) {
                $indexation[] = "{$p['url']} is set to noindex.";
            }
            if ($p['canonical'] !== '' && rtrim($p['canonical'], '/') !== rtrim($p['url'], '/')) {
                $indexation[] = "{$p['url']} canonicalizes to {$p['canonical']} — its content credits another URL.";
            }
        }

        // -- robots.txt (TSEO-014) ----------------------------------------
        $robotsItems = [];
        if (! $robots['found']) {
            $robotsItems[] = 'No robots.txt — crawlers get no guidance and no sitemap pointer.';
        } elseif (in_array('/', $robots['disallows'], true)) {
            $robotsItems[] = 'robots.txt disallows the whole site (Disallow: /).';
        } elseif ($robots['sitemaps'] === []) {
            $robotsItems[] = 'robots.txt has no Sitemap: line.';
        }

        // -- Crawlability (TSEO-004) --------------------------------------
        $crawlability = [];
        foreach ($pages as $p) {
            foreach ($robots['disallows'] as $prefix) {
                if ($prefix !== '/' && $prefix !== '' && str_starts_with($this->path($p['url']), $prefix)) {
                    $crawlability[] = "{$p['url']} is linked internally but blocked by robots.txt ({$prefix}).";
                    break;
                }
            }
        }

        // -- Sitemap (TSEO-013) -------------------------------------------
        $sitemap = [];
        if ($sitemapUrls === []) {
            $sitemap[] = 'No sitemap.xml found at the site root.';
        } else {
            $inSitemap = array_map(fn (string $u) => rtrim($u, '/'), $sitemapUrls);
            foreach ($html as $p) {
                if (! $p['noindex'] && ! in_array(rtrim($p['url'], '/'), $inSitemap, true)) {
                    $sitemap[] = "{$p['url']} is crawlable but missing from sitemap.xml.";
                }
            }
        }

        // -- Internal links (TSEO-010) ------------------------------------
        $links = [];
        foreach ($html as $p) {
            $in = count($inlinks[$p['url']] ?? []);
            if ($in === 0 && $p['url'] !== $this->normalize($startUrl, $startUrl)) {
                $links[] = "{$p['url']} is an orphan — no internal links point to it (found via sitemap).";
            }
            if ($p['links'] === []) {
                $links[] = "{$p['url']} is a dead end — it links to nothing internally.";
            }
        }

        // -- Duplicates (TSEO-016) ----------------------------------------
        $duplicates = [];
        foreach (['title' => 'title', 'description' => 'meta description', 'content_hash' => 'body content'] as $field => $label) {
            $groups = [];
            foreach ($html as $p) {
                if (($p[$field] ?? '') !== '') {
                    $groups[$p[$field]][] = $p['url'];
                }
            }
            foreach ($groups as $urls) {
                if (count($urls) > 1) {
                    $duplicates[] = ucfirst($label).' duplicated across: '.implode(' · ', $urls).' — differentiate or canonicalize.';
                }
            }
        }

        // -- Broken links (TSEO-017) --------------------------------------
        $broken = [];
        foreach ($pages as $p) {
            if ($p['status'] !== null && $p['status'] < 400) {
                continue;
            }
            $label = $p['status'] !== null ? "HTTP {$p['status']}" : 'unfetchable';
            $sources = implode(' · ', array_slice($inlinks[$p['url']] ?? [], 0, 3));
            $broken[] = "{$p['url']} is broken ({$label})".($sources !== '' ? ", linked from {$sources}" : '').'.';
        }

        // -- Redirects (TSEO-018) -----------------------------------------
        $redirectItems = [];
        foreach ($redirects as $from => $to) {
            $hops = 1;
            $cursor = $to;
            $seen = [$from => true];
            while (isset($redirects[$cursor]) && ! isset($seen[$cursor])) {
                $seen[$cursor] = true;
                $cursor = $redirects[$cursor];
                $hops++;
            }
            // Stopping on an already-seen URL means the chain bit its own tail.
            if (isset($redirects[$cursor])) {
                $redirectItems[] = "Redirect loop starting at {$from}.";
            } elseif ($hops > 1) {
                $redirectItems[] = "{$from} reaches {$cursor} through {$hops} hops — point it straight at the destination.";
            } else {
                $redirectItems[] = "{$from} redirects to {$to}.";
            }
        }

        // -- Speed heuristics (TSEO-021: heuristic only, CWV needs field data)
        $speed = [];
        foreach ($html as $p) {
            if ($p['bytes'] > self::HTML_BYTES_LIMIT) {
                $kb = (int) round($p['bytes'] / 1024);
                $speed[] = "{$p['url']} ships {$kb}KB of HTML — heavy for a marketing page.";
            }
        }

        // -- Architecture (TSEO-022) --------------------------------------
        $architecture = [];
        $depths = array_count_values(array_map(fn (array $p) => (int) $p['depth'], $html));
        ksort($depths);
        foreach ($html as $p) {
            if ($p['depth'] > self::DEPTH_LIMIT) {
                $architecture[] = "{$p['url']} sits {$p['depth']} clicks deep — buried pages rank and convert worse.";
            }
        }

        $sections = [
            $this->section('indexation', 'Indexation', $indexation),
            $this->section('robots', 'robots.txt', $robotsItems),
            $this->section('crawlability', 'Crawlability', $crawlability),
            $this->section('sitemap', 'XML sitemap', $sitemap),
            $this->section('links', 'Internal linking', $links),
            $this->section('duplicates', 'Duplicate content', $duplicates),
            $this->section('broken', 'Broken links', $broken),
            $this->section('redirects', 'Redirects', $redirectItems),
            $this->section('speed', 'Speed heuristics', $speed),
            $this->section('architecture', 'Site architecture', $architecture),
        ];

        return [
            'sections' => $sections,
            'issues_count' => array_sum(array_map(fn (array $s) => count($s['items']), $sections)),
            'depths' => $depths,
            'sitemap_urls' => count($sitemapUrls),
            'pages' => array_values(array_map(fn (array $p) => [
                'url' => $p['url'], 'status' => $p['status'], 'depth' => $p['depth'],
                'title' => mb_substr((string) $p['title'], 0, 120),
                'inlinks' => count($inlinks[$p['url']] ?? []),
                'outlinks' => count($p['links']),
                'bytes' => $p['bytes'],
            ], $pages)),
        ];
    }

    /**
     * @param  list<string>  $items
     * @return array{key: string, label: string, ok: bool, items: list<string>}
     */
    private function section(string $key, string $label, array $items): array
    {
        return ['key' => $key, 'label' => $label, 'ok' => $items === [], 'items' => $items];
    }

    /**
     * @return array{found: bool, disallows: list<string>, sitemaps: list<string>}
     */
    private function fetchRobots(string $origin): array
    {
        $out = ['found' => false, 'disallows' => [], 'sitemaps' => []];

        try {
            $response = Http::timeout(10)->withoutRedirecting()->get($origin.'/robots.txt');
            if (! $response->successful()) {
                return $out;
            }

            $out['found'] = true;
            $applies = false;
            foreach (preg_split('/\r?\n/', $response->body()) ?: [] as $line) {
                $line = trim(preg_replace('/#.*$/', '', $line) ?? '');
                if (preg_match('/^user-agent:\s*(.+)$/i', $line, $m) === 1) {
                    $applies = trim($m[1]) === '*';
                } elseif ($applies && preg_match('/^disallow:\s*(\S+)/i', $line, $m) === 1) {
                    $out['disallows'][] = trim($m[1]);
                } elseif (preg_match('/^sitemap:\s*(\S+)/i', $line, $m) === 1) {
                    $out['sitemaps'][] = trim($m[1]);
                }
            }
        } catch (Throwable) {
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function fetchSitemap(string $origin): array
    {
        try {
            $response = Http::timeout(10)->withoutRedirecting()->get($origin.'/sitemap.xml');
            if (! $response->successful()) {
                return [];
            }

            preg_match_all('/<loc>\s*([^<\s]+)\s*<\/loc>/i', $response->body(), $m);

            $urls = [];
            foreach ($m[1] as $loc) {
                $loc = html_entity_decode($loc);
                if ($this->origin($loc) === $origin) {
                    $urls[] = $this->normalize($loc, $origin.'/');
                }
            }

            return array_values(array_unique($urls));
        } catch (Throwable) {
            return [];
        }
    }

    /** Scheme + host (+ explicit port) — the crawl never leaves this. */
    private function origin(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return mb_strtolower($parts['scheme'].'://'.$parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function path(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    /** Resolve an href against its page; null for schemes we never crawl. */
    private function resolveLink(string $href, string $baseUrl): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto|tel|javascript):/i', $href) === 1) {
            return null;
        }

        return $this->normalize($href, $baseUrl);
    }

    /** Absolute URL without fragment; relative paths resolved against the base. */
    private function normalize(string $url, string $baseUrl): string
    {
        $url = preg_replace('/#.*$/', '', trim($url)) ?? '';
        $base = parse_url($baseUrl);
        $scheme = is_array($base) && isset($base['scheme']) ? $base['scheme'] : 'https';

        if (str_starts_with($url, '//')) {
            $url = $scheme.':'.$url;
        } elseif (str_starts_with($url, '/')) {
            $url = $this->origin($baseUrl).$url;
        } elseif (preg_match('#^https?://#i', $url) !== 1) {
            $dir = rtrim(dirname(is_array($base) && isset($base['path']) ? $base['path'] : '/'), '/\\');
            $url = $this->origin($baseUrl).$dir.'/'.$url;
        }

        return rtrim($url, '/') === $this->origin($url) ? $this->origin($url).'/' : $url;
    }
}
