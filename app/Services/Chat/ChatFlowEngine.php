<?php

namespace App\Services\Chat;

use App\Models\BookingPage;
use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatMessage;
use App\Models\ChatWidget;
use App\Models\ServiceLine;
use App\Services\Ai\AiGateway;
use App\Services\Sales\BookingService;
use App\Support\UrlGuard;
use Illuminate\Support\Facades\Http;
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

    public function __construct(private readonly ChatKnowledge $knowledge,
        private readonly ChatCaptureService $capture,
        private readonly ChatHandoffService $handoff,
        private readonly ChatBookingSlots $slots,
        private readonly BookingService $booking,
        private readonly AiGateway $ai,
    ) {}

    /**
     * Start a conversation: walk from the flow's start node to the first
     * interactive node, recording the bot's messages.
     *
     * @return array{messages: list<array<string, mixed>>, node: ?array<string, mixed>, done: bool}
     */
    public function start(ChatWidget $widget, ChatConversation $conversation): array
    {
        $this->known = $conversation->answers ?? [];
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
        $this->known = $conversation->answers ?? [];
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
            $payload = $this->typedOption($payload, $this->consentNode($widget)['options']);
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
            $this->known = $answers;
            $conversation->save();

            return $this->advance($widget, $conversation, $flow, $flow['start']);
        }

        $node = $flow['nodes'][$currentId] ?? null;
        if ($node === null) {
            throw ValidationException::withMessages(['node' => 'This conversation is out of date. Please reopen the chat.']);
        }

        // The message box stays open on every step, so a visitor may type
        // where a button was offered. Match it to the answer it names.
        if ($node['type'] === 'choice') {
            $payload = $this->typedOption($payload, $this->publicNode($currentId, $node)['options']);
        } elseif ($node['type'] === 'booking') {
            $page = BookingPage::query()->where('is_active', true)->first();
            $payload = $this->typedOption($payload, $this->bookingNode($currentId, $node, $page !== null ? $this->slots->available($page) : [])['options']);
        }

        $next = match ($node['type']) {
            'choice' => $this->applyChoice($conversation, $currentId, $node, $payload),
            'input' => $this->applyInput($conversation, $currentId, $node, $payload),
            'booking' => $this->applyBooking($widget, $conversation, $currentId, $node, $payload),
            'ai' => $this->applyAi($widget, $conversation, $currentId, $node, $payload),
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
                $this->known = $answers;
                $conversation->save();

                $consent = $widget->consent ?? [];
                $consentText = $consent['message']
                    ?? 'We use this chat to respond to your request and may retain the conversation.';

                // The consent copy has to be visible in the transcript, not just
                // implied by the buttons underneath it.
                $this->say($conversation, (string) $consentText, '_consent_gate');

                return [
                    'messages' => $this->drain(),
                    'node' => $this->consentNode($widget),
                    'done' => false,
                ];
            }

            $node = $flow['nodes'][$nodeId] ?? null;
            if ($node === null) {
                break; // Malformed flow: end gracefully rather than erroring the visitor.
            }

            if ($node['type'] === 'message') {
                $this->say($conversation, (string) $node['text'], $nodeId, $node['delay'] ?? null);
                $nodeId = $node['next'] ?? null;

                continue;
            }

            // Action nodes do their work and pass straight through; the visitor
            // never sees them.
            if (in_array($node['type'], ['score', 'tag', 'assign', 'condition'], true)) {
                $nodeId = $this->applyAction($conversation, $node);

                continue;
            }

            // The owner's own system, called while the visitor waits.
            if ($node['type'] === 'webhook') {
                $nodeId = $this->applyWebhook($widget, $conversation, $nodeId, $node);

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
                $this->known = $answers;
                $conversation->save();

                $result = $this->capture->complete($widget, $conversation, (string) ($node['outcome'] ?? 'lead'));

                return [
                    'messages' => $this->drain(),
                    'node' => null,
                    'done' => true,
                    ...$result,
                ];
            }

            // In-chat booking (CHAT-023). The widget sees an ordinary choice
            // node — slots are just buttons — so no client change is needed and
            // an old cached widget still works. Guards fall through silently:
            // already booked, no booking page, or nothing free all continue to
            // the fallback path, which hands over the booking-page link instead.
            if ($node['type'] === 'booking') {
                if (($answers['_booking'] ?? null) !== null) {
                    $nodeId = $node['next'] ?? null;

                    continue;
                }

                $page = BookingPage::query()->where('is_active', true)->first();
                $free = $page !== null ? $this->slots->available($page) : [];
                if ($free === []) {
                    $nodeId = $node['fallback'] ?? $node['next'] ?? null;

                    continue;
                }

                $this->say($conversation, (string) ($node['text'] ?? 'Pick a time that suits you:'), $nodeId);
                $answers[self::CURSOR] = $nodeId;
                $conversation->answers = $answers;
                $this->known = $answers;
                $conversation->status = in_array($conversation->status, [null, '', 'new'], true) ? 'open' : $conversation->status;
                $conversation->save();

                return [
                    'messages' => $this->drain(),
                    'node' => $this->bookingNode($nodeId, $node, $free),
                    'done' => false,
                ];
            }

            // Progressive profiling (§17): never re-ask something already known
            // about this visitor. The value is kept; only the question is skipped.
            if ($node['type'] === 'input') {
                $field = (string) ($node['field'] ?? '');
                $known = $field !== '' ? ($answers[$field] ?? null) : null;
                if (is_string($known) && trim($known) !== '') {
                    $nodeId = $node['next'] ?? null;

                    continue;
                }
            }

            // Interactive node: persist the cursor and hand a sanitized spec to the widget.
            $this->say($conversation, (string) $node['text'], $nodeId);
            $answers = $conversation->answers ?? [];
            $answers[self::CURSOR] = $nodeId;
            $conversation->answers = $answers;
            $this->known = $answers;
            // A freshly created row has no status in memory (the default is applied
            // by the database), so treat "unset" as new rather than writing null.
            $conversation->status = in_array($conversation->status, [null, '', 'new'], true) ? 'open' : $conversation->status;
            $conversation->save();

            return [
                'messages' => $this->drain(),
                'node' => $this->presentNode($widget, $nodeId, $node),
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
                    $this->known = $answers;
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
        $this->known = $answers;
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
        $this->known = $answers;
        $conversation->save();

        return $node['next'] ?? null;
    }

    /**
     * A picked booking slot: validate against live availability, book it through
     * the same service the public booking page uses, confirm in the transcript.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $payload
     */
    private function applyBooking(ChatWidget $widget, ChatConversation $conversation, string $nodeId, array $node, array $payload): ?string
    {
        $choice = (string) ($payload['option'] ?? '');
        $answers = $conversation->answers ?? [];

        if ($choice === 'none') {
            $this->record($conversation, $nodeId, 'visitor', 'None of these work');

            return $node['fallback'] ?? $node['next'] ?? null;
        }

        $page = BookingPage::query()->where('is_active', true)->first();
        if ($page === null) {
            return $node['fallback'] ?? $node['next'] ?? null;
        }

        // The list the visitor saw may be stale; the server re-derives what is
        // free and refuses anything else - including invented slot ids.
        $at = $this->slots->resolve($page, $choice);
        if ($at === null) {
            $this->say($conversation, 'Sorry - that time was just taken. Here are the times still free:', $nodeId);

            return $nodeId; // re-present with fresh slots
        }

        $email = trim((string) ($answers['email'] ?? ''));
        if ($email === '') {
            // A booking needs somewhere to send the confirmation. Misplaced
            // node (before capture): fall through to the link path.
            return $node['fallback'] ?? $node['next'] ?? null;
        }

        $label = $at->format('D j M H:i');
        $this->record($conversation, $nodeId, 'visitor', $label);

        // A builder preview must never create a real booking.
        if ($conversation->is_preview) {
            $answers['_booking'] = $at->toIso8601String();
            $conversation->answers = $answers;
            $this->known = $answers;
            $conversation->save();
            $this->say($conversation, "(Preview) You'd be booked for {$label}.", $nodeId);

            return $node['next'] ?? null;
        }

        $name = trim(($answers['first_name'] ?? '').' '.($answers['last_name'] ?? '')) ?: 'Website visitor';
        $this->booking->book($page, [
            'name' => $name,
            'email' => $email,
            'scheduled_at' => $at,
            'source' => 'website_chat',
            'notes' => 'Booked from the chat widget.',
        ]);

        $answers = $conversation->answers ?? [];
        $answers['_booking'] = $at->toIso8601String();
        $conversation->answers = $answers;
        $this->known = $answers;
        $conversation->save();

        $this->event($widget, 'meeting', $conversation, $nodeId);
        $this->say($conversation, "You're booked for {$label}. A confirmation is on its way to {$email}.", $nodeId);

        return $node['next'] ?? null;
    }

    /**
     * A free-text question, answered by the AI through the same gateway every
     * other AI feature uses - credit limits, cost recording and audit included.
     *
     * The visitor must never see an error: any failure, from exhausted credits
     * to a provider outage, degrades to the capture path so a human follows up.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $payload
     */
    private function applyAi(ChatWidget $widget, ChatConversation $conversation, string $nodeId, array $node, array $payload): ?string
    {
        $question = trim((string) ($payload['value'] ?? ''));
        if ($question === '') {
            throw ValidationException::withMessages(['value' => 'Please type a question.']);
        }
        if (mb_strlen($question) > 500) {
            throw ValidationException::withMessages(['value' => 'That question is a little long - could you shorten it?']);
        }

        $this->record($conversation, $nodeId, 'visitor', $question);

        // A builder preview answers deterministically and spends nothing.
        if ($conversation->is_preview) {
            $this->say($conversation, '(Preview) The AI assistant answers here once the conversation is live.', $nodeId);

            return $node['next'] ?? null;
        }

        $answers = $conversation->answers ?? [];
        $turns = (int) ($answers['_ai_turns'] ?? 0);
        if ($turns >= 5) {
            $this->say($conversation, 'Let me get a person to pick this up properly - a few quick details first.', $nodeId);

            return $node['fallback'] ?? $node['next'] ?? null;
        }

        try {
            $organization = $widget->organization()->first();
            // The answer comes from what this business has published, not from
            // what a model believes about IT companies in general.
            $knowledge = $this->knowledge->forQuestion($question, $widget);

            $completion = $this->ai->run('chat.answer', 'chat.answer', [
                'question' => $question,
                'company' => (string) ($organization->name ?? 'this company'),
                // Older prompt versions still ask for the services on their own.
                'services' => ServiceLine::query()->orderBy('name')->limit(15)->pluck('name')->implode(', ') ?: 'IT services',
                'knowledge' => $knowledge['text'] !== ''
                    ? $knowledge['text']
                    : 'Nothing has been published yet, so you have no facts about this company.',
            ]);

            $answers['_ai_turns'] = $turns + 1;
            $conversation->answers = $answers;
            $this->known = $answers;
            $conversation->save();

            // What it drew on is kept with the line, so the team can see where
            // an answer came from when they read the transcript.
            $message = $this->record($conversation, $nodeId, 'bot', trim($completion->text), $knowledge['sources'] !== [] ? ['sources' => $knowledge['sources']] : []);
            $this->pending[] = ['id' => $message->id, 'role' => 'bot', 'body' => trim($completion->text)];

            return $node['next'] ?? null;
        } catch (\Throwable) {
            // Exhausted credits, no provider, an outage: all the same to the
            // visitor. Hand over to capture so a person answers instead.
            $this->say($conversation, "I can't answer that one right now - let me take your details and a person will come straight back to you.", $nodeId);

            return $node['fallback'] ?? $node['next'] ?? null;
        }
    }

    /**
     * Hand what the visitor has said to the owner's own system, mid-conversation.
     *
     * This is how a chat reaches a PSA, a Zapier hook or an internal API while
     * the visitor is still there: it posts the answers so far, waits a moment,
     * and may keep one value out of the reply for a later step to use ("your
     * account is in good standing", a ticket number, a quote).
     *
     * The URL is the tenant's, so it goes through the SSRF guard first: nobody
     * points this at 169.254.169.254 or at a service on our private network.
     * Anything that fails - refused, slow, broken - takes the fallback path if
     * the flow has one and otherwise simply carries on, because a visitor must
     * never be stranded by someone else's server having a bad day.
     *
     * @param  array<string, mixed>  $node
     */
    private function applyWebhook(ChatWidget $widget, ChatConversation $conversation, string $nodeId, array $node): ?string
    {
        $url = trim((string) ($node['url'] ?? ''));
        $onwards = $node['next'] ?? null;
        $failed = $node['fallback'] ?? $onwards;

        // A preview must not call anyone's live system.
        if ($conversation->is_preview || $url === '') {
            return $onwards;
        }

        try {
            app(UrlGuard::class)->assertFetchable($url);
        } catch (\RuntimeException $e) {
            $this->record($conversation, $nodeId, 'system', 'Step skipped: '.$e->getMessage(), ['internal' => true]);

            return $failed;
        }

        try {
            $response = Http::asJson()
                ->withoutRedirecting()
                ->timeout((int) ($node['timeout'] ?? 5))
                ->withHeaders(array_filter(['X-Piotrack-Widget' => (string) $widget->id]))
                ->post($url, [
                    'conversation' => $conversation->token,
                    'widget' => $widget->name,
                    'page' => $conversation->attribution['page'] ?? null,
                    'answers' => $this->visibleAnswers($conversation->answers ?? []),
                ]);
        } catch (\Throwable $e) {
            $this->record($conversation, $nodeId, 'system', 'Step could not reach '.parse_url($url, PHP_URL_HOST).': '.$e->getMessage(), ['internal' => true]);

            return $failed;
        }

        if ($response->failed()) {
            $this->record($conversation, $nodeId, 'system', 'Step got '.$response->status().' from '.parse_url($url, PHP_URL_HOST), ['internal' => true]);

            return $failed;
        }

        // Keep one value from the reply, if the step was asked to.
        $field = trim((string) ($node['field'] ?? ''));
        $path = trim((string) ($node['path'] ?? ''));
        if ($field !== '' && $path !== '') {
            $value = data_get($response->json(), $path);
            if (is_scalar($value)) {
                $answers = $conversation->answers ?? [];
                $answers[$field] = (string) $value;
                $conversation->answers = $answers;
                $this->known = $answers;
                $conversation->save();
            }
        }

        return $onwards;
    }

    /**
     * What the visitor told us, without the engine's own bookkeeping keys.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function visibleAnswers(array $answers): array
    {
        return array_filter($answers, fn (string $key): bool => ! str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY);
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
            'text' => $this->fill((string) ($node['text'] ?? '')),
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

        // The widget predates these types, so each is presented as a shape it
        // already renders: an ai node is a text box. (Booking has its own
        // bookingNode(), because the options are live slots.)
        if ($node['type'] === 'ai') {
            $public['type'] = 'input';
            $public['input'] = 'text';
            $public['optional'] = false;
        }

        return $public;
    }

    /**
     * A visitor who typed instead of tapping: accept the reply when it plainly
     * names exactly one of the offered answers - its label or its id, ignoring
     * case and punctuation ("pricing", "book demo", "yes"), or a unique part of
     * a label. Anything else is refused, with the buttons still on screen.
     * A tapped answer passes through untouched.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{id: mixed, label: mixed}>  $options
     * @return array<string, mixed>
     */
    private function typedOption(array $payload, array $options): array
    {
        $typed = $this->comparable((string) ($payload['value'] ?? ''));
        if (($payload['option'] ?? '') !== '' || $typed === '') {
            return $payload;
        }

        $exact = [];
        $partial = [];
        foreach ($options as $option) {
            $label = $this->comparable((string) $option['label']);
            if ($typed === $label || $typed === $this->comparable((string) $option['id'])) {
                $exact[] = (string) $option['id'];
            } elseif (mb_strlen($typed) >= 3 && (str_contains($label, $typed) || (mb_strlen($label) >= 4 && str_contains($typed, $label)))) {
                $partial[] = (string) $option['id'];
            }
        }

        $match = match (true) {
            count($exact) === 1 => $exact[0],
            $exact === [] && count($partial) === 1 => $partial[0],
            default => null,
        };

        if ($match === null) {
            throw ValidationException::withMessages(['option' => 'Please tap one of the options above.']);
        }

        return [...$payload, 'option' => $match];
    }

    private function comparable(string $text): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text)));
    }

    /**
     * The question the visitor is sitting on, for a widget that was closed and
     * reopened mid-chat. Read-only: nothing is said, saved or advanced - the
     * transcript already holds the question, the widget only needs its buttons
     * (or its text box) back. Null when a person has the chat or it is over.
     *
     * @return array<string, mixed>|null
     */
    public function current(ChatWidget $widget, ChatConversation $conversation): ?array
    {
        $this->known = $conversation->answers ?? [];
        if ($conversation->is_live || in_array($conversation->status, ['closed', 'spam'], true)) {
            return null;
        }

        $nodeId = ($conversation->answers ?? [])[self::CURSOR] ?? null;
        if (! is_string($nodeId)) {
            return null;
        }

        if ($nodeId === '_consent_gate') {
            return $this->consentNode($widget);
        }

        $node = $this->flowFor($widget)['nodes'][$nodeId] ?? null;
        if ($node === null) {
            return null;
        }

        if ($node['type'] === 'booking') {
            // Slots are re-read, not remembered: some may have been taken while
            // the chat was closed. "None of these work" is always offered.
            $page = BookingPage::query()->where('is_active', true)->first();

            return $this->bookingNode($nodeId, $node, $page !== null ? $this->slots->available($page) : []);
        }

        return in_array($node['type'], ['choice', 'input', 'ai'], true)
            ? $this->presentNode($widget, $nodeId, $node)
            : null;
    }

    /**
     * An interactive node as the widget sees it, with the tenant's suggested
     * questions (CHAT-045) on the AI step - tappable chips so a visitor knows
     * what the assistant can answer. Never on data-collection inputs.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function presentNode(ChatWidget $widget, string $nodeId, array $node): array
    {
        $public = $this->publicNode($nodeId, $node);

        if ($node['type'] === 'ai') {
            $suggestions = array_values(array_filter(array_map(
                fn ($q) => trim((string) $q),
                (array) (($widget->settings['suggested_questions'] ?? [])),
            )));
            if ($suggestions !== []) {
                $public['suggestions'] = array_slice($suggestions, 0, 4);
            }
        }

        return $public;
    }

    /** @return array<string, mixed> */
    private function consentNode(ChatWidget $widget): array
    {
        $consent = $widget->consent ?? [];

        return [
            'id' => '_consent_gate',
            'type' => 'consent',
            'text' => $consent['message']
                ?? 'We use this chat to respond to your request and may retain the conversation.',
            'privacy_url' => $consent['privacy_url'] ?? null,
            'options' => [
                ['id' => 'accept', 'label' => 'Accept & continue'],
                ['id' => 'decline', 'label' => 'No thanks'],
            ],
        ];
    }

    /**
     * A booking step as the widget sees it: an ordinary choice whose buttons
     * are the free slots, plus a way out when none suit.
     *
     * @param  array<string, mixed>  $node
     * @param  list<array<string, mixed>>  $free
     * @return array<string, mixed>
     */
    private function bookingNode(string $nodeId, array $node, array $free): array
    {
        $options = array_map(fn (array $slot) => ['id' => $slot['id'], 'label' => $slot['label']], $free);
        $options[] = ['id' => 'none', 'label' => 'None of these work'];

        return [
            'id' => $nodeId,
            'type' => 'choice',
            'text' => (string) ($node['text'] ?? 'Pick a time that suits you:'),
            'options' => $options,
        ];
    }

    /** @var list<array<string, mixed>> */
    private array $pending = [];

    /**
     * What the visitor has told us so far, for filling placeholders.
     *
     * @var array<string, mixed>
     */
    private array $known = [];

    /**
     * Put what the visitor told us into a step's text: "Thanks, {{first_name}}!".
     *
     * A field nobody has answered yet falls back to the words after a pipe
     * ("{{first_name|there}}") and otherwise disappears, leaving a sentence that
     * still reads - never a visitor staring at their own curly braces. The
     * engine's own bookkeeping keys (_node, _tags) are not fields.
     */
    private function fill(string $text, ?ChatConversation $conversation = null): string
    {
        if (! str_contains($text, '{{')) {
            return $text;
        }

        $answers = $conversation !== null ? ($conversation->answers ?? []) : $this->known;

        $filled = preg_replace_callback(
            '/\{\{\s*([A-Za-z][A-Za-z0-9_]*)\s*(?:\|([^}]*))?\}\}/',
            function (array $match) use ($answers): string {
                $value = $answers[strtolower($match[1])] ?? null;
                if (is_array($value)) {
                    $value = implode(', ', array_filter($value, 'is_scalar'));
                }
                $value = is_scalar($value) ? trim((string) $value) : '';

                return $value !== '' ? $value : trim($match[2] ?? '');
            },
            $text,
        );

        // A placeholder that filled with nothing must not leave a gap behind.
        return trim((string) preg_replace('/ {2,}/', ' ', (string) $filled));
    }

    private function say(ChatConversation $conversation, string $text, ?string $nodeId = null, int|float|string|null $delay = null): void
    {
        $text = $this->fill($text, $conversation);
        // A pause before a line, so a run of messages reads like someone typing
        // rather than a wall arriving at once. Capped: nobody waits ten seconds.
        $pause = max(0.0, min(10.0, (float) ($delay ?? 0)));
        $message = $this->record($conversation, $nodeId, 'bot', $text, $pause > 0 ? ['delay' => $pause] : []);
        $this->pending[] = array_filter([
            'id' => $message->id,
            'role' => 'bot',
            'body' => $text,
            'delay' => $pause > 0 ? $pause : null,
        ], fn ($value) => $value !== null);
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
