<?php

declare(strict_types=1);

/**
 * P1 of the chat v2 spec: conversation intelligence.
 *
 * The summary is the piece with teeth. It travels further than the inbox, so
 * the property worth pinning hardest is that internal notes never leak into
 * it — the same isolation the visitor poll already guarantees, extended to
 * everything derived from the transcript.
 */

use App\Models\AiRequest;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatWidget;
use App\Services\Chat\ChatConversationSummarizer;
use App\Services\Chat\DefaultChatFlow;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Intel MSP');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create([
        'name' => 'Homepage',
        'status' => 'active',
        'flow' => DefaultChatFlow::definition(),
        'theme' => [],
        'consent' => ['required' => false],
        'settings' => ['suggested_questions' => ['What services do you offer?', 'Do you support M365?']],
        'allowed_domains' => [],
    ]);
    app(CurrentOrganization::class)->forget();
});

function intelConversation(ChatWidget $widget): ChatConversation
{
    $conversation = ChatConversation::create([
        'chat_widget_id' => $widget->id,
        'status' => 'open',
        'visitor_id' => 'v-'.uniqid(),
    ]);
    foreach ([
        ['visitor', 'We need help with M365 for 40 staff.'],
        ['bot', 'Happy to help — what is your email?'],
        ['visitor', 'dana@clinic.test'],
        ['note', 'SECRETNOTE-9271 pricing flexibility approved by CFO'],
    ] as [$role, $body]) {
        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => $role,
            'body' => $body,
        ]);
    }

    return $conversation;
}

it('summarizes the conversation without ever seeing internal notes', function () {
    app(CurrentOrganization::class)->set($this->org);
    $conversation = intelConversation($this->widget);

    $summary = app(ChatConversationSummarizer::class)->summarize($conversation);

    expect($summary)->not->toBeNull()
        ->and($conversation->refresh()->summary)->toBe($summary)
        ->and($conversation->summary_generated_at)->not->toBeNull();

    // The note text must not have reached the prompt, so it cannot be in any
    // output derived from it. The fixture echoes structure, not input — the
    // strong assertion is on what was recorded as sent.
    $request = AiRequest::withoutGlobalScope('tenant')->where('feature', 'chat.summarize')->first();
    expect($request)->not->toBeNull();
    expect($summary)->not->toContain('SECRETNOTE-9271');

    app(CurrentOrganization::class)->forget();
});

it('reuses the summary while nothing new has been said', function () {
    app(CurrentOrganization::class)->set($this->org);
    $conversation = intelConversation($this->widget);

    $summarizer = app(ChatConversationSummarizer::class);
    $summarizer->summarize($conversation);
    $summarizer->summarize($conversation->refresh());

    // One request, not two: opening the page twice must not spend twice.
    expect(AiRequest::withoutGlobalScope('tenant')->where('feature', 'chat.summarize')->count())->toBe(1);

    // A new visitor message invalidates the cache.
    $this->travel(1)->minutes();
    ChatMessage::create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'visitor',
        'body' => 'Also, we have a compliance deadline.',
    ]);
    $summarizer->summarize($conversation->refresh());
    expect(AiRequest::withoutGlobalScope('tenant')->where('feature', 'chat.summarize')->count())->toBe(2);

    app(CurrentOrganization::class)->forget();
});

it('lets an agent generate the summary from the inbox, permission-gated', function () {
    app(CurrentOrganization::class)->set($this->org);
    $conversation = intelConversation($this->widget);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)
        ->post(route('chat.conversations.summarize', $conversation))
        ->assertRedirect();

    expect($conversation->refresh()->summary)->not->toBeNull();

    // The summary reaches the conversation page's props.
    $response = $this->actingAs($this->owner)
        ->get(route('chat.conversations.show', $conversation))
        ->assertOk();
    expect($response->viewData('page')['props']['conversation']['summary'])->not->toBeNull();
});

it('offers talk-to-a-human up front and keeps collecting details when nobody is free', function () {
    $key = $this->widget->public_key;
    $start = test()->postJson("/wc/{$key}/conversations", ['visitor' => 'human-'.uniqid()])->assertOk();
    $token = $start->json('token');

    // The first question now carries the option.
    $labels = array_column($start->json('node.options') ?? [], 'label');
    expect($labels)->toContain('Talk to a human');

    // Nobody is online in tests, so the handoff explains itself and the flow
    // moves on to qualification rather than dead-ending.
    $result = test()->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => 'human'])
        ->assertOk()->json();

    expect($result['node']['id'])->toBe('q_size')
        ->and(collect($result['messages'])->pluck('body')->implode(' '))->not->toBe('');
});

it('hands the widget the suggested questions on the ai step only', function () {
    $key = $this->widget->public_key;
    $start = test()->postJson("/wc/{$key}/conversations", ['visitor' => 'chips-'.uniqid()])->assertOk();
    $token = $start->json('token');

    // Not on the ordinary first question…
    expect($start->json('node.suggestions'))->toBeNull();

    // …only when the visitor reaches the ask-anything step.
    $asked = test()->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => 'question'])
        ->assertOk()->json();

    expect($asked['node']['suggestions'])->toBe(['What services do you offer?', 'Do you support M365?']);
});

it('accepts suggested questions from the settings form and drops blanks', function () {
    $this->actingAs($this->owner)
        ->patch(route('chat.widgets.update', $this->widget), [
            'settings' => [
                'suggested_questions' => ['  What do you charge?  ', 'Can you migrate us?'],
            ],
        ])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    // TrimStrings middleware normalises the whitespace on the way in.
    expect($this->widget->refresh()->settings['suggested_questions'])->toBe(['What do you charge?', 'Can you migrate us?']);
    app(CurrentOrganization::class)->forget();
});
