<?php

namespace App\Seo\Contracts;

use App\Seo\RankResult;

/**
 * SERP rank lookup (ADR-0005). The `fixture` driver is the tested default;
 * `serpapi`/`dataforseo` are real but untested here (no credentials).
 */
interface RankProvider
{
    public function rank(string $keyword, string $domain, ?string $location, string $engine): RankResult;

    /**
     * ANLY-012: the business's position in the Google local pack (map results)
     * for a keyword, or null when it does not appear / cannot be measured.
     * Never an invented position.
     */
    public function localPack(string $keyword, string $businessName, ?string $location): ?int;
}
