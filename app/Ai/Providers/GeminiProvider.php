<?php

namespace App\Ai\Providers;

use App\Ai\AiCompletion;
use App\Ai\Contracts\AiProvider;
use App\Ai\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Live Google Gemini driver (real, verified against a faked HTTP layer — a
 * genuine call still needs an API key, per ADR-0008). Transient failures
 * (timeout, 429, 5xx) are flagged so the gateway can retry them.
 */
class GeminiProvider implements AiProvider
{
    public function complete(string $prompt, ?string $system = null): AiCompletion
    {
        $key = (string) config('ai.gemini.api_key', '');
        if ($key === '') {
            throw new AiProviderException('Gemini is not configured.');
        }

        $payload = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['maxOutputTokens' => (int) config('ai.max_tokens', 1024)],
        ];
        if ($system !== null && $system !== '') {
            $payload['system_instruction'] = ['parts' => [['text' => $system]]];
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout((int) config('ai.timeout', 30))
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/'.$this->model().':generateContent',
                    $payload,
                );
        } catch (ConnectionException $e) {
            throw new AiProviderException('Gemini connection failed: '.$e->getMessage(), transient: true);
        }

        if ($response->failed()) {
            $status = $response->status();
            throw new AiProviderException(
                "Gemini request failed with status {$status}.",
                transient: $status === 429 || $status >= 500,
            );
        }

        $body = $response->json();

        return new AiCompletion(
            text: (string) ($body['candidates'][0]['content']['parts'][0]['text'] ?? ''),
            promptTokens: (int) ($body['usageMetadata']['promptTokenCount'] ?? 0),
            completionTokens: (int) ($body['usageMetadata']['candidatesTokenCount'] ?? 0),
            model: (string) ($body['modelVersion'] ?? $this->model()),
        );
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function model(): string
    {
        return (string) config('ai.gemini.model', 'gemini-2.5-flash');
    }
}
