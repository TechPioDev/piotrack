<?php

namespace App\Seo\Providers;

use App\Seo\Contracts\LinkDataProvider;

/**
 * Deterministic link-index fixture: the same domain always yields the same
 * link set (seeded from a hash), including a few deliberately toxic-shaped
 * rows so the audit heuristics have something real to catch in tests and
 * demos. The UI labels this driver's output as simulated.
 */
class FixtureLinkDataProvider implements LinkDataProvider
{
    private const CLEAN_SOURCES = [
        ['msp-insider.example', 22], ['it-channel-news.example', 47], ['tech-roundup.example', 38],
        ['smb-technology.example', 31], ['cloud-review.example', 55], ['cyber-briefing.example', 44],
        ['business-journal.example', 61], ['startup-weekly.example', 28],
    ];

    private const TOXIC_SOURCES = [
        ['best-links-cheap.xyz', 3, 'managed it services'],
        ['seo-directory-farm.top', 5, 'it support company'],
        ['free-backlinks.click', 2, 'msp services'],
    ];

    public function backlinks(string $domain): array
    {
        $seed = crc32(mb_strtolower(trim($domain)));
        $links = [];

        // 4-6 clean links from the shared pool, rotated by the domain seed.
        $cleanCount = 4 + ($seed % 3);
        for ($i = 0; $i < $cleanCount; $i++) {
            [$source, $da] = self::CLEAN_SOURCES[($seed + $i) % count(self::CLEAN_SOURCES)];
            $links[] = [
                'source_domain' => $source,
                'url' => "https://{$source}/articles/".(($seed + $i) % 97),
                'anchor' => $i % 3 === 0 ? $domain : 'read the analysis',
                'domain_authority' => $da,
            ];
        }

        // 3 sources unique to this domain, so different domains genuinely
        // have different link profiles (competitor gap analysis needs that).
        for ($i = 1; $i <= 3; $i++) {
            $n = ($seed * $i) % 900 + 100;
            $links[] = [
                'source_domain' => "industry-press-{$n}.example",
                'url' => "https://industry-press-{$n}.example/coverage/{$i}",
                'anchor' => 'company profile',
                'domain_authority' => 20 + (($seed + $i) % 40),
            ];
        }

        // 1-3 toxic-shaped links.
        $toxicCount = 1 + ($seed % 3);
        for ($i = 0; $i < $toxicCount; $i++) {
            [$source, $da, $anchor] = self::TOXIC_SOURCES[($seed + $i) % count(self::TOXIC_SOURCES)];
            $links[] = [
                'source_domain' => $source,
                'url' => "https://{$source}/links/".(($seed + $i) % 53),
                'anchor' => $anchor,
                'domain_authority' => $da,
            ];
        }

        return $links;
    }

    public function name(): string
    {
        return 'fixture';
    }
}
