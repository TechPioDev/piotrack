<?php

namespace App\Seo\Providers;

use App\Seo\Contracts\RankProvider;
use App\Seo\RankResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Real SERP rank driver over the SerpApi HTTP API. Real code, but with no API
 * key in this environment it is NOT exercised in tests — status "Implemented
 * (untested — requires credentials)", never "Tested" (ADR-0005, §38). Selected
 * with SEO_RANK_PROVIDER=serpapi.
 */
class SerpApiRankProvider implements RankProvider
{
    public function rank(string $keyword, string $domain, ?string $location, string $engine): RankResult
    {
        $key = (string) config('seo.serpapi.key');

        if ($key === '') {
            return new RankResult(null);
        }

        try {
            $response = Http::timeout(20)->get('https://serpapi.com/search.json', array_filter([
                'engine' => 'google',
                'q' => $keyword,
                'location' => $location,
                'api_key' => $key,
            ]));

            if ($response->failed()) {
                return new RankResult(null);
            }

            /** @var array<int, array<string, mixed>> $organic */
            $organic = $response->json('organic_results', []);

            foreach ($organic as $result) {
                if (str_contains((string) ($result['link'] ?? ''), $domain)) {
                    return new RankResult(
                        isset($result['position']) ? (int) $result['position'] : null,
                        (string) ($result['link'] ?? ''),
                    );
                }
            }

            return new RankResult(null);
        } catch (Throwable) {
            return new RankResult(null);
        }
    }

    /**
     * ANLY-012: local-pack position from the SAME SerpApi response surface
     * the rank lookups use — `local_results` carries the map pack.
     */
    public function localPack(string $keyword, string $businessName, ?string $location): ?int
    {
        $key = (string) config('seo.serpapi.key');

        if ($key === '') {
            return null;
        }

        try {
            $response = Http::timeout(20)->get('https://serpapi.com/search.json', array_filter([
                'engine' => 'google',
                'q' => $keyword,
                'location' => $location,
                'api_key' => $key,
            ]));

            if ($response->failed()) {
                return null;
            }

            /** @var array<int, array<string, mixed>> $places */
            $places = $response->json('local_results.places', []);

            foreach ($places as $i => $place) {
                if (mb_stripos((string) ($place['title'] ?? ''), $businessName) !== false) {
                    return isset($place['position']) ? (int) $place['position'] : $i + 1;
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }
}
