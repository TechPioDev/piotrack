<?php

namespace App\Ai;

use App\Ai\Contracts\AiProvider;
use App\Ai\Providers\AnthropicProvider;
use App\Ai\Providers\FixtureAiProvider;
use App\Ai\Providers\GeminiProvider;
use App\Ai\Providers\OpenAiProvider;
use App\Models\PlatformSetting;

/**
 * Resolves the configured language-model driver (ADR-0008). Defaults to the
 * tested fixture driver; `openai`/`anthropic`/`gemini` select the live drivers.
 *
 * Settings saved in the platform console override the environment, so the
 * operator can switch provider or rotate a key without a deploy. The override
 * is applied here — the one choke point every AI call already passes through —
 * rather than at boot, so requests that never touch AI never touch the table.
 */
class AiProviderManager
{
    private bool $overridesApplied = false;

    public function driver(): AiProvider
    {
        $this->applyOverrides();

        return match ((string) config('ai.driver', 'fixture')) {
            'openai' => new OpenAiProvider,
            'anthropic' => new AnthropicProvider,
            'gemini' => new GeminiProvider,
            default => new FixtureAiProvider,
        };
    }

    /**
     * A specific driver regardless of the configured one — the settings page
     * uses this so "Test" exercises the provider being edited, not whichever
     * happens to be active.
     */
    public function driverNamed(string $name): AiProvider
    {
        $this->applyOverrides();

        return match ($name) {
            'openai' => new OpenAiProvider,
            'anthropic' => new AnthropicProvider,
            'gemini' => new GeminiProvider,
            default => new FixtureAiProvider,
        };
    }

    /**
     * Whether the active driver is a real language model. The UI uses this to
     * state plainly when output comes from the fixture driver.
     */
    public function isLive(): bool
    {
        return $this->driver()->name() !== 'fixture';
    }

    /**
     * Lay the platform-console settings over the env-derived config. Once per
     * request is enough; the table is tiny and safely absent on fresh installs.
     */
    private function applyOverrides(): void
    {
        if ($this->overridesApplied) {
            return;
        }
        $this->overridesApplied = true;

        foreach (PlatformSetting::under('ai.') as $key => $value) {
            config()->set($key, $value);
        }
    }
}
