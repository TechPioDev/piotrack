<?php

namespace App\Seo\Providers;

use App\Seo\Contracts\WebVitalsProvider;

/**
 * Deterministic CWV field-data fixture (seeded from the URL). Verdicts use
 * Google's published thresholds so the display logic is real even when the
 * numbers are simulated — and the UI says they are simulated.
 */
class FixtureWebVitalsProvider implements WebVitalsProvider
{
    /** Google's CWV thresholds: [good <=, needs_improvement <=] */
    public const LCP_MS = [2500, 4000];

    public const CLS = [0.1, 0.25];

    public const INP_MS = [200, 500];

    public function fieldData(string $url): array
    {
        $seed = crc32(mb_strtolower(trim($url)));

        $lcp = 1400 + ($seed % 3400);          // 1.4s – 4.8s
        $cls = round((($seed >> 3) % 30) / 100, 2); // 0.00 – 0.29
        $inp = 90 + (($seed >> 5) % 520);      // 90ms – 610ms

        return [
            'lcp_ms' => $lcp,
            'cls' => $cls,
            'inp_ms' => $inp,
            'verdicts' => [
                'lcp' => $this->verdict($lcp, self::LCP_MS),
                'cls' => $this->verdict($cls, self::CLS),
                'inp' => $this->verdict($inp, self::INP_MS),
            ],
        ];
    }

    public function name(): string
    {
        return 'fixture';
    }

    /**
     * @param  array{0: float|int, 1: float|int}  $thresholds
     */
    private function verdict(float|int $value, array $thresholds): string
    {
        if ($value <= $thresholds[0]) {
            return 'good';
        }

        return $value <= $thresholds[1] ? 'needs_improvement' : 'poor';
    }
}
