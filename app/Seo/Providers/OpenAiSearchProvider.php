<?php

namespace App\Seo\Providers;

use App\Models\PlatformSetting;
use App\Seo\AiVisibilityResult;
use App\Seo\AnswerAnalyzer;
use App\Seo\Contracts\AiSearchProvider;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * ChatGPT visibility driver (AIVM / AIVIS-002): asks the OpenAI API the prompt
 * and measures the answer through the shared AnswerAnalyzer. The HTTP mechanics
 * and analysis are Http::fake-verified; live traffic additionally needs a key
 * (env, or the platform AI console's) and outbound 443.
 */
class OpenAiSearchProvider implements AiSearchProvider
{
    public function __construct(private AnswerAnalyzer $analyzer) {}

    public static function key(): string
    {
        return (string) (config('seo.openai.key') ?: PlatformSetting::get('ai.openai.api_key', ''));
    }

    public function query(string $prompt, string $brand, array $competitors = []): AiVisibilityResult
    {
        $key = self::key();

        if ($key === '') {
            return new AiVisibilityResult(false, null, [], [], 0);
        }

        try {
            $response = Http::withToken($key)->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => (string) config('seo.openai.model', 'gpt-4o-mini'),
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
