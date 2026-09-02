<?php

namespace App\Services\Analytics;

use App\Models\Competitor;
use App\Models\CompetitorSnapshot;
use App\Support\AuditLogger;
use App\Support\UrlGuard;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Competitor content monitoring (CINT-005), in-house per ADR-0005: a
 * competitor's published content lives on their public website, so monitoring
 * it needs a fetch, not a data vendor. Discovery prefers their sitemap.xml
 * (capped), falling back to homepage links; each page is captured as
 * url + title + a normalized content hash, and the diff against the previous
 * capture — new, changed, removed — is stored with the snapshot.
 *
 * Nothing is invented: an unreachable page is recorded as unfetchable, an
 * unreachable site yields an empty snapshot rather than fabricated activity,
 * and every fetch passes the SSRF guard.
 */
class CompetitorContentMonitor
{
    /** Page budget per check — enough to watch an MSP site's key pages. */
    public const MAX_PAGES = 15;

    public function __construct(
        private UrlGuard $urls,
        private AuditLogger $audit,
    ) {}

    public function check(Competitor $competitor): CompetitorSnapshot
    {
        $origin = $this->origin($competitor);
        $this->urls->assertFetchable($origin.'/');

        $urls = $this->discover($origin);

        $pages = [];
        foreach (array_slice($urls, 0, self::MAX_PAGES) as $url) {
            $pages[] = $this->capture($url);
        }

        $previous = CompetitorSnapshot::where('competitor_id', $competitor->id)->latest('id')->first();
        $diff = $this->diff($previous->pages ?? [], $pages);

        $snapshot = CompetitorSnapshot::create([
            'competitor_id' => $competitor->id,
            'pages' => $pages,
            'pages_count' => count($pages),
            'new_pages' => $diff['new'],
            'changed_pages' => $diff['changed'],
            'removed_pages' => $diff['removed'],
        ]);

        $this->audit->log(
            'analytics.competitor.content_checked',
            context: ['competitor' => $competitor->name, 'pages' => count($pages), 'new' => count($diff['new']), 'changed' => count($diff['changed'])],
            resourceType: 'competitor',
            resourceId: (string) $competitor->id,
        );

        return $snapshot;
    }

    /**
     * Same-host URLs worth watching: the sitemap when they publish one,
     * homepage links when they do not. The homepage itself is always first.
     *
     * @return list<string>
     */
    private function discover(string $origin): array
    {
        $urls = [$origin.'/'];

        try {
            $response = Http::timeout(10)->withoutRedirecting()->get($origin.'/sitemap.xml');
            if ($response->successful()) {
                preg_match_all('/<loc>\s*([^<\s]+)\s*<\/loc>/i', $response->body(), $m);
                foreach ($m[1] as $loc) {
                    $loc = html_entity_decode($loc);
                    if (str_starts_with($loc, $origin)) {
                        $urls[] = rtrim($loc, '/') === $origin ? $origin.'/' : $loc;
                    }
                }
            }
        } catch (Throwable) {
        }

        if (count($urls) === 1) {
            // No sitemap: read the homepage's own links instead.
            foreach ($this->capture($origin.'/', true)['links'] ?? [] as $link) {
                $urls[] = $link;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return array{url: string, title: string, hash: string, links?: list<string>}
     */
    private function capture(string $url, bool $withLinks = false): array
    {
        $page = ['url' => $url, 'title' => '', 'hash' => ''];

        try {
            $this->urls->assertFetchable($url);
            $response = Http::timeout(10)->withoutRedirecting()->get($url);
            if (! $response->successful()) {
                $page['title'] = '(unfetchable: HTTP '.$response->status().')';

                return $page;
            }

            $doc = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="utf-8" ?>'.$response->body());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new DOMXPath($doc);

            $titleNodes = $xpath->query('//title');
            $title = $titleNodes !== false && $titleNodes->item(0) !== null ? trim($titleNodes->item(0)->textContent) : '';
            $bodyNodes = $xpath->query('//body');
            $body = $bodyNodes !== false && $bodyNodes->item(0) !== null ? $bodyNodes->item(0)->textContent : '';

            $page['title'] = mb_substr($title, 0, 200);
            $page['hash'] = md5(preg_replace('/\s+/u', ' ', trim($body)) ?? '');

            if ($withLinks) {
                $origin = $this->originOf($url);
                $links = [];
                foreach (iterator_to_array($xpath->query('//a[@href]') ?: []) as $a) {
                    if (! $a instanceof \DOMElement) {
                        continue;
                    }
                    $href = trim($a->getAttribute('href'));
                    if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
                        $href = $origin.$href;
                    }
                    if (str_starts_with($href, $origin) && ! str_contains($href, '#')) {
                        $links[] = $href;
                    }
                }
                $page['links'] = array_values(array_unique($links));
            }
        } catch (Throwable) {
            $page['title'] = '(unfetchable)';
        }

        return $page;
    }

    /**
     * @param  list<array{url: string, title: string, hash: string}>  $before
     * @param  list<array{url: string, title: string, hash: string}>  $after
     * @return array{new: list<string>, changed: list<string>, removed: list<string>}
     */
    private function diff(array $before, array $after): array
    {
        $beforeByUrl = array_column($before, 'hash', 'url');
        $afterByUrl = array_column($after, 'hash', 'url');

        $new = array_values(array_diff(array_keys($afterByUrl), array_keys($beforeByUrl)));
        $removed = array_values(array_diff(array_keys($beforeByUrl), array_keys($afterByUrl)));

        $changed = [];
        foreach ($afterByUrl as $url => $hash) {
            if (isset($beforeByUrl[$url]) && $beforeByUrl[$url] !== $hash && $hash !== '') {
                $changed[] = $url;
            }
        }

        return ['new' => $new, 'changed' => $changed, 'removed' => $removed];
    }

    /** The competitor's site origin from its stored domain. */
    private function origin(Competitor $competitor): string
    {
        $domain = trim((string) $competitor->domain);
        if ($domain === '') {
            throw new RuntimeException('Set the competitor\'s domain before checking their content.');
        }

        if (preg_match('#^https?://#i', $domain) !== 1) {
            $domain = 'https://'.$domain;
        }

        return $this->originOf($domain);
    }

    private function originOf(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('That domain cannot be parsed.');
        }

        return mb_strtolower($parts['scheme'].'://'.$parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
