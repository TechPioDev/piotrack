<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatWidget;
use App\Services\Chat\DefaultChatFlow;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for chat widgets. Authorization is enforced at the route layer
 * (can:chat.widget.manage / can:chat.view); tenant scoping via BelongsToTenant.
 */
class ChatWidgetController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        $widgets = ChatWidget::query()
            ->withCount('conversations')
            ->latest()
            ->get()
            ->map(fn (ChatWidget $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'description' => $w->description,
                'status' => $w->status,
                'public_key' => $w->public_key,
                'conversations_count' => $w->conversations_count,
                'embed' => sprintf(
                    '<script src="%s" data-widget="%s" async></script>',
                    url('/widget/piotrack-chat.js'),
                    $w->public_key,
                ),
            ]);

        return Inertia::render('chat/widgets/index', ['widgets' => $widgets]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
        ]);

        $widget = ChatWidget::create([
            ...$data,
            'status' => 'draft',
            'flow' => DefaultChatFlow::definition(),
            'theme' => ['accent' => '#0bb39e', 'position' => 'bottom-right', 'title' => 'Chat with us'],
            'consent' => ['required' => false],
            'settings' => ['language' => 'en', 'mode' => 'bot'],
            'allowed_domains' => [],
        ]);

        $this->audit->log('chat.widget.created', ['name' => $widget->name], resourceType: 'chat_widget', resourceId: (string) $widget->id);

        return back()->with('status', 'Widget created.');
    }

    /** The widget's own settings: appearance, targeting, hours, consent, mode. */
    public function edit(ChatWidget $widget): Response
    {
        return Inertia::render('chat/widgets/settings', [
            'widget' => [
                'id' => $widget->id,
                'name' => $widget->name,
                'description' => $widget->description,
                'status' => $widget->status,
                'public_key' => $widget->public_key,
                'theme' => $widget->theme ?? [],
                'consent' => $widget->consent ?? [],
                'settings' => $widget->settings ?? [],
                'targeting' => $widget->targeting ?? [],
                'business_hours' => $widget->business_hours ?? [],
                'allowed_domains' => $widget->allowed_domains ?? [],
                'embed' => sprintf(
                    '<script src="%s" data-widget="%s" async></script>',
                    url('/widget/piotrack-chat.js'),
                    $widget->public_key,
                ),
            ],
        ]);
    }

    public function update(Request $request, ChatWidget $widget): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'description' => 'nullable|string|max:255',
            'status' => ['sometimes', Rule::in(ChatWidget::STATUSES)],
            'theme' => 'sometimes|array',
            'theme.title' => 'nullable|string|max:60',
            'theme.company' => 'nullable|string|max:60',
            'theme.accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.position' => ['nullable', Rule::in(['bottom-left', 'bottom-right'])],
            'consent' => 'sometimes|array',
            'consent.required' => 'sometimes|boolean',
            'consent.message' => 'nullable|string|max:500',
            'consent.privacy_url' => 'nullable|url|max:500',
            'settings' => 'sometimes|array',
            'settings.teaser' => 'nullable|string|max:120',
            // CHAT-041: the B teaser arms the A/B split, sticky per visitor.
            'settings.teaser_b' => 'nullable|string|max:120',
            'settings.teaser_delay' => 'nullable|integer|min:0|max:120',
            'settings.mode' => ['nullable', Rule::in(['bot', 'bot_then_human', 'live'])],
            'settings.language' => 'nullable|string|max:10',
            'settings.experiment' => 'nullable|string|max:60',
            'settings.variant' => 'nullable|string|max:60',
            'settings.fallback_contact' => 'nullable|string|max:200',
            'settings.suggested_questions' => 'sometimes|array|max:6',
            'settings.suggested_questions.*' => 'string|max:120',
            'allowed_domains' => 'sometimes|array|max:20',
            'allowed_domains.*' => 'string|max:255',
            'routing' => 'sometimes|array',
            'routing.assignee_id' => 'nullable|integer',
            // Page + behaviour targeting (§34, §35).
            'targeting' => 'sometimes|array',
            'targeting.include' => 'sometimes|array|max:50',
            'targeting.include.*' => 'string|max:255',
            'targeting.exclude' => 'sometimes|array|max:50',
            'targeting.exclude.*' => 'string|max:255',
            'targeting.devices' => 'sometimes|array',
            'targeting.devices.*' => Rule::in(['desktop', 'mobile']),
            'targeting.visitor' => ['nullable', Rule::in(['all', 'first', 'returning'])],
            'targeting.delay_seconds' => 'nullable|integer|min:0|max:300',
            'targeting.scroll_percent' => 'nullable|integer|min:0|max:100',
            'targeting.exit_intent' => 'sometimes|boolean',
            // Business hours (§33).
            'business_hours' => 'sometimes|array',
            'business_hours.timezone' => 'nullable|string|max:64',
            'business_hours.closed_message' => 'nullable|string|max:300',
            'business_hours.days' => 'sometimes|array',
        ]);

        $widget->update($data);
        $this->audit->log('chat.widget.updated', ['fields' => array_keys($data)], resourceType: 'chat_widget', resourceId: (string) $widget->id);

        return back()->with('status', 'Widget updated.');
    }

    public function destroy(ChatWidget $widget): RedirectResponse
    {
        $widget->delete();
        $this->audit->log('chat.widget.deleted', ['name' => $widget->name], resourceType: 'chat_widget', resourceId: (string) $widget->id);

        return back()->with('status', 'Widget deleted.');
    }
}
