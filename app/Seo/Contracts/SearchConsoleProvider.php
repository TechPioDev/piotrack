<?php

namespace App\Seo\Contracts;

/**
 * TSEO-023/024: Search Console data behind a seam (ADR-0005). The fixture
 * driver ships deterministic output labeled simulated in the UI; the live
 * driver is Google OAuth credentials plus one class.
 */
interface SearchConsoleProvider
{
    /**
     * Top search queries for the site.
     *
     * @return list<array{query: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function searchPerformance(string $siteUrl): array;

    /**
     * Index coverage: how much is indexed and what is not, by issue type.
     *
     * @return array{indexed: int, issues: list<array{type: string, count: int, example: string}>}
     */
    public function indexCoverage(string $siteUrl): array;

    /**
     * Manual actions currently applied to the site (empty = none).
     *
     * @return list<array{type: string, reason: string}>
     */
    public function manualActions(string $siteUrl): array;

    public function name(): string;
}
