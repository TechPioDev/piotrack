<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
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
            'flow' => $flow,
            'validation' => $this->validator->validate($flow),
            'templates' => $this->templates->catalog(),
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

        return back()->with('message', $publishing ? 'Conversation published.' : 'Draft saved.');
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

        return back()->with('message', 'Template applied — edit it to suit your business.');
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
