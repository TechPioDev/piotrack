<?php

namespace App\Http\Controllers\Platform;

use App\Ai\AiProviderManager;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform console: which language model answers, and with whose key.
 *
 * Platform-scoped on purpose. Tenants pay for AI in plan credits; the provider
 * account behind those credits belongs to the operator, so the key lives here
 * and never in a tenant-facing screen. Keys are encrypted at rest, written
 * blind (the form never gets the value back, only its last four characters),
 * and redacted from the audit trail.
 */
class AiSettingsController extends Controller
{
    private const PROVIDERS = ['fixture', 'anthropic', 'openai', 'gemini'];

    public function __construct(
        private readonly AiProviderManager $providers,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(): Response
    {
        $active = $this->providers->driver();

        return Inertia::render('platform/ai', [
            'driver' => $active->name(),
            'activeModel' => $active->model(),
            'providers' => collect(['anthropic', 'openai', 'gemini'])->mapWithKeys(function (string $name) {
                $key = PlatformSetting::get("ai.{$name}.api_key") ?? (string) config("ai.{$name}.api_key", '');

                return [$name => [
                    // Enough to recognise the key, never enough to use it.
                    'key_hint' => $key !== '' ? '…'.substr($key, -4) : null,
                    'model' => PlatformSetting::get("ai.{$name}.model") ?? (string) config("ai.{$name}.model", ''),
                ]];
            }),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'driver' => ['required', Rule::in(self::PROVIDERS)],
            'api_key' => ['nullable', 'string', 'max:300'],
            'model' => ['nullable', 'string', 'max:120'],
        ]);

        PlatformSetting::put('ai.driver', $data['driver']);

        if ($data['driver'] !== 'fixture') {
            // A blank key means "keep the one already stored" — the form never
            // holds the current value, so blank must not erase it.
            if (($data['api_key'] ?? '') !== '') {
                PlatformSetting::put("ai.{$data['driver']}.api_key", $data['api_key']);
            }
            PlatformSetting::put("ai.{$data['driver']}.model", $data['model'] ?? null);
        }

        $this->audit->log('platform.ai.settings_changed', context: [
            'driver' => $data['driver'],
            'model' => $data['model'] ?? null,
            'api_key' => ($data['api_key'] ?? '') !== '' ? '[rotated]' : '[unchanged]',
        ]);

        return back()->with('status', 'AI settings saved.');
    }

    /**
     * One tiny real completion against a named provider, so "does my key work"
     * is answered here rather than by a visitor. Calls the driver directly —
     * not through the gateway — so a connectivity check never consumes a
     * tenant's credits or lands in their usage.
     */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'driver' => ['required', Rule::in(self::PROVIDERS)],
        ]);

        $startedAt = microtime(true);

        try {
            $completion = $this->providers->driverNamed($data['driver'])
                ->complete('Reply with the single word: ready');

            return response()->json([
                'ok' => true,
                'model' => $completion->model,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'reply' => mb_substr(trim($completion->text), 0, 120),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
