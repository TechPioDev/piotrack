<?php

namespace App\Seo\Providers;

use App\Seo\Contracts\SearchConsoleProvider;

/**
 * Deterministic Search Console fixture: the same site always yields the same
 * performance rows and coverage picture (seeded from a hash). Manual actions
 * are returned ONLY for hosts containing "penalized" — a self-describing
 * marker, so demos and tests can exercise the penalty path without ever
 * implying a real site is penalized. The UI labels this driver as simulated.
 */
class FixtureSearchConsoleProvider implements SearchConsoleProvider
{
    private const QUERY_POOL = [
        'managed it services', 'it support near me', 'msp pricing', 'co-managed it',
        'network security audit', 'microsoft 365 migration', 'it helpdesk outsourcing',
        'ransomware recovery services', 'cmmc compliance help', 'backup and disaster recovery',
    ];

    private const ISSUE_TYPES = [
        ['Crawled - currently not indexed', '/blog/archive'],
        ['Duplicate without user-selected canonical', '/services/it-support-2'],
        ['Page with redirect', '/old-home'],
    ];

    public function searchPerformance(string $siteUrl): array
    {
        $seed = $this->seed($siteUrl);
        $rows = [];

        $count = 6 + ($seed % 3);
        for ($i = 0; $i < $count; $i++) {
            $impressions = 120 + (($seed + $i * 37) % 900);
            $clicks = (int) round($impressions * ((3 + (($seed + $i) % 9)) / 100));
            $rows[] = [
                'query' => self::QUERY_POOL[($seed + $i) % count(self::QUERY_POOL)],
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => round($clicks / $impressions * 100, 1),
                'position' => round(2 + (($seed + $i * 13) % 280) / 10, 1),
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['clicks'] <=> $a['clicks']);

        return $rows;
    }

    public function indexCoverage(string $siteUrl): array
    {
        $seed = $this->seed($siteUrl);

        $issues = [];
        $issueCount = $seed % 3; // 0-2 issue types
        for ($i = 0; $i < $issueCount; $i++) {
            [$type, $example] = self::ISSUE_TYPES[($seed + $i) % count(self::ISSUE_TYPES)];
            $issues[] = ['type' => $type, 'count' => 1 + (($seed + $i) % 5), 'example' => $example];
        }

        return [
            'indexed' => 18 + ($seed % 40),
            'issues' => $issues,
        ];
    }

    public function manualActions(string $siteUrl): array
    {
        if (str_contains(mb_strtolower($siteUrl), 'penalized')) {
            return [['type' => 'Unnatural links to your site', 'reason' => 'A pattern of unnatural artificial, deceptive, or manipulative links points to this site.']];
        }

        return [];
    }

    public function name(): string
    {
        return 'fixture';
    }

    private function seed(string $siteUrl): int
    {
        return crc32(mb_strtolower(trim($siteUrl)));
    }
}
