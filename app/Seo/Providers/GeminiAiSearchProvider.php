<?php

namespace App\Seo\Providers;

use App\Models\PlatformSetting;
use App\Seo\AiVisibilityResult;
use App\Seo\AnswerAnalyzer;
use App\Seo\Contracts\AiSearchProvider;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gemini visibility driver (AIVM / AIVIS-003): asks the Gemini API the prompt
 * and measures the answer through the shared AnswerAnalyzer. Http::fake-verified
 * mechanics; live traffic needs a key (env `GEMINI_API_KEY` via config, or the
 * platform AI console's) and outbound 443.
 */
class GeminiAiSearchProvider implements AiSearchProvider
{
    public function __construct(private AnswerAnalyzer $analyzer) {}

    public static function key(): string
    {
        return (string) (config('seo.gemini.key') ?: PlatformSetting::get('ai.gemini.api_key', ''));
    }

    public function query(string $prompt, string $brand, array $competitors = []): AiVisibilityResult
    {
        $key = self::key();

        if ($key === '') {
            return new AiVisibilityResult(false, null, [], [], 0);
        }

        $model = (string) config('seo.gemini.model', 'gemini-2.0-flash');

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])->timeout(30)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                ]);

            if ($response->failed()) {
                return new AiVisibilityResult(false, null, [], [], 0);
            }

            return $this->analyzer->analyze((string) $response->json('candidates.0.content.parts.0.text', ''), $brand, $competitors);
        } catch (Throwable) {
            return new AiVisibilityResult(false, null, [], [], 0);
        }
    }
}
