<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatMessage;
use App\Models\ChatWidget;
use Illuminate\Validation\ValidationException;

/**
 * The server-authoritative conversation runner. The widget only ever renders what
 * this engine hands it: the engine owns the cursor, validates every answer, adds
 * the score, and decides the next node — so a visitor cannot skip qualification
 * or fake a score from the client (spec: business rules & validation).
 *
 * The cursor and consent state live inside the conversation's `answers` JSON
 * under reserved underscore keys; collected field values sit beside them.
 */
class ChatFlowEngine
{
    private const CURSOR = '_node';

    private const CONSENT = '_consent';

    public function __construct(private readonly ChatCaptureService $capture,
        private readonly ChatHandoffService $handoff,
    ) {}

    /**
     * Start a conversation: walk from the flow's start node to the first
     * interactive node, recording the bot's messages.
     *
     * @return array{messages: list<array<string, mixed>>, node: ?array<string, mixed>, done: bool}
     */
    public function start(ChatWidget $widget, ChatConversation $conversation): array
    {
        $flow = $this->flowFor($widget);
        $answers = $conversation->answers ?? [];

        // Consent gate: when required, the very first prompt is the consent node;
        // no PII is stored until it is accepted (§14).
        $entry = $this->consentRequired($widget, $answers) ? '_consent_gate' : $flow['start'];

        return $this->advance($widget, $conversation, $flow, $entry);
    }

    /**
     * Apply one visitor reply to the node the SERVER says is current, then walk
     * to the next interactive node.
     *
     * @param  array{option?: string, value?: string}  $payload
     * @return array{messages: list<array<string, mixed>>, node: ?array<string, mixed>, done: bool}
     */
    public function handle(ChatWidget $widget, ChatConversation $conversation, array $payload): array
    {
        $flow = $this->flowFor($widget);
        $answers = $conversation->answers ?? [];
        $currentId = $answers[self::CURSOR] ?? null;

        // Live state is checked before the cursor: once a human has taken over
        // there is no scripted position to be at, and falling through to the
        // cursor check would restart the flow under the agent's feet.
        if ($conversation->is_live) {
            $body = trim((string) ($payload['value'] ?? ''));
            if ($body !== '') {
                $this->record($conversation, null, 'visitor', $body);
                $conversation->forceFill(['last_message_at' => now()])->save();
            }

            return ['messages' => [], 'node' => $this->liveNode(), 'done' => false, 'live' => true];
        }

        if ($currentId === null) {
            return $this->start($widget, $conversation);
        }

        // Consent acceptance is handled before any flow node. Only the two offered
        // options are valid: a malformed reply is rejected rather than silently
        // treated as a decline, which would strand the visitor's conversation.
        if ($currentId === '_consent_gate') {
            $choice = $payload['option'] ?? null;
            if (! in_array($choice, ['accept', 'decline'], true)) {
                throw ValidationException::withMessages(['option' => 'Please choose whether to continue.']);
            }

            if ($choice !== 'accept') {
                // Declined: end politely without storing anything.
                $this->say($conversation, 'No problem — you can reach us through the contact details on this website.');
                $conversation->forceFill(['status' => 'closed'])->save();

                return ['messages' => $this->drain(), 'node' => null, 'done' => true];
            }
            $answers[self::CONSENT] = true;
            $conversation->answers = $answers;
            $conversation->save();

            return $this->advance($widget, $conversation, $flow, $flow['start']);
        }

        $node = $flow['nodes'][$currentId] ?? null;
        if ($node === null) {
            throw ValidationException::withMessages(['node' => 'This conversation is out of date. Please reopen the chat.']);
        }

        $next = match ($node['type']) {
            'choice' => $this->applyChoice($conversation, $currentId, $node, $payload),
            'input' => $this->applyInput($conversation, $currentId, $node, $payload),
            default => $node['next'] ?? null,
        };

        return $this->advance($widget, $conversation, $flow, $next);
    }

    /**
     * Walk forward from $nodeId, emitting message nodes, until an interactive
     * node (choice/input/consent) or an end node is reached.
     *
     * @param  array{start: string, nodes: array<string, array<string, mixed>>}  $flow
     * @return array{messages: list<array<string, mixed>>, node: ?array<string, mixed>, done: bool}
     */
    private function advance(ChatWidget $widget, ChatConversation $conversation, array $flow, ?string $nodeId): array
    {
        $answers = $conversation->answers ?? [];

        while ($nodeId !== null) {
            if ($nodeId === '_consent_gate') {
                $answers[self::CURSOR] = '_consent_gate';
                $conversation->answers = $answers;
                $conversation->save();

                $consent = $widget->consent ?? [];
                $consentText = $consent['message']
                    ?? 'We use this chat to respond to your request and may retain the conversation.';

                // The consent copy has to be visible in the transcript, not just
                // implied by the buttons underneath it.
                $this->say($conversation, (string) $consentText, '_consent_gate');

                return [
                    'messages' => $this->drain(),
                    'node' => [
                        'id' => '_consent_gate',
                        'type' => 'consent',
                        'text' => $consent['message']
                            ?? 'We use this chat to respond to your request and may retain the conversation.',
                        'privacy_url' => $consent['privacy_url'] ?? null,
                        'options' => [
                            ['id' => 'accept', 'label' => 'Accept & continue'],
                            ['id' => 'decline', 'label' => 'No thanks'],
                        ],
                    ],
                    'done' => false,
                ];
            }

            $node = $flow['nodes'][$nodeId] ?? null;
            if ($node === null) {
                break; // Malformed flow: end gracefully rather than erroring the visitor.
            }

            if ($node['type'] === 'message') {
                $this->say($conversation, (string) $node['text'], $nodeId);
                $nodeId = $node['next'] ?? null;

                continue;
            }

            // Action nodes do their work and pass straight through; the visitor
            // never sees them.
            if (in_array($node['type'], ['score', 'tag', 'assign', 'condition'], true)) {
                $nodeId = $this->applyAction($conversation, $node);

                continue;
            }

            // Ask for a human. If one takes it, the conversation goes live and
            // the bot steps back; if not, the visitor is told what happens next
            // and the flow carries on collecting their details.
            if ($node['type'] === 'handoff') {
                $result = $this->handoff->request($widget, $conversation);
                $this->say($conversation, $result['message'], $nodeId);

                if ($result['live']) {
                    $conversation->refresh();

                    return [
                        'messages' => $this->drain(),
                        'node' => $this->liveNode(),
                        'done' => false,
                        'live' => true,
                        'agent' => $result['agent'],
                    ];
                }

                $nodeId = $node['next'] ?? null;

                continue;
            }

            if ($node['type'] === 'end') {
                $this->say($conversation, (string) ($node['text'] ?? 'Thanks for chatting with us!'), $nodeId);
                $answers = $conversation->answers ?? [];
                unset($answers[self::CURSOR]);
                $conversation->answers = $answers;
                $conversation->save();

                $result = $this->capture->complete($widget, $conversation, (string) ($node['outcome'] ?? 'lead'));

                return [
                    'messages' => $this->drain(),
                    'node' => null,
                    'done' => true,
                    ...$result,
                ];
            }

            // Interactive node: persist the cursor and hand a sanitized spec to the widget.
            $this->say($conversation, (string) $node['text'], $nodeId);
            $answers = $conversation->answers ?? [];
            $answers[self::CURSOR] = $nodeId;
            $conversation->answers = $answers;
            // A freshly created row has no status in memory (the default is applied
            // by the database), so treat "unset" as new rather than writing null.
            $conversation->status = in_array($conversation->status, [null, '', 'new'], true) ? 'open' : $conversation->status;
            $conversation->save();

            return [
                'messages' => $this->drain(),
                'node' => $this->publicNode($nodeId, $node),
                'done' => false,
            ];
        }

        // Fell off the graph — close politely.
        $this->say($conversation, 'Thanks for chatting with us!');
        $conversation->forceFill(['status' => 'closed'])->save();

        return ['messages' => $this->drain(), 'node' => null, 'done' => true];
    }

    /**
     * Silent action nodes: adjust score, tag the conversation, hint at an owner,
     * or branch on an answer already collected. Returns the next node id.
     *
     * @param  array<string, mixed>  $node
     */
    private function applyAction(ChatConversation $conversation, array $node): ?string
    {
        $answers = $conversation->answers ?? [];

        switch ($node['type']) {
            case 'score':
                $conversation->lead_score += (int) ($node['points'] ?? 0);
                break;

            case 'tag':
                $tag = trim((string) ($node['tag'] ?? ''));
                if ($tag !== '') {
                    $tags = (array) ($answers['_tags'] ?? []);
                    if (! in_array($tag, $tags, true)) {
                        $tags[] = $tag;
                    }
                    $answers['_tags'] = array_values($tags);
                    $conversation->answers = $answers;
                }
                break;

            case 'assign':
                if (! empty($node['assignee_id'])) {
                    $conversation->assignee_id = (int) $node['assignee_id'];
                }
                break;

            case 'condition':
                $conversation->save();

                return $this->matches($answers, $node)
                    ? ($node['next'] ?? null)
                    : ($node['otherwise'] ?? null);
        }

        $conversation->save();

        return $node['next'] ?? null;
    }

    /**
     * Evaluate a condition node against the answers collected so far.
     *
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $node
     */
    private function matches(array $answers, array $node): bool
    {
        $actual = $answers[(string) ($node['field'] ?? '')] ?? null;
        $expected = $node['value'] ?? null;

        return match ($node['operator'] ?? 'equals') {
            'not_equals' => (string) $actual !== (string) $expected,
            'contains' => $actual !== null && str_contains(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
            'is_set' => $actual !== null && $actual !== '',
            'gte' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            'lte' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            default => (string) $actual === (string) $expected,
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array{option?: string, value?: string}  $payload
     */
    private function applyChoice(ChatConversation $conversation, string $nodeId, array $node, array $payload): ?string
    {
        $optionId = $payload['option'] ?? null;

        $option = null;
        foreach ((array) ($node['options'] ?? []) as $candidate) {
            if (is_array($candidate) && ($candidate['id'] ?? null) === $optionId) {
                $option = $candidate;
                break;
            }
        }

        if ($option === null) {
            throw ValidationException::withMessages(['option' => 'Please pick one of the options.']);
        }

        $this->record($conversation, $nodeId, 'visitor', (string) $option['label'], ['option' => $option['id']]);

        $answers = $conversation->answers ?? [];
        if (! empty($node['field'])) {
            $answers[$node['field']] = $option['id'];
        }
        if (! empty($option['priority'])) {
            $answers['_priority'] = $option['priority'];
        }
        $conversation->answers = $answers;
        $conversation->lead_score += (int) ($option['score'] ?? 0);
        $conversation->save();

        return $option['next'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array{option?: string, value?: string}  $payload
     */
    private function applyInput(ChatConversation $conversation, string $nodeId, array $node, array $payload): ?string
    {
        $value = trim((string) ($payload['value'] ?? ''));
        $kind = $node['input'] ?? 'text';
        $optional = (bool) ($node['optional'] ?? false);

        if ($value === '' && $optional) {
            $this->record($conversation, $nodeId, 'visitor', '—', ['skipped' => true]);

            return $node['next'] ?? null;
        }

        if ($value === '') {
            throw ValidationException::withMessages(['value' => 'Please enter a value.']);
        }
        if (mb_strlen($value) > 500) {
            throw ValidationException::withMessages(['value' => 'That answer is too long.']);
        }
        if ($kind === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['value' => 'That does not look like a valid email address.']);
        }
        if ($kind === 'phone' && ! preg_match('/^[0-9 +().\-]{7,25}$/', $value)) {
            throw ValidationException::withMessages(['value' => 'That does not look like a valid phone number.']);
        }
        if ($kind === 'number' && ! is_numeric($value)) {
            throw ValidationException::withMessages(['value' => 'Please enter a number.']);
        }

        $this->record($conversation, $nodeId, 'visitor', $value);

        $answers = $conversation->answers ?? [];
        $answers[$node['field'] ?? $nodeId] = $value;
        $conversation->answers = $answers;
        $conversation->save();

        return $node['next'] ?? null;
    }

    /**
     * Sanitized node spec for the client: never leaks scores, priorities or branching.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function publicNode(string $id, array $node): array
    {
        $public = [
            'id' => $id,
            'type' => $node['type'],
            'text' => $node['text'] ?? '',
        ];

        if ($node['type'] === 'choice') {
            $options = [];
            foreach ((array) ($node['options'] ?? []) as $option) {
                if (is_array($option)) {
                    $options[] = ['id' => $option['id'] ?? '', 'label' => $option['label'] ?? ''];
                }
            }
            $public['options'] = $options;
        }

        if ($node['type'] === 'input') {
            $public['input'] = $node['input'] ?? 'text';
            $public['optional'] = (bool) ($node['optional'] ?? false);
        }

        return $public;
    }

    /** @var list<array<string, mixed>> */
    private array $pending = [];

    private function say(ChatConversation $conversation, string $text, ?string $nodeId = null): void
    {
        $message = $this->record($conversation, $nodeId, 'bot', $text);
        $this->pending[] = ['id' => $message->id, 'role' => 'bot', 'body' => $text];
    }

    /** @return list<array<string, mixed>> */
    private function drain(): array
    {
        $messages = $this->pending;
        $this->pending = [];

        return $messages;
    }

    /** @param array<string, mixed> $meta */
    private function record(ChatConversation $conversation, ?string $nodeId, string $role, string $body, array $meta = []): ChatMessage
    {
        $message = ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => $role,
            'body' => $body,
            'meta' => array_filter(['node' => $nodeId, ...$meta]),
        ]);
        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message;
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function flowFor(ChatWidget $widget): array
    {
        $flow = $widget->flow;

        if (is_array($flow) && isset($flow['start'], $flow['nodes'])) {
            return $flow;
        }

        return DefaultChatFlow::definition();
    }

    /** @param array<string, mixed> $answers */
    private function consentRequired(ChatWidget $widget, array $answers): bool
    {
        return (bool) ($widget->consent['required'] ?? false) && empty($answers[self::CONSENT]);
    }

    public function event(ChatWidget $widget, string $type, ?ChatConversation $conversation = null, ?string $nodeId = null): void
    {
        ChatEvent::create([
            'chat_widget_id' => $widget->id,
            'chat_conversation_id' => $conversation?->id,
            'type' => $type,
            'node_id' => $nodeId,
        ]);
    }

    /**
     * The "you are talking to a person now" node: a free-text box with no
     * scripted question behind it.
     *
     * @return array<string, mixed>
     */
    private function liveNode(): array
    {
        return [
            'id' => '_live',
            'type' => 'input',
            'text' => 'You are chatting with our team.',
            'input' => 'text',
            'optional' => false,
            'live' => true,
        ];
    }
}
