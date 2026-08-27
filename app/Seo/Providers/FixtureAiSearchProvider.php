<?php

namespace App\Seo\Providers;

use App\Seo\AiVisibilityResult;
use App\Seo\Contracts\AiSearchProvider;

/**
 * The default, fully-tested AI-visibility driver: deterministic results derived
 * from a hash of (prompt, brand). The whole AI-visibility pipeline — mention,
 * position, cited sources, competitors, share-of-answer, history — runs for
 * real against it (ADR-0005).
 */
class FixtureAiSearchProvider implements AiSearchProvider
{
    public function query(string $prompt, string $brand, array $competitors = []): AiVisibilityResult
    {
        $seed = crc32(mb_strtolower($prompt.'|'.$brand));
        $mentioned = ($seed % 3) !== 0; // ~2 in 3

        // The excerpt must never read as a market finding (AIVM).
        $excerpt = '[Simulated answer — fixture driver, not a live AI engine] '
            .($mentioned ? "{$brand} appears in this simulated response to: {$prompt}" : "Simulated response to: {$prompt}");

        if (! $mentioned) {
            return new AiVisibilityResult(false, null, [], ['competitor-msp.com', 'rival-it.com'], 0, $excerpt);
        }

        $position = (int) ($seed % 5) + 1;
        $share = (int) ($seed % 50) + 30; // 30–79%

        return new AiVisibilityResult(
            true,
            $position,
            [mb_strtolower(str_replace(' ', '', $brand)).'.com', 'wikipedia.org'],
            ['competitor-msp.com'],
            $share,
            $excerpt,
        );
    }
}
