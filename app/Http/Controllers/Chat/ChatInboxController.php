<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Notifications\ChatMentionNotification;
use App\Services\Chat\ChatPresenceService;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Conversations inbox: list, transcript, agent replies, internal notes,
 * status + assignment. Authorization at the route layer.
 */
class ChatInboxController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CurrentOrganization $currentOrganization,
        private readonly ChatPresenceService $presence,
    ) {}

    public function index(Request $request): Response
    {
        $filter = $request->string('filter', 'all')->toString();

        $conversations = ChatConversation::query()
            // Builder previews are not real visitor conversations.
            ->where('is_preview', false)
            ->with(['widget:id,name', 'assignee:id,name', 'contact:id,first_name,last_name,email,lead_score'])
            ->when($filter === 'unassigned', fn ($q) => $q->whereNull('assignee_id'))
            ->when($filter === 'mine', fn ($q) => $q->where('assignee_id', $request->user()->id))
            ->when($filter === 'open', fn ($q) => $q->whereNotIn('status', ['closed', 'spam']))
            ->when($filter === 'closed', fn ($q) => $q->whereIn('status', ['closed', 'spam']))
            ->latest('last_message_at')
            ->limit(100)
            ->get()
            ->map(fn (ChatConversation $c) => [
                'id' => $c->id,
                'status' => $c->status,
                'lead_score' => $c->lead_score,
                'widget' => $c->widget?->name,
                'assignee' => $c->assignee?->name,
                'contact' => $c->contact ? [
                    'name' => trim($c->contact->first_name.' '.$c->contact->last_name),
                    'email' => $c->contact->email,
                ] : null,
                'answers' => Arr::except($c->answers ?? [], ['_node', '_consent', '_priority']),
                'priority' => ($c->answers['_priority'] ?? null) === 'high',
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ]);

        return Inertia::render('chat/inbox/index', [
            'conversations' => $conversations,
            'filter' => $filter,
            'presence' => [
                'me' => $this->presence->statusFor($request->user()),
                'roster' => $this->presence->roster($this->currentOrganization->get()),
            ],
        ]);
    }

    public function show(ChatConversation $conversation): Response
    {
        $conversation->load(['widget:id,name', 'assignee:id,name', 'contact', 'messages.author:id,name']);

        return Inertia::render('chat/inbox/show', [
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'lead_score' => $conversation->lead_score,
                'widget' => $conversation->widget?->name,
                'assignee' => $conversation->assignee ? ['id' => $conversation->assignee->id, 'name' => $conversation->assignee->name] : null,
                'contact' => $conversation->contact ? [
                    'id' => $conversation->contact->id,
                    'name' => trim($conversation->contact->first_name.' '.$conversation->contact->last_name),
                    'email' => $conversation->contact->email,
                    'lead_score' => $conversation->contact->lead_score,
                ] : null,
                'answers' => Arr::except($conversation->answers ?? [], ['_node', '_consent', '_priority']),
                'priority' => ($conversation->answers['_priority'] ?? null) === 'high',
                'is_live' => (bool) $conversation->is_live,
                'attribution' => $conversation->attribution,
                'created_at' => $conversation->created_at->toIso8601String(),
            ],
            'messages' => $conversation->messages->map(fn (ChatMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'body' => $m->body,
                'author' => $m->author?->name,
                'at' => $m->created_at->toIso8601String(),
            ]),
            'statuses' => ChatConversation::STATUSES,
            'presence' => [
                'me' => $this->presence->statusFor(request()->user()),
                'roster' => $this->presence->roster($this->currentOrganization->get()),
            ],
        ]);
    }

    public function reply(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $data = $request->validate(['body' => 'required|string|max:2000']);

        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'agent',
            'author_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        // An agent replying is itself a handoff: the visitor is now talking to a
        // person, so the bot must stop driving the conversation.
        $conversation->forceFill([
            'last_message_at' => now(),
            'assignee_id' => $conversation->assignee_id ?? $request->user()->id,
            'status' => $conversation->status === 'new' ? 'open' : $conversation->status,
            'is_live' => true,
        ])->save();

        return back();
    }

    public function note(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $data = $request->validate(['body' => 'required|string|max:2000']);

        $mentioned = $this->mentionedUsers($data['body']);

        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'note',
            'author_id' => $request->user()->id,
            'body' => $data['body'],
            'meta' => $mentioned === [] ? null : ['mentions' => array_column($mentioned, 'id')],
        ]);

        // Tell the people named in the note — a mention nobody sees is pointless.
        foreach ($mentioned as $user) {
            if ($user['id'] === $request->user()->id) {
                continue;
            }
            Notification::route('mail', $user['email'])->notify(
                new ChatMentionNotification($request->user()->name, $conversation->id, $data['body']),
            );
        }

        return back();
    }

    /**
     * Resolve "@Name" mentions in a note against active members of the tenant.
     * Longest names are matched first so "@Sarah Mitchell" wins over "@Sarah".
     *
     * @return list<array{id: int, name: string, email: string}>
     */
    private function mentionedUsers(string $body): array
    {
        if (! str_contains($body, '@')) {
            return [];
        }

        $members = $this->currentOrganization->get()?->members()
            ->wherePivot('status', 'active')
            ->get(['users.id', 'users.name', 'users.email']) ?? collect();

        $matched = [];
        foreach ($members->sortByDesc(fn ($u) => mb_strlen((string) $u->name)) as $user) {
            if (stripos($body, '@'.$user->name) !== false) {
                $matched[] = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
            }
        }

        return $matched;
    }

    /**
     * Poll for messages added since the agent last looked, so an open
     * conversation updates without a full page reload.
     */
    public function poll(Request $request, ChatConversation $conversation): JsonResponse
    {
        $since = (int) $request->query('since', '0');

        $messages = $conversation->messages()
            ->with('author:id,name')
            ->when($since > 0, fn ($q) => $q->where('id', '>', $since))
            ->orderBy('id')
            ->limit(100)
            ->get();

        return response()->json([
            'messages' => $messages->map(fn (ChatMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'body' => $m->body,
                'author' => $m->author?->name,
                'at' => $m->created_at?->toIso8601String(),
            ])->all(),
            'status' => $conversation->status,
            'is_live' => (bool) $conversation->is_live,
        ]);
    }

    public function update(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(ChatConversation::STATUSES)],
            'assignee_id' => 'sometimes|nullable|integer|exists:users,id',
        ]);

        $before = ['status' => $conversation->status, 'assignee_id' => $conversation->assignee_id];
        $conversation->update($data);
        $this->audit->log(
            'chat.conversation.updated',
            ['before' => $before, 'after' => $data],
            resourceType: 'chat_conversation',
            resourceId: (string) $conversation->id,
        );

        return back();
    }
}
