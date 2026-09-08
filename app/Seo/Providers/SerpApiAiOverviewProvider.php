<?php

namespace App\Seo\Providers;

use App\Seo\AiVisibilityResult;
use App\Seo\AnswerAnalyzer;
use App\Seo\Contracts\AiSearchProvider;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google AI Overview visibility driver (AIVM / AIVIS-006): AI Overviews are
 * part of the Google SERP, and SerpApi returns the overview's text blocks in
 * its google engine response — so this rides the SAME SerpApi key the rank
 * driver already uses. No overview on the SERP = an honest empty result,
 * never an invented one.
 */
class SerpApiAiOverviewProvider implements AiSearchProvider
{
    public function __construct(private AnswerAnalyzer $analyzer) {}

    public static function key(): string
    {
        return (string) config('seo.serpapi.key');
    }

    public function query(string $prompt, string $brand, array $competitors = []): AiVisibilityResult
    {
        $key = self::key();

        if ($key === '') {
            return new AiVisibilityResult(false, null, [], [], 0);
        }

        try {
            $response = Http::timeout(30)->get('https://serpapi.com/search.json', [
                'engine' => 'google',
                'q' => $prompt,
                'api_key' => $key,
            ]);

            if ($response->failed()) {
                return new AiVisibilityResult(false, null, [], [], 0);
            }

            /** @var array<int, array<string, mixed>> $blocks */
            $blocks = $response->json('ai_overview.text_blocks', []);
            $answer = $this->flatten($blocks);

            if ($answer === '') {
                // The SERP simply had no AI Overview for this prompt.
                return new AiVisibilityResult(false, null, [], [], 0);
            }

            return $this->analyzer->analyze($answer, $brand, $competitors);
        } catch (Throwable) {
            return new AiVisibilityResult(false, null, [], [], 0);
        }
    }

    /**
     * SerpApi renders the overview as nested text blocks; join every snippet.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function flatten(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            if (isset($block['snippet']) && is_string($block['snippet'])) {
                $parts[] = $block['snippet'];
            }
            if (isset($block['list']) && is_array($block['list'])) {
                foreach ($block['list'] as $item) {
                    if (is_array($item) && isset($item['snippet']) && is_string($item['snippet'])) {
                        $parts[] = $item['snippet'];
                    }
                }
            }
        }

        return trim(implode("\n", $parts));
    }
}
