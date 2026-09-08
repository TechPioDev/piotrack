<?php

namespace App\Seo\Contracts;

/**
 * TSEO-019: Core Web Vitals FIELD data behind a seam (ADR-0005). Lab factors
 * are computed first-party by CwvLabAuditor; field metrics come from a
 * provider (live PageSpeed Insights = API key + one class; the fixture is
 * deterministic and labeled simulated in the UI).
 */
interface WebVitalsProvider
{
    /**
     * Field metrics for a URL with per-metric verdicts against the CWV
     * thresholds (good / needs_improvement / poor).
     *
     * @return array{lcp_ms: int, cls: float, inp_ms: int, verdicts: array{lcp: string, cls: string, inp: string}}
     */
    public function fieldData(string $url): array;

    public function name(): string;
}
