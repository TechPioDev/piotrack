<?php

namespace App\Seo\Providers;

use App\Seo\Contracts\RankProvider;
use App\Seo\RankResult;

/**
 * The default, fully-tested rank driver: a deterministic position derived from
 * a hash of the inputs (1–20). The whole rank pipeline — history, current
 * position, page-one/top-three flags, competitor comparison — runs for real
 * against it (ADR-0005). Also serves as a "bring-your-own-data" mode.
 */
class FixtureRankProvider implements RankProvider
{
    public function rank(string $keyword, string $domain, ?string $location, string $engine): RankResult
    {
        $seed = crc32(mb_strtolower($keyword.'|'.$domain.'|'.$engine.'|'.($location ?? '')));
        $position = (int) ($seed % 20) + 1;

        return new RankResult($position, "https://{$domain}/");
    }

    public function localPack(string $keyword, string $businessName, ?string $location): ?int
    {
        $seed = crc32(mb_strtolower($keyword.'|'.$businessName.'|'.($location ?? '')));

        // The pack has three spots; roughly half of queries miss it entirely —
        // deterministically, so tests and demos see both outcomes.
        return $seed % 2 === 0 ? ($seed >> 2) % 3 + 1 : null;
    }
}
