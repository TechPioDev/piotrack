<?php

declare(strict_types=1);

/**
 * Platform AI settings: which model answers, configured without a deploy.
 *
 * Two things are worth being paranoid about here. The API key is a platform
 * credential — it must be encrypted at rest, never sent back to any client,
 * never written to the audit trail, and invisible to tenant admins entirely.
 * And the override must actually take effect: a saved driver that the provider
 * manager ignores would be a settings page that lies.
 */

use App\Ai\AiProviderManager;
use App\Ai\Exceptions\AiProviderException;
use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function aiPlatformStaff(): User
{
    return User::factory()->create(['platform_role' => Role::PlatformSuperAdmin->value]);
}

it('is invisible to a tenant admin', function () {
    [, $owner] = makeOrganization('Tenant Co');

    $this->actingAs($owner)->get('/platform/ai')->assertForbidden();
    $this->actingAs($owner)->post('/platform/ai', ['driver' => 'fixture'])->assertForbidden();
    $this->actingAs($owner)->postJson('/platform/ai/test', ['driver' => 'fixture'])->assertForbidden();
});

it('stores the key encrypted and never hands it back', function () {
    $staff = aiPlatformStaff();

    $this->actingAs($staff)->post('/platform/ai', [
        'driver' => 'anthropic',
        'api_key' => 'sk-ant-super-secret-1234',
        'model' => 'claude-haiku-4-5-20251001',
    ])->assertRedirect();

    // Encrypted at rest: the raw row must not contain the plaintext.
    $raw = (string) DB::table('platform_settings')->where('key', 'ai.anthropic.api_key')->value('value');
    expect($raw)->not->toContain('sk-ant-super-secret-1234')
        ->and(PlatformSetting::get('ai.anthropic.api_key'))->toBe('sk-ant-super-secret-1234');

    // The page gets a recognisable hint, never the value.
    $props = $this->actingAs($staff)->get('/platform/ai')->assertOk()
        ->viewData('page')['props'];
    expect($props['providers']['anthropic']['key_hint'])->toBe('…1234')
        ->and(json_encode($props))->not->toContain('sk-ant-super-secret-1234');

    // The audit trail records that a key changed, not what it changed to.
    $audit = AuditLog::withoutGlobalScopes()->where('action', 'platform.ai.settings_changed')->latest('id')->first();
    expect(json_encode($audit->context))->not->toContain('sk-ant-super-secret-1234')
        ->and($audit->context['api_key'])->toBe('[rotated]');
});

it('keeps the stored key when the form is saved with the field blank', function () {
    $staff = aiPlatformStaff();
    PlatformSetting::put('ai.anthropic.api_key', 'sk-ant-keep-me');

    $this->actingAs($staff)->post('/platform/ai', [
        'driver' => 'anthropic',
        'api_key' => '',
        'model' => 'claude-sonnet-5',
    ])->assertRedirect();

    expect(PlatformSetting::get('ai.anthropic.api_key'))->toBe('sk-ant-keep-me');
});

it('actually switches the provider the gateway resolves', function () {
    PlatformSetting::put('ai.driver', 'openai');

    expect(app(AiProviderManager::class)->driver()->name())->toBe('openai');
});

it('reports the fixture as working and an unconfigured provider as not', function () {
    $staff = aiPlatformStaff();

    $ok = $this->actingAs($staff)->postJson('/platform/ai/test', ['driver' => 'fixture'])
        ->assertOk()->json();
    expect($ok['ok'])->toBeTrue()
        ->and($ok['latency_ms'])->toBeGreaterThanOrEqual(0);

    // No key saved: the test must say so plainly rather than 500.
    $fail = $this->actingAs($staff)->postJson('/platform/ai/test', ['driver' => 'anthropic'])
        ->assertOk()->json();
    expect($fail['ok'])->toBeFalse()
        ->and($fail['error'])->toContain('not configured');
});

it('speaks the Gemini wire format', function () {
    config()->set('ai.gemini.api_key', 'test-key');
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'modelVersion' => 'gemini-2.5-flash',
            'candidates' => [['content' => ['parts' => [['text' => 'ready']]]]],
            'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 3],
        ]),
    ]);

    $completion = app(AiProviderManager::class)->driverNamed('gemini')->complete('Say ready', 'Be brief.');

    expect($completion->text)->toBe('ready')
        ->and($completion->promptTokens)->toBe(12)
        ->and($completion->completionTokens)->toBe(3)
        ->and($completion->model)->toBe('gemini-2.5-flash');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'gemini-2.5-flash:generateContent')
            && $request->hasHeader('x-goog-api-key', 'test-key')
            && $request['system_instruction']['parts'][0]['text'] === 'Be brief.';
    });
});

it('flags a Gemini rate limit as transient so the gateway retries it', function () {
    config()->set('ai.gemini.api_key', 'test-key');
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'slow down'], 429)]);

    try {
        app(AiProviderManager::class)->driverNamed('gemini')->complete('hello');
        $this->fail('expected an AiProviderException');
    } catch (AiProviderException $e) {
        expect($e->transient)->toBeTrue();
    }
});
