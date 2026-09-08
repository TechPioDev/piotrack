<?php

namespace App\Seo\Providers;

use App\Seo\AiVisibilityResult;
use App\Seo\AnswerAnalyzer;
use App\Seo\Contracts\AiSearchProvider;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Microsoft Copilot visibility driver (AIVM / AIVIS-005). Consumer Copilot
 * publishes NO public API — that is stated, not papered over. What Microsoft
 * does support programmatically is the surface behind Copilot: an Azure
 * OpenAI deployment, or an OpenAI-compatible gateway in front of the M365
 * Copilot Chat API. This driver speaks the OpenAI-compatible protocol against
 * a configurable endpoint (SEO_COPILOT_ENDPOINT + SEO_COPILOT_KEY) and stays
 * honestly simulated until both are configured.
 */
class CopilotAiSearchProvider implements AiSearchProvider
{
    public function __construct(private AnswerAnalyzer $analyzer) {}

    public static function endpoint(): string
    {
        return (string) config('seo.copilot.endpoint');
    }

    public static function key(): string
    {
        return (string) config('seo.copilot.key');
    }

    public static function available(): bool
    {
        return self::endpoint() !== '' && self::key() !== '';
    }

    public function query(string $prompt, string $brand, array $competitors = []): AiVisibilityResult
    {
        if (! self::available()) {
            return new AiVisibilityResult(false, null, [], [], 0);
        }

        try {
            $response = Http::withToken(self::key())->timeout(30)->post(self::endpoint(), [
                'model' => (string) config('seo.copilot.model', 'gpt-4o'),
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
