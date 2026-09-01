<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Integrations\ConnectorRegistry;
use App\Jobs\DeliverWebhook;
use App\Jobs\RunIntegrationSync;
use App\Models\Integration;
use App\Models\SyncRun;
use App\Models\WebhookEndpoint;
use App\Services\Integrations\OAuthFlow;
use App\Services\Integrations\WebhookDispatcher;
use App\Services\IntegrationService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connector management UI + actions (INTG). The catalog merges the code registry
 * with the org's stored connectors; connect/disconnect/reconnect/sync all flow
 * through {@see IntegrationService} so audit + health stay consistent. Syncs run
 * synchronously here for immediate feedback, and also expose a queued path.
 */
class IntegrationController extends Controller
{
    public function __construct(
        private IntegrationService $integrations,
        private CurrentOrganization $currentOrganization,
        private OAuthFlow $oauth,
    ) {}

    public function index(): Response
    {
        return Inertia::render('settings/integrations', [
            'connectors' => $this->integrations->catalog(),
            'webhookEvents' => WebhookEndpoint::EVENTS,
            'webhooks' => WebhookEndpoint::latest('id')->get()->map(fn (WebhookEndpoint $w) => [
                'id' => $w->id,
                'url' => $w->url,
                'events' => $w->events ?? [],
                'is_active' => $w->is_active,
                'failure_count' => $w->failure_count,
                'last_delivered_at' => $w->last_delivered_at?->toIso8601String(),
                'last_error' => $w->last_error,
            ]),
            'recentRuns' => SyncRun::with('integration:id,provider,name')
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn (SyncRun $run) => [
                    'id' => $run->id,
                    'provider' => $run->integration?->provider,
                    'provider_name' => $run->integration?->name,
                    'status' => $run->status,
                    'records' => $run->records,
                    'error' => $run->error,
                    'started_at' => $run->started_at?->toIso8601String(),
                    'finished_at' => $run->finished_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_column(ConnectorRegistry::all(), 'key'))],
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $credentials = isset($data['api_key']) && $data['api_key'] !== ''
            ? ['api_key' => $data['api_key']]
            : [];

        $this->integrations->connect($data['provider'], $credentials);

        return back()->with('status', __(':name connected.', ['name' => ConnectorRegistry::name($data['provider'])]));
    }

    public function disconnect(Integration $integration): RedirectResponse
    {
        $this->integrations->disconnect($integration);

        return back()->with('status', __(':name disconnected.', ['name' => $integration->name]));
    }

    public function reconnect(Integration $integration): RedirectResponse
    {
        $this->integrations->reconnect($integration);

        return back()->with('status', __(':name reconnected.', ['name' => $integration->name]));
    }

    public function sync(Integration $integration): RedirectResponse
    {
        $run = $this->integrations->sync($integration);

        return back()->with('status', $run->status === 'success'
            ? __('Synced :n records from :name.', ['n' => $run->records, 'name' => $integration->name])
            : __('Sync failed: :error', ['error' => $run->error]));
    }

    /**
     * Queue a background sync (INTG-003) — proves the async path without blocking.
     */
    public function queueSync(Integration $integration): RedirectResponse
    {
        RunIntegrationSync::dispatch($integration->id, $this->currentOrganization->id());

        return back()->with('status', __('Sync queued for :name.', ['name' => $integration->name]));
    }

    // ---- Outbound webhooks (INTG-009): the generic integration surface ----

    public function storeWebhook(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:https', 'max:500'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string', Rule::in(WebhookEndpoint::EVENTS)],
        ]);

        $secret = 'whsec_'.Str::random(40);
        WebhookEndpoint::create([
            'url' => $data['url'],
            'secret' => $secret,
            'events' => $data['events'] ?? [],
        ]);

        // Shown exactly once — deliveries are signed with it from now on.
        return back()->with('status', __('Webhook added. Signing secret (copy it now): :secret', ['secret' => $secret]));
    }

    public function destroyWebhook(WebhookEndpoint $webhook): RedirectResponse
    {
        $webhook->delete();

        return back()->with('status', __('Webhook removed.'));
    }

    public function testWebhook(WebhookEndpoint $webhook, WebhookDispatcher $webhooks): RedirectResponse
    {
        DeliverWebhook::dispatch($webhook->id, 'ping', ['message' => 'Piotrack webhook test'], now()->toIso8601String());

        return back()->with('status', __('Test delivery queued — check the endpoint, then refresh for delivery status.'));
    }

    // ---- Generic OAuth2 connect flow (INTG-001) ----

    public function oauthRedirect(string $provider): RedirectResponse
    {
        $connector = ConnectorRegistry::find($provider);
        abort_if($connector === null || $connector['auth_type'] !== 'oauth', 404);

        if (! $this->oauth->isConfigured($provider)) {
            return redirect()->route('integrations.index')
                ->withErrors(['provider' => __(':name is not available yet — its OAuth app is not configured.', ['name' => $connector['name']])]);
        }

        $state = Str::random(40);
        session()->put("oauth_state.{$provider}", $state);

        return redirect()->away($this->oauth->authorizeUrl($provider, $state));
    }

    public function oauthCallback(Request $request, string $provider): RedirectResponse
    {
        $connector = ConnectorRegistry::find($provider);
        abort_if($connector === null || $connector['auth_type'] !== 'oauth', 404);

        $expected = (string) session()->pull("oauth_state.{$provider}", '');
        if ($expected === '' || ! hash_equals($expected, (string) $request->query('state', ''))) {
            return redirect()->route('integrations.index')->withErrors(['provider' => __('OAuth state mismatch — please try connecting again.')]);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('integrations.index')->withErrors(['provider' => __('Authorization was cancelled or denied.')]);
        }

        try {
            $tokens = $this->oauth->exchange($provider, $code);
        } catch (\Throwable $e) {
            return redirect()->route('integrations.index')->withErrors(['provider' => __('Connection failed: :error', ['error' => $e->getMessage()])]);
        }

        $this->integrations->connect($provider, $tokens);

        return redirect()->route('integrations.index')
            ->with('status', __(':name connected.', ['name' => $connector['name']]));
    }
}
