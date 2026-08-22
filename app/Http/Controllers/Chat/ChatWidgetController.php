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

        return back()->with('message', 'Widget created.');
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
            'allowed_domains' => 'sometimes|array|max:20',
            'allowed_domains.*' => 'string|max:255',
            'routing' => 'sometimes|array',
            'routing.assignee_id' => 'nullable|integer',
        ]);

        $widget->update($data);
        $this->audit->log('chat.widget.updated', ['fields' => array_keys($data)], resourceType: 'chat_widget', resourceId: (string) $widget->id);

        return back()->with('message', 'Widget updated.');
    }

    public function destroy(ChatWidget $widget): RedirectResponse
    {
        $widget->delete();
        $this->audit->log('chat.widget.deleted', ['name' => $widget->name], resourceType: 'chat_widget', resourceId: (string) $widget->id);

        return back()->with('message', 'Widget deleted.');
    }
}
