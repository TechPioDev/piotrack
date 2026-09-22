<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Jobs\EmailChatReplies;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Notifications\ChatMentionNotification;
use App\Services\Chat\ChatConversationSummarizer;
use App\Services\Chat\ChatPresenceService;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'answers' => Arr::except($c->answers ?? [], ['_node', '_consent', '_priority', '_ticket']),
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

    public function show(ChatConversation $conversation, ChatConversationSummarizer $summarizer): Response
    {
        $conversation->load(['widget:id,name', 'assignee:id,name', 'contact', 'messages.author:id,name']);

        // A live conversation is a takeover in progress: give the agent the
        // summary without a click. Anywhere else it stays on-demand, so
        // opening old conversations never spends AI credits by itself.
        if ($conversation->is_live && $conversation->summary === null) {
            $summarizer->summarize($conversation);
            $conversation->refresh();
        }

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
                'answers' => Arr::except($conversation->answers ?? [], ['_node', '_consent', '_priority', '_ticket']),
                'priority' => ($conversation->answers['_priority'] ?? null) === 'high',
                'ticket_id' => $conversation->answers['_ticket'] ?? null,
                'is_live' => (bool) $conversation->is_live,
                'summary' => $conversation->summary,
                'summary_generated_at' => $conversation->summary_generated_at?->toIso8601String(),
                'attribution' => $conversation->attribution,
                'created_at' => $conversation->created_at->toIso8601String(),
            ],
            'messages' => $conversation->messages->map(fn (ChatMessage $m) => $this->present($conversation, $m)),
            'statuses' => ChatConversation::STATUSES,
            'presence' => [
                'me' => $this->presence->statusFor(request()->user()),
                'roster' => $this->presence->roster($this->currentOrganization->get()),
            ],
        ]);
    }

    /**
     * Generate (or refresh) the AI summary on demand. Idempotent while nothing
     * new has been said; a failure reports plainly rather than erroring.
     */
    public function summarize(ChatConversation $conversation, ChatConversationSummarizer $summarizer): RedirectResponse
    {
        $summary = $summarizer->summarize($conversation);

        return back()->with('status', $summary !== null
            ? 'Summary updated.'
            : 'The AI could not produce a summary right now.');
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

        // If the visitor has left by the time this could have reached them in
        // the chat, it goes to them by email instead.
        EmailChatReplies::dispatch($conversation->id, (int) $conversation->organization_id)
            ->delay(now()->addMinutes(2));

        return back();
    }

    /**
     * A file the visitor sent in the chat. Always downloaded, never rendered
     * inline: it came from an anonymous website visitor.
     */
    public function file(ChatConversation $conversation, ChatMessage $message): StreamedResponse
    {
        $attachment = $message->meta['attachment'] ?? null;
        abort_unless(
            $message->chat_conversation_id === $conversation->id
                && is_array($attachment)
                && Storage::disk('local')->exists((string) ($attachment['path'] ?? '')),
            404,
        );

        return Storage::disk('local')->download((string) $attachment['path'], (string) ($attachment['name'] ?? 'attachment'), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function present(ChatConversation $conversation, ChatMessage $message): array
    {
        $attachment = $message->meta['attachment'] ?? null;

        return [
            'id' => $message->id,
            'role' => $message->role,
            'body' => $message->body,
            'author' => $message->author?->name,
            'at' => $message->created_at?->toIso8601String(),
            'attachment' => is_array($attachment) ? [
                'name' => (string) ($attachment['name'] ?? 'attachment'),
                'size' => (int) ($attachment['size'] ?? 0),
                'url' => route('chat.conversations.files.show', [$conversation, $message]),
            ] : null,
            'emailed' => isset($message->meta['emailed_at']),
        ];
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
            'messages' => $messages->map(fn (ChatMessage $m) => $this->present($conversation, $m))->all(),
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
