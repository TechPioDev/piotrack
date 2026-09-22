<?php

namespace App\Http\Controllers\Public;

use App\Billing\Entitlements;
use App\Billing\Feature;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatMessage;
use App\Models\ChatWidget;
use App\Security\UploadScanner;
use App\Services\Chat\ChatCaptureService;
use App\Services\Chat\ChatFlowEngine;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The widget's public API. Unauthenticated and cross-origin (the widget lives on
 * the customer's own website): the tenant is resolved from the widget's opaque
 * public key, never from a session. CSRF-exempt via the wc/* prefix; protected by
 * throttling, a honeypot, an origin allow-list, and entitlement checks. Only
 * whitelisted config is ever returned — tenant internals never leave the server.
 */
class PublicChatController extends Controller
{
    /** Files one visitor may send in one conversation. */
    private const MAX_FILES = 10;

    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly Entitlements $entitlements,
        private readonly ChatFlowEngine $engine,
        private readonly ChatCaptureService $capture,
    ) {}

    /**
     * The widget's logo. Deliberately looser than resolve(): an <img> request
     * usually carries no Origin and often no Referer, so the domain allow-list
     * would break the image on the very sites it is meant for — and a logo is a
     * public brand asset, not tenant data. It still requires a live widget on a
     * plan with chat, so a paused or unpaid widget exposes nothing.
     */
    public function logo(string $publicKey): StreamedResponse
    {
        $widget = ChatWidget::withoutGlobalScope('tenant')
            ->where('public_key', $publicKey)
            ->where('status', 'active')
            ->first();

        $organization = $widget?->organization()->first();
        abort_if($widget === null || $organization === null || ! $this->entitlements->feature($organization, 'chat'), 404);
        abort_if($widget->logo_path === null || ! Storage::disk('local')->exists($widget->logo_path), 404);

        // The URL carries a version per upload, so a long browser cache is safe.
        return Storage::disk('local')->response($widget->logo_path, null, [
            'Cache-Control' => 'public, max-age=604800, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function config(Request $request, string $publicKey): JsonResponse
    {
        $widget = $this->resolve($request, $publicKey);

        $theme = $widget->theme ?? [];
        $consent = $widget->consent ?? [];
        $settings = $widget->settings ?? [];
        $targeting = $widget->targeting ?? [];

        return response()->json([
            'name' => $widget->name,
            'theme' => [
                'title' => $theme['title'] ?? 'Chat with us',
                'accent' => $theme['accent'] ?? '#0bb39e',
                'position' => in_array($theme['position'] ?? '', ['bottom-left', 'bottom-right'], true)
                    ? $theme['position']
                    : 'bottom-right',
                'company' => $theme['company'] ?? $widget->name,
                'logo_url' => $widget->logoUrl(),
            ],
            // CHAT-041: with a B teaser configured, the variant is sticky per
            // visitor (hash of the widget-local visitor key the widget sends).
            'teaser' => $this->teaserFor($settings, (string) $request->query('vid', '')),
            'teaser_variant' => $this->teaserVariant($settings, (string) $request->query('vid', '')),
            'teaser_delay' => (int) ($settings['teaser_delay'] ?? 4),
            'consent_required' => (bool) ($consent['required'] ?? false),
            'privacy_url' => $consent['privacy_url'] ?? null,
            'fallback_contact' => $settings['fallback_contact'] ?? null,
            'attachments' => (bool) ($settings['attachments'] ?? true),
            // "Powered by Piotrack" comes off only on a plan that includes
            // white-labelling - checked here too, so a downgrade restores it.
            'branding' => ! ((bool) ($settings['hide_branding'] ?? false) && $this->whiteLabelled()),
            // Display rules (§34, §35). These decide WHEN to show, never what the
            // visitor may do, so evaluating them in the browser is appropriate.
            'targeting' => [
                'include' => array_values(array_filter((array) ($targeting['include'] ?? []))),
                'exclude' => array_values(array_filter((array) ($targeting['exclude'] ?? []))),
                'devices' => array_values(array_filter((array) ($targeting['devices'] ?? []))),
                'visitor' => $targeting['visitor'] ?? 'all',
                'delay_seconds' => (int) ($targeting['delay_seconds'] ?? 0),
                'scroll_percent' => (int) ($targeting['scroll_percent'] ?? 0),
                'exit_intent' => (bool) ($targeting['exit_intent'] ?? false),
            ],
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

        // A returning visitor is not asked again for what they already told us.
        $known = $this->capture->knownAnswersFor($data['visitor'] ?? null);

        $conversation = ChatConversation::create([
            'chat_widget_id' => $widget->id,
            'status' => 'new',
            'visitor_id' => $data['visitor'] ?? null,
            'answers' => $known === [] ? null : $known,
            'attribution' => array_filter([
                'source' => 'website_chat',
                'page' => $data['page'] ?? null,
                'referrer' => $data['referrer'] ?? null,
                // CHAT-041: which teaser variant this visitor was served.
                'teaser_variant' => $this->teaserVariant($widget->settings ?? [], (string) ($data['visitor'] ?? '')),
                ...$utm,
            ]),
        ]);

        $this->engine->event($widget, 'start', $conversation);
        $result = $this->engine->start($widget, $conversation);
        $conversation->markSeen();

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

        $conversation->markSeen();
        $result = $this->engine->handle($widget, $conversation, $data);

        return response()->json($result);
    }

    /**
     * A file the visitor attaches to the chat - a screenshot of an error, a
     * document the team asked for. Checked like every upload (size, type, and
     * content that really is that type), kept private, and shown only to the
     * team. It is not an answer: the conversation stays where it was.
     */
    public function upload(Request $request, string $publicKey, string $token, UploadScanner $scanner): JsonResponse
    {
        $widget = $this->resolve($request, $publicKey);
        abort_unless((bool) (($widget->settings ?? [])['attachments'] ?? true), 404);

        $conversation = ChatConversation::query()
            ->where('chat_widget_id', $widget->id)
            ->where('token', $token)
            ->first();

        abort_if($conversation === null, 404);
        abort_if(in_array($conversation->status, ['closed', 'spam'], true), 410);

        // Nothing the visitor sends is stored before they have agreed to it.
        if ((bool) (($widget->consent ?? [])['required'] ?? false) && empty($conversation->answers['_consent'])) {
            throw ValidationException::withMessages(['file' => 'Please answer the privacy question before sending a file.']);
        }

        $sent = $conversation->messages()->where('role', 'visitor')->get(['meta'])
            ->filter(fn (ChatMessage $m) => isset($m->meta['attachment']))
            ->count();
        if ($sent >= self::MAX_FILES) {
            throw ValidationException::withMessages(['file' => sprintf('This chat already has %d files. Please email anything else to the team.', self::MAX_FILES)]);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:png,jpg,jpeg,gif,webp,pdf,txt,csv,doc,docx,xls,xlsx'],
        ], [
            'file.required' => 'Choose a file to send.',
            'file.uploaded' => 'That file could not be received. Files can be up to 5 MB.',
            'file.max' => 'Files can be up to 5 MB.',
            'file.mimes' => 'You can send images, PDFs, text, Word and Excel files.',
        ]);

        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $scanner->scan($file);

        $path = $file->store("org-{$widget->organization_id}/chat-files/{$conversation->id}", 'local');
        $name = mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', $file->getClientOriginalName())), 0, 120) ?: 'file';

        $message = ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'visitor',
            'body' => "📎 {$name}",
            'meta' => ['attachment' => [
                'path' => $path,
                'name' => $name,
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ]],
        ]);
        $conversation->forceFill(['last_message_at' => now()])->save();
        $conversation->markSeen();

        return response()->json(['message' => ['id' => $message->id, 'role' => 'visitor', 'body' => $message->body]]);
    }

    /**
     * Poll for anything the agent has said since the visitor last looked.
     *
     * There is no websocket server in this stack (and the product runs on an
     * isolated network), so a live chat is delivered by short polling. Only
     * visitor-facing roles are ever returned — internal notes stay internal.
     */
    public function poll(Request $request, string $publicKey, string $token): JsonResponse
    {
        $widget = $this->resolve($request, $publicKey);

        $conversation = ChatConversation::query()
            ->where('chat_widget_id', $widget->id)
            ->where('token', $token)
            ->first();

        abort_if($conversation === null, 404);

        $since = (int) $request->query('since', '0');

        // A reopened widget asks for the whole transcript, the visitor's own
        // answers included. Live polling leaves them out: the widget already
        // drew each one as it was sent (and older cached widgets would draw
        // them a second time, as if the bot had said them).
        $transcript = $since === 0 && $request->boolean('transcript');
        $roles = $transcript ? ['visitor', 'agent', 'bot', 'system'] : ['agent', 'bot', 'system'];

        $messages = $conversation->messages()
            ->whereIn('role', $roles)
            ->when($since > 0, fn ($q) => $q->where('id', '>', $since))
            ->orderBy('id', $transcript ? 'desc' : 'asc')
            ->limit($transcript ? 100 : 50)
            ->get(['id', 'role', 'body']);

        if ($transcript) {
            $messages = $messages->reverse()->values();
        }

        // The widget now holds everything up to the newest line it was sent.
        $conversation->markSeen(max($since, (int) $messages->max('id')));

        return response()->json([
            'messages' => $messages->map(fn ($m) => ['id' => $m->id, 'role' => $m->role, 'body' => $m->body])->all(),
            'live' => (bool) $conversation->is_live,
            'closed' => in_array($conversation->status, ['closed', 'spam'], true),
            // The question still waiting for an answer, so a widget reopened
            // mid-chat can show its options again instead of a bare text box.
            'node' => $this->engine->current($widget, $conversation),
        ]);
    }

    /**
     * Resolve the widget cross-tenant by its public key, enforce activity,
     * entitlement and the origin allow-list, then establish tenant context.
     */
    /**
     * CHAT-041: the teaser this visitor sees. Without a B variant everyone
     * gets A; with one, the split is a sticky hash of the visitor key.
     *
     * @param  array<string, mixed>  $settings
     */
    private function teaserFor(array $settings, string $visitorKey): ?string
    {
        return $this->teaserVariant($settings, $visitorKey) === 'b'
            ? ($settings['teaser_b'] ?? null)
            : ($settings['teaser'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function teaserVariant(array $settings, string $visitorKey): ?string
    {
        if (empty($settings['teaser_b'])) {
            return null;
        }

        return crc32($visitorKey) % 2 === 0 ? 'a' : 'b';
    }

    private function whiteLabelled(): bool
    {
        $organization = $this->currentOrganization->get();

        return $organization !== null && $this->entitlements->feature($organization, Feature::WhiteLabel);
    }

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
