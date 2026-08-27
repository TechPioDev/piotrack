<?php

namespace App\Seo\Contracts;

use App\Seo\AiVisibilityResult;

/**
 * AI-engine visibility lookup (ADR-0005). The `fixture` driver is the tested
 * default; `openai`/`perplexity` are real but untested here (no credentials).
 */
interface AiSearchProvider
{
    /**
     * @param  list<string>  $competitors  Known competitor names, so analysis
     *                                     can rank the brand among them.
     */
    public function query(string $prompt, string $brand, array $competitors = []): AiVisibilityResult;
}
