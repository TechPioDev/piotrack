<?php

namespace App\Services\Seo;

use App\Models\AiVisibilityCheck;
use App\Models\Competitor;
use App\Seo\SeoProviderManager;
use App\Support\AuditLogger;

/**
 * Records AI-engine visibility for a prompt via the engine's AiSearchProvider
 * (AEO-018, GEO-001…010). Captures mention/position/cited sources/competitors/
 * share-of-answer as a snapshot for trend + share reporting.
 */
class AiVisibilityService
{
    public function __construct(
        private AuditLogger $audit,
        private SeoProviderManager $providers,
    ) {}

    public function check(string $prompt, string $brand, string $engine = 'chatgpt'): AiVisibilityCheck
    {
        // Engine-specific driver + tenant competitor names for analysis (AIVM).
        $competitorNames = Competitor::query()->pluck('name')->filter()->values()->all();
        $result = $this->providers->aiFor($engine)->query($prompt, $brand, $competitorNames);

        $check = AiVisibilityCheck::create([
            'prompt' => $prompt,
            'engine' => $engine,
            'brand' => $brand,
            'mentioned' => $result->mentioned,
            'position' => $result->position,
            'cited_sources' => $result->citedSources,
            'competitors' => $result->competitors,
            'share_of_answer' => $result->shareOfAnswer,
            'answer_excerpt' => $result->answerExcerpt !== '' ? $result->answerExcerpt : null,
            // Which driver produced this. The fixture driver invents competitor
            // domains and citations, which must never read as market findings.
            'provider' => $this->providers->aiProviderNameFor($engine),
            'checked_at' => now(),
        ]);

        $this->audit->log('seo.ai.checked', context: ['prompt' => $prompt, 'engine' => $engine, 'mentioned' => $result->mentioned, 'provider' => $this->providers->aiProviderName()], resourceType: 'ai_visibility_check', resourceId: (string) $check->id, organizationId: $check->organization_id);

        return $check;
    }
}
