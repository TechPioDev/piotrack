<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatFlowVersion;
use App\Models\ChatWidget;
use App\Services\Chat\ChatFlowEngine;
use App\Services\Chat\ChatFlowTemplates;
use App\Services\Chat\ChatFlowValidator;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The conversation flow builder. Tenants compose the graph here rather than
 * editing JSON, and can run a real test conversation before publishing — the
 * test uses the same engine visitors hit, flagged as a preview so it never
 * reaches the CRM.
 */
class ChatFlowController extends Controller
{
    public function __construct(
        private readonly ChatFlowValidator $validator,
        private readonly ChatFlowTemplates $templates,
        private readonly ChatFlowEngine $engine,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(ChatWidget $widget): Response
    {
        $flow = $widget->flow ?? ['start' => null, 'nodes' => []];

        return Inertia::render('chat/flow/edit', [
            'widget' => [
                'id' => $widget->id,
                'name' => $widget->name,
                'status' => $widget->status,
            ],
            // The builder's widget switcher.
            'widgets' => ChatWidget::query()->orderBy('name')->get(['id', 'name', 'status'])
                ->map(fn (ChatWidget $w) => ['id' => $w->id, 'name' => $w->name, 'status' => $w->status])
                ->all(),
            'flow' => $flow,
            'validation' => $this->validator->validate($flow),
            'templates' => $this->templates->catalog(),
            'history' => ChatFlowVersion::query()
                ->where('chat_widget_id', $widget->id)
                ->with('author:id,name')
                ->latest('id')
                ->limit(ChatFlowVersion::KEEP)
                ->get()
                ->map(fn (ChatFlowVersion $version) => [
                    'id' => $version->id,
                    'steps' => $version->steps,
                    'note' => $version->note,
                    'published' => $version->published,
                    'author' => $version->author?->name,
                    'at' => $version->created_at?->toIso8601String(),
                ]),
            'assignees' => $widget->organization()->first()?->members()
                ->wherePivot('status', 'active')
                ->get(['users.id', 'users.name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                ->all() ?? [],
        ]);
    }

    public function update(Request $request, ChatWidget $widget): RedirectResponse
    {
        $data = $request->validate([
            'flow' => 'required|array',
            'flow.start' => 'nullable|string|max:100',
            'flow.nodes' => 'required|array',
            'publish' => 'sometimes|boolean',
        ]);

        $flow = $data['flow'];
        $result = $this->validator->validate($flow);

        // A draft may be saved at any time; publishing requires a valid graph so a
        // visitor is never dropped into a broken conversation.
        $publishing = (bool) ($data['publish'] ?? false);
        if ($publishing && ! $result['valid']) {
            return back()->withErrors([
                'flow' => 'This conversation cannot go live yet: '.$result['errors'][0]['message'],
            ]);
        }

        // A widget has one conversation, so once it is live a "draft" save is
        // what visitors get on their next message. Hold it to the same bar as
        // publishing - a question with no answers strands every visitor on it.
        if (! $publishing && $widget->status === 'active' && ! $result['valid']) {
            return back()->withErrors([
                'flow' => 'Not saved - this chat is live on your website, so changes reach visitors straight away. Fix this first: '.$result['errors'][0]['message'],
            ]);
        }

        // Keep what this replaces before it is gone. Saved first, so a save
        // that fails halfway leaves the history rather than losing both.
        $this->keepVersion($request, $widget, $publishing);

        $widget->flow = $flow;
        if ($publishing) {
            $widget->status = 'active';
        }
        $widget->save();

        $this->audit->log(
            $publishing ? 'chat.flow.published' : 'chat.flow.saved',
            ['steps' => count($flow['nodes'] ?? [])],
            resourceType: 'chat_widget',
            resourceId: (string) $widget->id,
        );

        return back()->with('status', $publishing ? 'Conversation published.' : 'Draft saved.');
    }

    /**
     * Keep the conversation as it stands, before a save overwrites it.
     *
     * The very first save of a brand-new widget has nothing worth keeping, and
     * a save that changes nothing is not a version either - a list full of
     * identical entries is worse than no list.
     */
    private function keepVersion(Request $request, ChatWidget $widget, bool $publishing): void
    {
        $current = $widget->flow ?? [];
        if (($current['nodes'] ?? []) === []) {
            return;
        }

        $latest = ChatFlowVersion::query()->where('chat_widget_id', $widget->id)->latest('id')->first();
        if ($latest !== null && $latest->flow == $current) {
            return;
        }

        ChatFlowVersion::create([
            'chat_widget_id' => $widget->id,
            'saved_by' => $request->user()?->id,
            'flow' => $current,
            'steps' => count($current['nodes'] ?? []),
            'published' => $widget->status === 'active',
            'note' => $publishing ? 'Before publishing' : 'Before a change',
        ]);

        // Old enough to be forgotten: the last two dozen is plenty to undo a
        // bad afternoon, and keeping every save of a busy widget is not free.
        $keep = ChatFlowVersion::query()
            ->where('chat_widget_id', $widget->id)
            ->latest('id')
            ->limit(ChatFlowVersion::KEEP)
            ->pluck('id');
        ChatFlowVersion::query()
            ->where('chat_widget_id', $widget->id)
            ->whereNotIn('id', $keep)
            ->delete();
    }

    /**
     * Put an earlier version back.
     *
     * It arrives as a draft rather than going live: restoring is a decision
     * about the editor, and publishing is a separate one about visitors. The
     * version being replaced is kept too, so a restore is itself undoable.
     */
    public function restoreVersion(Request $request, ChatWidget $widget, ChatFlowVersion $version): RedirectResponse
    {
        abort_if($version->chat_widget_id !== $widget->id, 404);

        $result = $this->validator->validate($version->flow);
        if ($widget->status === 'active' && ! $result['valid']) {
            return back()->withErrors([
                'flow' => 'Not restored - this chat is live, and that version has a problem: '.$result['errors'][0]['message'],
            ]);
        }

        $this->keepVersion($request, $widget, false);
        $widget->forceFill(['flow' => $version->flow])->save();

        $this->audit->log(
            'chat.flow.restored',
            ['version' => $version->id, 'steps' => $version->steps],
            resourceType: 'chat_widget',
            resourceId: (string) $widget->id,
        );

        return back()->with('status', 'Restored the conversation as it was on '.$version->created_at->toDayDateTimeString().'. Publish it when you are happy.');
    }

    /** Live validation while editing, without saving. */
    public function validateFlow(Request $request, ChatWidget $widget): JsonResponse
    {
        $data = $request->validate([
            'flow' => 'required|array',
            // start must be declared or validate() strips it, and the engine would
            // silently fall back to the default conversation.
            'flow.start' => 'nullable|string|max:100',
            'flow.nodes' => 'required|array',
        ]);

        return response()->json($this->validator->validate($data['flow']));
    }

    public function applyTemplate(Request $request, ChatWidget $widget): RedirectResponse
    {
        $data = $request->validate(['template' => 'required|string|max:60']);

        $flow = $this->templates->flow($data['template']);
        if ($flow === null) {
            return back()->withErrors(['template' => 'That template no longer exists.']);
        }

        $widget->update(['flow' => $flow]);
        $this->audit->log(
            'chat.flow.template_applied',
            ['template' => $data['template']],
            resourceType: 'chat_widget',
            resourceId: (string) $widget->id,
        );

        return back()->with('status', 'Template applied — edit it to suit your business.');
    }

    /**
     * Start (or continue) a preview conversation against the draft flow the
     * builder currently holds, using the real engine.
     */
    public function test(Request $request, ChatWidget $widget): JsonResponse
    {
        $data = $request->validate([
            'flow' => 'required|array',
            // start must be declared or validate() strips it, and the engine would
            // silently fall back to the default conversation.
            'flow.start' => 'nullable|string|max:100',
            'flow.nodes' => 'required|array',
            'token' => 'nullable|string|max:64',
            'option' => 'nullable|string|max:100',
            'value' => 'nullable|string|max:1000',
        ]);

        // Run against the draft in the builder, not the saved flow, so the tenant
        // tests what they are looking at. The widget is not mutated.
        $draft = clone $widget;
        $draft->flow = $data['flow'];
        // A preview must not be gated by the consent step; that is verified live.
        $draft->consent = ['required' => false];

        if (empty($data['token'])) {
            $conversation = ChatConversation::create([
                'chat_widget_id' => $widget->id,
                'status' => 'new',
                'is_preview' => true,
                'visitor_id' => 'preview',
            ]);

            return response()->json(['token' => $conversation->token, ...$this->engine->start($draft, $conversation)]);
        }

        $conversation = ChatConversation::query()
            ->where('chat_widget_id', $widget->id)
            ->where('is_preview', true)
            ->where('token', $data['token'])
            ->firstOrFail();

        return response()->json($this->engine->handle($draft, $conversation, [
            'option' => $data['option'] ?? null,
            'value' => $data['value'] ?? null,
        ]));
    }
}
