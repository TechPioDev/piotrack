<?php

namespace App\Http\Controllers\Public;

use App\Billing\Entitlements;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatWidget;
use App\Services\Chat\ChatFlowEngine;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The widget's public API. Unauthenticated and cross-origin (the widget lives on
 * the customer's own website): the tenant is resolved from the widget's opaque
 * public key, never from a session. CSRF-exempt via the wc/* prefix; protected by
 * throttling, a honeypot, an origin allow-list, and entitlement checks. Only
 * whitelisted config is ever returned — tenant internals never leave the server.
 */
class PublicChatController extends Controller
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly Entitlements $entitlements,
        private readonly ChatFlowEngine $engine,
    ) {}

    public function config(Request $request, string $publicKey): JsonResponse
    {
        $widget = $this->resolve($request, $publicKey);

        $theme = $widget->theme ?? [];
        $consent = $widget->consent ?? [];
        $settings = $widget->settings ?? [];

        return response()->json([
            'name' => $widget->name,
            'theme' => [
                'title' => $theme['title'] ?? 'Chat with us',
                'accent' => $theme['accent'] ?? '#0bb39e',
                'position' => in_array($theme['position'] ?? '', ['bottom-left', 'bottom-right'], true)
                    ? $theme['position']
                    : 'bottom-right',
                'company' => $theme['company'] ?? $widget->name,
            ],
            'teaser' => $settings['teaser'] ?? null,
            'consent_required' => (bool) ($consent['required'] ?? false),
            'privacy_url' => $consent['privacy_url'] ?? null,
        ]);
    }

    public function event(Request $request, string $publicKey): JsonResponse
    {
        $widget = $this->resolve($request, $publicKey);

        $data = $request->validate([
            'type' => 'required|in:impression,open,dropoff',
            'node' => 'nullable|string|max:100',
        ]);

        ChatEvent::create([
            'chat_widget_id' => $widget->id,
            'type' => $data['type'],
            'node_id' => $data['node'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function start(Request $request, string $publicKey): JsonResponse
    {
        $widget = $this->resolve($request, $publicKey);

        // Honeypot: bots fill the hidden field; pretend success, store nothing.
        if ($request->filled('website')) {
            return response()->json(['token' => 'cv_ok', 'messages' => [], 'node' => null, 'done' => true]);
        }

        $data = $request->validate([
            'visitor' => 'nullable|string|max:64',
            'page' => 'nullable|string|max:500',
            'referrer' => 'nullable|string|max:500',
            'utm' => 'nullable|array',
            'utm.*' => 'nullable|string|max:200',
        ]);

        $utm = [];
        foreach (['source', 'medium', 'campaign', 'term', 'content'] as $key) {
            $value = $data['utm'][$key] ?? null;
            if (is_string($value) && $value !== '') {
                $utm['utm_'.$key] = $value;
            }
        }

        $conversation = ChatConversation::create([
            'chat_widget_id' => $widget->id,
            'status' => 'new',
            'visitor_id' => $data['visitor'] ?? null,
            'attribution' => array_filter([
                'source' => 'website_chat',
                'page' => $data['page'] ?? null,
                'referrer' => $data['referrer'] ?? null,
                ...$utm,
            ]),
        ]);

        $this->engine->event($widget, 'start', $conversation);
        $result = $this->engine->start($widget, $conversation);

        return response()->json(['token' => $conversation->token, ...$result]);
    }

    public function message(Request $request, string $publicKey, string $token): JsonResponse
    {
        $widget = $this->resolve($request, $publicKey);

        $conversation = ChatConversation::query()
            ->where('chat_widget_id', $widget->id)
            ->where('token', $token)
            ->first();

        abort_if($conversation === null, 404);
        abort_if(in_array($conversation->status, ['closed', 'spam'], true), 410);

        $data = $request->validate([
            'option' => 'nullable|string|max:100',
            'value' => 'nullable|string|max:1000',
        ]);

        $result = $this->engine->handle($widget, $conversation, $data);

        return response()->json($result);
    }

    /**
     * Resolve the widget cross-tenant by its public key, enforce activity,
     * entitlement and the origin allow-list, then establish tenant context.
     */
    private function resolve(Request $request, string $publicKey): ChatWidget
    {
        $widget = ChatWidget::withoutGlobalScope('tenant')
            ->where('public_key', $publicKey)
            ->where('status', 'active')
            ->first();

        abort_if($widget === null, 404);

        $organization = $widget->organization()->first();
        abort_if($organization === null, 404);

        // The owning tenant's plan must include the chat feature.
        abort_unless($this->entitlements->feature($organization, 'chat'), 404);

        // Domain allow-list (§39): when configured, the browser Origin must match.
        $allowed = array_filter((array) ($widget->allowed_domains ?? []));
        if ($allowed !== []) {
            $origin = parse_url((string) $request->headers->get('Origin'), PHP_URL_HOST)
                ?? parse_url((string) $request->headers->get('Referer'), PHP_URL_HOST);
            $ok = $origin !== null && collect($allowed)->contains(
                fn (string $domain) => strcasecmp($domain, $origin) === 0
                    || str_ends_with(strtolower($origin), '.'.strtolower($domain)),
            );
            abort_unless($ok, 403);
        }

        $this->currentOrganization->set($organization);

        return $widget;
    }
}
