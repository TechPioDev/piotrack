<?php

namespace App\Seo\Providers;

use App\Models\PlatformSetting;
use App\Seo\AiVisibilityResult;
use App\Seo\AnswerAnalyzer;
use App\Seo\Contracts\AiSearchProvider;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Perplexity visibility driver (AIVM / AIVIS-004): Perplexity exposes a public
 * OpenAI-compatible chat API, so this mirrors the ChatGPT driver against
 * api.perplexity.ai. HTTP mechanics and analysis are Http::fake-verified;
 * live traffic additionally needs a key (env, or the platform AI console's)
 * and outbound 443.
 */
class PerplexityAiSearchProvider implements AiSearchProvider
{
    public function __construct(private AnswerAnalyzer $analyzer) {}

    public static function key(): string
    {
        return (string) (config('seo.perplexity.key') ?: PlatformSetting::get('ai.perplexity.api_key', ''));
    }

    public function query(string $prompt, string $brand, array $competitors = []): AiVisibilityResult
    {
        $key = self::key();

        if ($key === '') {
            return new AiVisibilityResult(false, null, [], [], 0);
        }

        try {
            $response = Http::withToken($key)->timeout(30)->post('https://api.perplexity.ai/chat/completions', [
                'model' => (string) config('seo.perplexity.model', 'sonar'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            if ($response->failed()) {
                return new AiVisibilityResult(false, null, [], [], 0);
            }

            return $this->analyzer->analyze((string) $response->json('choices.0.message.content', ''), $brand, $competitors);
        } catch (Throwable) {
            return new AiVisibilityResult(false, null, [], [], 0);
        }
    }
}
