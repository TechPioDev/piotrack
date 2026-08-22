<?php

declare(strict_types=1);

/**
 * Website Chat Phase 3 — live human chat, handoff, presence and business hours.
 *
 * The promise being tested: a visitor who asks for a person either gets one, or
 * is told plainly what happens instead. Nobody is ever left waiting for a reply
 * that will never come.
 */

use App\Authorization\Role;
use App\Models\ChatAgentPresence;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatWidget;
use App\Notifications\ChatMentionNotification;
use App\Notifications\ChatVisitorWaitingNotification;
use App\Services\Chat\ChatBusinessHours;
use App\Services\Chat\ChatPresenceService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/** A flow whose only job is to ask for a human. */
function handoffFlow(): array
{
    return [
        'start' => 'ask',
        'nodes' => [
            'ask' => ['type' => 'handoff', 'next' => 'in_email'],
            'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'What is your email?', 'next' => 'done'],
            'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks!'],
        ],
    ];
}

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create([
        'name' => 'Live widget',
        'status' => 'active',
        'flow' => handoffFlow(),
        'settings' => ['mode' => 'bot_then_human'],
    ]);
    app(CurrentOrganization::class)->forget();
});

// ------------------------------------------------------------------ presence

it('treats an agent who stopped checking in as away', function () {
    app(CurrentOrganization::class)->set($this->org);

    $fresh = ChatAgentPresence::create(['user_id' => $this->owner->id, 'status' => 'online', 'last_seen_at' => now()]);
    expect($fresh->isAvailable())->toBeTrue()
        ->and($fresh->effectiveStatus())->toBe('online');

    // A browser that closed without signing out leaves the row "online" forever.
    $fresh->update(['last_seen_at' => now()->subMinutes(ChatAgentPresence::STALE_AFTER_MINUTES + 1)]);
    expect($fresh->fresh()->isAvailable())->toBeFalse()
        ->and($fresh->fresh()->effectiveStatus())->toBe('away');

    app(CurrentOrganization::class)->forget();
});

it('lets an agent set their own status and see the roster', function () {
    $this->actingAs($this->owner)
        ->postJson(route('chat.presence.update'), ['status' => 'online'])
        ->assertOk()
        ->assertJsonPath('status', 'online');

    $this->actingAs($this->owner)
        ->postJson(route('chat.presence.update'), ['status' => 'nonsense'])
        ->assertStatus(422);
});

// ------------------------------------------------------------ business hours

it('knows when the tenant is open', function () {
    $hours = app(ChatBusinessHours::class);

    // Nothing configured means always open — the least surprising default.
    expect($hours->isOpen($this->widget))->toBeTrue();

    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update(['business_hours' => [
        'timezone' => 'UTC',
        'days' => ['mon' => ['09:00', '17:00']],
        'closed_message' => 'We are closed. Leave your details.',
    ]]);
    app(CurrentOrganization::class)->forget();

    $widget = $this->widget->fresh();

    // Monday inside the window, Monday outside it, and a day with no window.
    expect($hours->isOpen($widget, Carbon::parse('2026-08-24 10:00:00', 'UTC')))->toBeTrue()
        ->and($hours->isOpen($widget, Carbon::parse('2026-08-24 18:30:00', 'UTC')))->toBeFalse()
        ->and($hours->isOpen($widget, Carbon::parse('2026-08-25 10:00:00', 'UTC')))->toBeFalse()
        ->and($hours->closedMessage($widget))->toBe('We are closed. Leave your details.');
});

// ------------------------------------------------------------------ handoff

it('connects a visitor to an available agent and says who joined', function () {
    app(CurrentOrganization::class)->set($this->org);
    app(ChatPresenceService::class)->setStatus($this->owner, 'online');
    app(CurrentOrganization::class)->forget();

    $start = $this->postJson("/wc/{$this->widget->public_key}/conversations");

    $start->assertOk()
        ->assertJsonPath('live', true)
        ->assertJsonPath('agent', $this->owner->name);

    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $start->json('token'));
    expect($conversation->is_live)->toBeTrue()
        ->and($conversation->assignee_id)->toBe($this->owner->id)
        ->and($conversation->status)->toBe('assigned');

    // The transcript records the moment a person arrived.
    $system = ChatMessage::withoutGlobalScope('tenant')->where('role', 'system')->first();
    expect($system->body)->toContain($this->owner->name.' joined the conversation.');
});

it('falls back gracefully when nobody is online', function () {
    // No presence rows at all — everyone is offline.
    $start = $this->postJson("/wc/{$this->widget->public_key}/conversations");

    $start->assertOk();
    expect($start->json('live'))->toBeNull();

    $bodies = collect($start->json('messages'))->pluck('body')->implode(' ');
    expect($bodies)->toContain('reply within one business day')
        // ...and the conversation carries on collecting details rather than stopping.
        ->and($start->json('node.text'))->toBe('What is your email?');

    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $start->json('token'));
    expect($conversation->is_live)->toBeFalse()
        ->and($conversation->status)->toBe('waiting');
});

it('tells the team when a visitor asked for a person and got nobody', function () {
    Notification::fake();

    // Nobody online: the visitor is promised a reply, so that promise is escalated
    // to the team rather than quietly dropped.
    $this->postJson("/wc/{$this->widget->public_key}/conversations");

    Notification::assertSentTo($this->owner, ChatVisitorWaitingNotification::class);
});

it('does not offer a human on a bot-only widget', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update(['settings' => ['mode' => 'bot']]);
    app(ChatPresenceService::class)->setStatus($this->owner, 'online');
    app(CurrentOrganization::class)->forget();

    $start = $this->postJson("/wc/{$this->widget->public_key}/conversations");

    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $start->json('token'));
    expect($conversation->is_live)->toBeFalse();

    expect(collect($start->json('messages'))->pluck('body')->implode(' '))
        ->toContain('follow up by email');
});

it('shows the configured closed message outside business hours', function () {
    app(CurrentOrganization::class)->set($this->org);
    app(ChatPresenceService::class)->setStatus($this->owner, 'online');
    // A window that cannot contain "now", whichever day the suite runs.
    $this->widget->update(['business_hours' => [
        'timezone' => 'UTC',
        'days' => [],
        'closed_message' => 'Out of hours — leave your details.',
    ]]);
    // An empty day map means "always open", so give it a real day that is not today.
    $notToday = now()->addDay()->format('D');
    $map = ['mon' => null, 'tue' => null, 'wed' => null, 'thu' => null, 'fri' => null, 'sat' => null, 'sun' => null];
    $this->widget->update(['business_hours' => [
        'timezone' => 'UTC',
        'days' => $map,
        'closed_message' => 'Out of hours — leave your details.',
    ]]);
    app(CurrentOrganization::class)->forget();

    $start = $this->postJson("/wc/{$this->widget->public_key}/conversations");

    expect(collect($start->json('messages'))->pluck('body')->implode(' '))
        ->toContain('Out of hours');

    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $start->json('token'));
    expect($conversation->is_live)->toBeFalse()->and($conversation->status)->toBe('waiting');
});

// -------------------------------------------------------------- live messaging

it('lets a visitor and an agent talk once live, with the bot standing down', function () {
    app(CurrentOrganization::class)->set($this->org);
    app(ChatPresenceService::class)->setStatus($this->owner, 'online');
    app(CurrentOrganization::class)->forget();

    $key = $this->widget->public_key;
    $start = $this->postJson("/wc/{$key}/conversations");
    $token = $start->json('token');

    // The visitor types freely; the bot must not advance the flow.
    $reply = $this->postJson("/wc/{$key}/conversations/{$token}/messages", ['value' => 'Our email is broken']);
    $reply->assertOk()->assertJsonPath('live', true);

    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token);
    expect($conversation->messages()->where('role', 'visitor')->where('body', 'Our email is broken')->exists())->toBeTrue()
        // The email question was never asked, because a human is handling it.
        ->and($conversation->answers['email'] ?? null)->toBeNull();

    // The agent replies from the inbox...
    $this->actingAs($this->owner)
        ->post(route('chat.conversations.reply', $conversation), ['body' => 'Hi, let me take a look.']);

    // ...and the visitor receives it by polling.
    $poll = $this->getJson("/wc/{$key}/conversations/{$token}/poll");
    $poll->assertOk();
    expect(collect($poll->json('messages'))->pluck('body')->implode(' '))->toContain('Hi, let me take a look.')
        ->and($poll->json('live'))->toBeTrue();
});

it('never leaks internal notes to the visitor', function () {
    app(CurrentOrganization::class)->set($this->org);
    app(ChatPresenceService::class)->setStatus($this->owner, 'online');
    app(CurrentOrganization::class)->forget();

    $key = $this->widget->public_key;
    $token = $this->postJson("/wc/{$key}/conversations")->json('token');
    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token);

    $this->actingAs($this->owner)
        ->post(route('chat.conversations.note', $conversation), ['body' => 'Competitor contract expires October.']);

    $poll = $this->getJson("/wc/{$key}/conversations/{$token}/poll");

    expect(collect($poll->json('messages'))->pluck('body')->implode(' '))
        ->not->toContain('Competitor contract expires October.');
});

// ------------------------------------------------------------------ mentions

it('notifies a colleague named in an internal note', function () {
    Notification::fake();

    $colleague = addMember($this->org, Role::SalesRepresentative);

    app(CurrentOrganization::class)->set($this->org);
    $conversation = ChatConversation::create(['chat_widget_id' => $this->widget->id, 'status' => 'open']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->post(route('chat.conversations.note', $conversation), [
        'body' => "@{$colleague->name} can you follow up with this prospect?",
    ]);

    Notification::assertSentOnDemand(ChatMentionNotification::class);

    $note = ChatMessage::withoutGlobalScope('tenant')->where('role', 'note')->first();
    expect($note->meta['mentions'])->toContain($colleague->id);
});

it('does not treat a stray @ as a mention', function () {
    Notification::fake();

    app(CurrentOrganization::class)->set($this->org);
    $conversation = ChatConversation::create(['chat_widget_id' => $this->widget->id, 'status' => 'open']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->post(route('chat.conversations.note', $conversation), [
        'body' => 'Emailed them at info@example.test already.',
    ]);

    Notification::assertNothingSent();
});

// ------------------------------------------------------------- authorization

it('keeps presence and polling behind the right permissions', function () {
    $viewer = addMember($this->org, Role::Viewer);

    // A viewer may read the inbox but not set availability (they cannot handle chats).
    $this->actingAs($viewer)
        ->postJson(route('chat.presence.update'), ['status' => 'online'])
        ->assertForbidden();

    app(CurrentOrganization::class)->set($this->org);
    $conversation = ChatConversation::create(['chat_widget_id' => $this->widget->id, 'status' => 'open']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($viewer)
        ->getJson(route('chat.conversations.poll', $conversation))
        ->assertOk();
});

it('never lets one tenant poll another tenant conversation', function () {
    [$otherOrg, $otherOwner] = makeOrganization('Rival MSP');
    subscribeOrganization($otherOrg, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $conversation = ChatConversation::create(['chat_widget_id' => $this->widget->id, 'status' => 'open']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($otherOwner)
        ->getJson(route('chat.conversations.poll', $conversation))
        ->assertNotFound();
});
