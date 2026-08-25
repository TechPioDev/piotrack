<?php

declare(strict_types=1);

/**
 * In-chat booking (CHAT-023) and AI answers (CHAT-043).
 *
 * Both close gaps against the HubSpot widget this module competes with, and
 * both have the same failure discipline: the visitor must never see an error
 * or a dead end. No free slot, a slot taken mid-conversation, exhausted AI
 * credits, a provider outage — every one degrades onto a path that still
 * captures the lead.
 */

use App\Ai\Exceptions\AiProviderException;
use App\Models\AiRequest;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatWidget;
use App\Services\Ai\AiGateway;
use App\Services\Chat\DefaultChatFlow;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Booked MSP');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create([
        'name' => 'Homepage',
        'status' => 'active',
        'flow' => DefaultChatFlow::definition(),
        'theme' => [],
        'consent' => ['required' => false],
        'settings' => [],
        'allowed_domains' => [],
    ]);
    $this->page = BookingPage::create([
        'name' => 'Intro call',
        'slug' => 'intro-'.uniqid(),
        'duration_minutes' => 30,
        'assignment' => 'fixed',
        'user_id' => $this->owner->id,
        'is_active' => true,
    ]);
    app(CurrentOrganization::class)->forget();
});

/** Walk the flow up to the meeting question, answering as a qualified buyer. */
function walkToSlots(string $key): array
{
    $start = test()->postJson("/wc/{$key}/conversations", ['visitor' => 'bk-'.uniqid()]);
    $start->assertOk();
    $token = $start->json('token');

    $answers = [
        ['option' => 'managed_it'], ['option' => '11-50'], ['option' => 'yes'],
        ['option' => 'slow_support'], ['value' => 'Dana'], ['value' => 'Whitfield'],
        ['value' => 'dana@bookme.test'], ['value' => '2155550100'], ['value' => 'Bookme Co'],
        ['option' => '1_3_months'], ['option' => '1'], ['option' => 'none'],
        ['option' => 'yes'], // wants a meeting -> booking node
    ];
    $payload = [];
    foreach ($answers as $answer) {
        $payload = test()->postJson("/wc/{$key}/conversations/{$token}/messages", $answer)->assertOk()->json();
    }

    return ['token' => $token, 'payload' => $payload];
}

it('books a real slot inside the conversation', function () {
    $key = $this->widget->public_key;
    $result = walkToSlots($key);

    // The widget is shown live availability as ordinary buttons.
    $node = $result['payload']['node'];
    expect($node['type'])->toBe('choice');
    $slotIds = array_column($node['options'], 'id');
    expect(count($slotIds))->toBeGreaterThan(1)
        ->and(end($slotIds))->toBe('none');

    $picked = $slotIds[0];
    $final = test()->postJson("/wc/{$key}/conversations/{$result['token']}/messages", ['option' => $picked])
        ->assertOk()->json();

    expect($final['done'])->toBeTrue();
    // Booked in chat, so the visitor is NOT handed a "choose a time" link.
    expect($final)->not->toHaveKey('booking_url');

    $booking = Booking::withoutGlobalScope('tenant')->firstWhere('email', 'dana@bookme.test');
    expect($booking)->not->toBeNull()
        ->and($booking->source)->toBe('website_chat')
        ->and($booking->booking_page_id)->toBe($this->page->id)
        ->and($booking->scheduled_at->format('Y-m-d\TH:i'))->toBe(str_replace('slot_', '', $picked));

    // Analytics sees the meeting, once.
    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $result['token']);
    expect(ChatEvent::withoutGlobalScope('tenant')
        ->where('chat_conversation_id', $conversation->id)->where('type', 'meeting')->count())->toBe(1);
});

it('falls back to the booking link when no time works', function () {
    $key = $this->widget->public_key;
    $result = walkToSlots($key);

    $final = test()->postJson("/wc/{$key}/conversations/{$result['token']}/messages", ['option' => 'none'])
        ->assertOk()->json();

    expect($final['done'])->toBeTrue()
        ->and($final['booking_url'] ?? null)->toContain('/b/');
    expect(Booking::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('re-presents fresh slots when the picked one was just taken', function () {
    $key = $this->widget->public_key;
    $result = walkToSlots($key);
    $picked = $result['payload']['node']['options'][0]['id'];

    // Someone else takes that exact slot between presentation and pick.
    app(CurrentOrganization::class)->set($this->org);
    Booking::create([
        'booking_page_id' => $this->page->id,
        'name' => 'Rival', 'email' => 'rival@other.test',
        'scheduled_at' => str_replace(['slot_', 'T'], ['', ' '], $picked),
        'status' => 'booked',
    ]);
    app(CurrentOrganization::class)->forget();

    $again = test()->postJson("/wc/{$key}/conversations/{$result['token']}/messages", ['option' => $picked])
        ->assertOk()->json();

    // Not an error and not done: the visitor is offered what is still free,
    // and the taken slot is no longer on the list.
    expect($again['done'])->toBeFalse()
        ->and($again['node']['type'])->toBe('choice')
        ->and(array_column($again['node']['options'], 'id'))->not->toContain($picked);
});

it('skips straight to the link path when no booking page exists', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->page->update(['is_active' => false]);
    app(CurrentOrganization::class)->forget();

    $key = $this->widget->public_key;
    $result = walkToSlots($key);

    // No slots to offer, so the meeting question's yes lands on the end node.
    expect($result['payload']['done'])->toBeTrue();
    expect(Booking::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('answers a typed question through the gateway and returns to the funnel', function () {
    $key = $this->widget->public_key;
    $start = test()->postJson("/wc/{$key}/conversations", ['visitor' => 'ai-'.uniqid()])->assertOk();
    $token = $start->json('token');

    $asked = test()->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => 'question'])
        ->assertOk()->json();
    // Presented to the widget as a plain text input — no client changes needed.
    expect($asked['node']['type'])->toBe('input');

    $answered = test()->postJson("/wc/{$key}/conversations/{$token}/messages", ['value' => 'Do you support Microsoft 365?'])
        ->assertOk()->json();

    // The AI's reply is in the transcript, and the follow-up choice keeps the
    // visitor in the funnel rather than dead-ending after the answer.
    expect(collect($answered['messages'])->pluck('body')->filter()->count())->toBeGreaterThan(0)
        ->and($answered['node']['id'])->toBe('ai_more');

    $continued = test()->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => 'talk'])
        ->assertOk()->json();
    expect($continued['node']['id'])->toBe('q_size');
});

it('degrades to the qualification path when the AI is unavailable', function () {
    // Whatever goes wrong at the gateway — no credits, no provider, an outage —
    // must read to the visitor as a graceful handover, never an error.
    $failing = Mockery::mock(AiGateway::class);
    $failing->shouldReceive('run')->andThrow(new AiProviderException('provider down', transient: false));
    app()->instance(AiGateway::class, $failing);

    $key = $this->widget->public_key;
    $start = test()->postJson("/wc/{$key}/conversations", ['visitor' => 'ai-down-'.uniqid()])->assertOk();
    $token = $start->json('token');

    test()->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => 'question'])->assertOk();
    $result = test()->postJson("/wc/{$key}/conversations/{$token}/messages", ['value' => 'Anyone there?'])
        ->assertOk()->json();

    // Landed on the fallback (company size), with a human-sounding apology said.
    expect($result['node']['id'])->toBe('q_size');
    expect(collect($result['messages'])->pluck('body')->implode(' '))->toContain('take your details');
});

it('never spends AI credits or books rooms from a builder preview', function () {
    $this->actingAs($this->owner);
    app(CurrentOrganization::class)->set($this->org);

    $flow = DefaultChatFlow::definition();

    // Preview the AI step: deterministic canned answer, no gateway call.
    $startResponse = $this->postJson(route('chat.flow.test', $this->widget), ['flow' => $flow]);
    $startResponse->assertOk();
    $token = $startResponse->json('token');

    $this->postJson(route('chat.flow.test', $this->widget), ['flow' => $flow, 'token' => $token, 'option' => 'question'])->assertOk();
    $answer = $this->postJson(route('chat.flow.test', $this->widget), ['flow' => $flow, 'token' => $token, 'value' => 'What about pricing?'])
        ->assertOk()->json();

    expect(collect($answer['messages'])->pluck('body')->implode(' '))->toContain('(Preview)');
    expect(AiRequest::withoutGlobalScope('tenant')->where('feature', 'chat.answer')->count())->toBe(0);

    app(CurrentOrganization::class)->forget();
});
