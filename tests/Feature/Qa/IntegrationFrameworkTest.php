<?php

declare(strict_types=1);

/**
 * Integration Framework close-out (Phase 3 — INTG-001/009/010).
 *
 * Outbound webhooks are the generic integration surface: tenant-scoped signed
 * deliveries with event filtering, failure bookkeeping that never breaks the
 * emitting flow, and emission wired into the real business events. The generic
 * OAuth2 flow completes the connector framework: provider apps are pure config,
 * state is verified, tokens land in the encrypted vault. Vendor connectors
 * stay "coming soon" until their OAuth apps exist — pinned here too.
 */

use App\Authorization\Role;
use App\Integrations\ConnectorRegistry;
use App\Jobs\DeliverWebhook;
use App\Models\BookingPage;
use App\Models\Integration;
use App\Models\WebhookEndpoint;
use App\Services\Integrations\WebhookDispatcher;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Integration Org');
    subscribeOrganization($this->org, 'enterprise');
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('creates, tests and deletes webhook endpoints with permission and https guards', function () {
    $this->actingAs($this->owner)
        ->post(route('integrations.webhooks.store'), ['url' => 'http://insecure.test/hook'])
        ->assertSessionHasErrors('url');

    $this->actingAs($this->owner)
        ->post(route('integrations.webhooks.store'), ['url' => 'https://hooks.example.test/a', 'events' => ['deal.won']])
        ->assertRedirect()->assertSessionHasNoErrors();

    $endpoint = WebhookEndpoint::withoutGlobalScopes()->firstOrFail();
    expect((int) $endpoint->organization_id)->toBe((int) $this->org->id)
        ->and($endpoint->events)->toBe(['deal.won'])
        ->and($endpoint->secret)->toStartWith('whsec_');

    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)
        ->post(route('integrations.webhooks.store'), ['url' => 'https://x.test/h'])
        ->assertForbidden();

    Queue::fake();
    $this->actingAs($this->owner)
        ->post(route('integrations.webhooks.test', $endpoint->id), [])
        ->assertRedirect();
    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->event === 'ping');

    $this->actingAs($this->owner)
        ->delete(route('integrations.webhooks.destroy', $endpoint->id))
        ->assertRedirect();
    expect(WebhookEndpoint::withoutGlobalScopes()->count())->toBe(0);
});

it('delivers signed payloads, filters events, and records failures without breaking flows', function () {
    app(CurrentOrganization::class)->set($this->org);
    $wanted = WebhookEndpoint::create(['url' => 'https://receiver.test/hook', 'secret' => 's3cret', 'events' => ['booking.created']]);
    $other = WebhookEndpoint::create(['url' => 'https://other.test/hook', 'secret' => 'x', 'events' => ['deal.won']]);
    $broken = WebhookEndpoint::create(['url' => 'https://broken.test/hook', 'secret' => 'y', 'events' => []]);

    Http::fake([
        'receiver.test/*' => Http::response(['ok' => true]),
        'broken.test/*' => Http::response('nope', 500),
    ]);

    $queued = app(WebhookDispatcher::class)->dispatch('booking.created', ['booking_id' => 7]);
    expect($queued)->toBe(2); // the deal.won-only endpoint is skipped

    // Signature is HMAC-SHA256 of the exact body with the endpoint's secret.
    Http::assertSent(function ($request) {
        if (! str_starts_with($request->url(), 'https://receiver.test/')) {
            return false;
        }
        $expected = hash_hmac('sha256', $request->body(), 's3cret');

        return $request->header('X-Piotrack-Signature')[0] === $expected
            && $request->header('X-Piotrack-Event')[0] === 'booking.created'
            && str_contains($request->body(), '"booking_id":7');
    });

    expect($wanted->fresh()->last_delivered_at)->not->toBeNull()
        ->and($wanted->fresh()->failure_count)->toBe(0)
        ->and($broken->fresh()->failure_count)->toBeGreaterThan(0)
        ->and($broken->fresh()->last_error)->toContain('500')
        ->and($other->fresh()->last_delivered_at)->toBeNull();
});

it('emits webhooks from real business events (booking created)', function () {
    app(CurrentOrganization::class)->set($this->org);
    WebhookEndpoint::create(['url' => 'https://receiver.test/hook', 'secret' => 's', 'events' => []]);
    BookingPage::create([
        'name' => 'Intro', 'slug' => 'intg-intro', 'meeting_type' => 'consultation',
        'duration_minutes' => 30, 'is_active' => true, 'user_id' => $this->owner->id,
    ]);
    app(CurrentOrganization::class)->forget();

    Http::fake(['receiver.test/*' => Http::response(['ok' => true])]);

    $this->post(route('public.booking.book', 'intg-intro'), [
        'name' => 'Web Hooke', 'email' => 'hooke@client.test',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://receiver.test/')
        && $request->header('X-Piotrack-Event')[0] === 'booking.created'
        && str_contains($request->body(), 'hooke@client.test'));
});

it('connects an OAuth provider through the generic flow with state verification', function () {
    config()->set('services.connectors.slack', [
        'client_id' => 'client-123',
        'client_secret' => 'shhh',
        'authorize_url' => 'https://slack.test/oauth/authorize',
        'token_url' => 'https://slack.test/oauth/token',
        'scopes' => 'chat:write',
    ]);

    // With config present the connector becomes connectable and redirects out.
    $redirect = $this->actingAs($this->owner)->get(route('integrations.oauth.redirect', 'slack'));
    $redirect->assertRedirect();
    $location = $redirect->headers->get('Location');
    expect($location)->toStartWith('https://slack.test/oauth/authorize?')
        ->and($location)->toContain('client_id=client-123')->toContain('state=');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

    // A forged state is rejected.
    $this->actingAs($this->owner)
        ->get(route('integrations.oauth.callback', ['provider' => 'slack', 'state' => 'forged', 'code' => 'abc']))
        ->assertRedirect(route('integrations.index'))->assertSessionHasErrors('provider');

    // The real state completes the exchange and stores tokens in the vault.
    $redirect = $this->actingAs($this->owner)->get(route('integrations.oauth.redirect', 'slack'));
    parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    Http::fake(['slack.test/oauth/token' => Http::response([
        'access_token' => 'xoxb-token', 'refresh_token' => 'xoxe-refresh', 'expires_in' => 3600,
    ])]);

    $this->actingAs($this->owner)
        ->get(route('integrations.oauth.callback', ['provider' => 'slack', 'state' => $query['state'], 'code' => 'good-code']))
        ->assertRedirect(route('integrations.index'))->assertSessionHasNoErrors();

    $integration = Integration::withoutGlobalScopes()->where('provider', 'slack')->firstOrFail();
    expect($integration->status)->toBe('connected')
        ->and($integration->credentials['access_token'])->toBe('xoxb-token')
        ->and($integration->credentials['refresh_token'])->toBe('xoxe-refresh');
});

it('keeps unconfigured OAuth connectors non-connectable', function () {
    // No services.connectors.slack config in this test.
    $this->actingAs($this->owner)
        ->get(route('integrations.oauth.redirect', 'slack'))
        ->assertRedirect(route('integrations.index'))->assertSessionHasErrors('provider');

    $connectors = collect(ConnectorRegistry::all());
    expect($connectors->firstWhere('key', 'slack')['connectable'])->toBeFalse()
        ->and($connectors->firstWhere('key', 'mailchimp')['connectable'])->toBeTrue();
});
