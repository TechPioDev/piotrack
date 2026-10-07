<?php

declare(strict_types=1);

/**
 * Who a chat's support ticket goes to, and what happens when the same client
 * asks twice.
 *
 * Three things, found and built together:
 *
 *  - A conversation could be handed to ANY user on the platform, not only a
 *    teammate: reassigning in the inbox checked "is a user", a widget's default
 *    owner checked nothing, and a flow's Assign step was never checked at all.
 *    The stranger's name then showed in this workspace's inbox.
 *  - A flow can now send tickets by topic: an Assign step on one answer's path,
 *    or "Who gets the ticket" on the ending.
 *  - A second request from the same client about the same thing joins the
 *    ticket that is still open, instead of becoming someone else's problem.
 */

use App\Authorization\Role;
use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatWidget;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Notifications\ChatTicketOpenedNotification;
use App\Notifications\TicketNotification;
use App\Notifications\TicketRequesterNotification;
use App\Services\Chat\ChatFlowValidator;
use App\Support\CurrentOrganization;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');
    $this->alex = addMember($this->org, Role::SalesRepresentative);
    $this->sam = addMember($this->org, Role::SalesRepresentative);
    [$this->rival, $this->stranger] = makeOrganization('Rival MSP');
    subscribeOrganization($this->rival, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'active', 'theme' => ['company' => 'PioManage']]);
    app(CurrentOrganization::class)->forget();

    $this->key = $this->widget->public_key;
    Notification::fake();
});

/**
 * Billing goes through an Assign step; technical does not. Both end at one
 * support ending, which names its own default.
 *
 * @return array<string, mixed>
 */
function supportByTopic(?int $billingTo, ?int $endingTo): array
{
    return [
        'start' => 'q',
        'nodes' => [
            'q' => ['type' => 'choice', 'text' => 'How can we help?', 'field' => 'topic', 'options' => [
                ['id' => 'billing', 'label' => 'Billing question', 'next' => 'to_billing'],
                ['id' => 'technical', 'label' => 'Technical support', 'next' => 'in_email'],
            ]],
            'to_billing' => ['type' => 'assign', 'assignee_id' => $billingTo, 'next' => 'in_email'],
            'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'Which email should we reply to?', 'next' => 'in_issue'],
            'in_issue' => ['type' => 'input', 'input' => 'text', 'field' => 'support_issue', 'text' => 'What do you need help with?', 'next' => 'end_support'],
            'end_support' => ['type' => 'end', 'outcome' => 'support', 'text' => 'Thanks - we have opened a support ticket.', 'assignee_id' => $endingTo],
        ],
    ];
}

/** Put a flow on the widget as it is stored, without the builder's checks. */
function useSupportFlow($test, array $flow, ?ChatWidget $widget = null): void
{
    ($widget ?? $test->widget)->forceFill(['flow' => $flow])->save();
}

/** A client asks for help; returns the last reply the chat gave them. */
function askAbout($test, string $topic, string $email = 'dana@client.test', string $issue = 'The invoice is wrong', ?string $key = null): array
{
    $key ??= $test->key;
    $token = $test->postJson("/wc/{$key}/conversations", ['page' => 'https://piomanage.test/support'])->assertOk()->json('token');
    $test->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => $topic])->assertOk();
    $test->postJson("/wc/{$key}/conversations/{$token}/messages", ['value' => $email])->assertOk();
    $reply = $test->postJson("/wc/{$key}/conversations/{$token}/messages", ['value' => $issue])->assertOk()->json();

    return ['token' => $token, 'reply' => $reply];
}

function ticketsOf($test)
{
    return Ticket::withoutGlobalScope('tenant')->where('organization_id', $test->org->id)->orderBy('id')->get();
}

/*
 * Only ever a teammate.
 */

it('refuses to hand a conversation to someone outside the workspace from the inbox', function () {
    useSupportFlow($this, supportByTopic(null, null));
    $token = askAbout($this, 'technical')['token'];
    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token);

    $this->actingAs($this->owner)
        ->patch(route('chat.conversations.update', $conversation), ['assignee_id' => $this->stranger->id])
        ->assertSessionHasErrors('assignee_id');
    expect($conversation->refresh()->assignee_id)->not->toBe($this->stranger->id);

    $this->actingAs($this->owner)
        ->patch(route('chat.conversations.update', $conversation), ['assignee_id' => $this->alex->id])
        ->assertSessionHasNoErrors();
    expect($conversation->refresh()->assignee_id)->toBe($this->alex->id);
});

it('refuses a stranger as the chat\'s default owner', function () {
    $this->actingAs($this->owner)
        ->patch(route('chat.widgets.update', $this->widget), ['routing' => ['assignee_id' => $this->stranger->id]])
        ->assertSessionHasErrors('routing.assignee_id');

    $this->actingAs($this->owner)
        ->patch(route('chat.widgets.update', $this->widget), ['routing' => ['assignee_id' => $this->alex->id]])
        ->assertSessionHasNoErrors();
});

it('will not publish a conversation that hands over to someone outside the workspace', function () {
    app(CurrentOrganization::class)->set($this->org);
    $validator = app(ChatFlowValidator::class);
    $assigning = $validator->validate(supportByTopic($this->stranger->id, null));
    $ending = $validator->validate(supportByTopic(null, $this->stranger->id));
    $fine = $validator->validate(supportByTopic($this->alex->id, $this->sam->id));
    app(CurrentOrganization::class)->forget();

    expect($assigning['valid'])->toBeFalse()
        ->and(collect($assigning['errors'])->pluck('node')->all())->toContain('to_billing')
        ->and($ending['valid'])->toBeFalse()
        ->and(collect($ending['errors'])->pluck('node')->all())->toContain('end_support')
        ->and($fine['valid'])->toBeTrue();

    $this->actingAs($this->owner)
        ->put(route('chat.flow.update', $this->widget), ['flow' => supportByTopic($this->stranger->id, null), 'publish' => true])
        ->assertSessionHasErrors('flow');
});

it('never follows a stored flow outside the workspace, whatever it says', function () {
    // As a flow saved before any of this was checked would be.
    useSupportFlow($this, supportByTopic($this->stranger->id, $this->stranger->id));

    $token = askAbout($this, 'billing')['token'];

    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token);
    expect($conversation->assignee_id)->toBeNull()
        ->and(ticketsOf($this)->sole()->assignee_id)->toBeNull();
    Notification::assertNothingSentTo($this->stranger);
});

/*
 * Sending tickets by topic.
 */

it('sends each topic\'s ticket to its own person', function () {
    useSupportFlow($this, supportByTopic($this->alex->id, $this->sam->id));

    askAbout($this, 'billing', 'dana@client.test');
    askAbout($this, 'technical', 'omar@client.test');

    [$billing, $technical] = ticketsOf($this)->all();

    // The Assign step on the billing path decides, ahead of the ending's default.
    expect($billing->topic)->toBe('Billing question')
        ->and($billing->assignee_id)->toBe($this->alex->id)
        // No Assign step on the technical path: the ending names who gets it.
        ->and($technical->topic)->toBe('Technical support')
        ->and($technical->assignee_id)->toBe($this->sam->id);

    Notification::assertSentTo($this->alex, TicketNotification::class, fn ($n) => $n->title() === 'Ticket assigned to you');
    Notification::assertSentTo($this->sam, TicketNotification::class, fn ($n) => $n->title() === 'Ticket assigned to you');
});

it('falls back to the chat\'s default owner when the flow names nobody', function () {
    useSupportFlow($this, supportByTopic(null, null));
    $this->widget->forceFill(['routing' => ['assignee_id' => $this->sam->id]])->save();

    askAbout($this, 'technical');

    expect(ticketsOf($this)->sole()->assignee_id)->toBe($this->sam->id);
});

/*
 * The same client, asking twice.
 */

it('adds a repeat request to the ticket that is still open, and tells the person who has it', function () {
    useSupportFlow($this, supportByTopic($this->alex->id, null));

    $first = askAbout($this, 'billing', 'dana@client.test', 'The invoice is wrong');
    Notification::fake();
    $second = askAbout($this, 'billing', 'Dana@Client.test', 'Still wrong, and now a second invoice too');

    $ticket = ticketsOf($this)->sole();
    $added = TicketMessage::withoutGlobalScope('tenant')->where('ticket_id', $ticket->id)->sole();

    expect($added->user_id)->toBeNull()
        ->and($added->is_internal)->toBeFalse()
        ->and($added->body)->toStartWith('More from the website chat')
        ->toContain('Details: Still wrong, and now a second invoice too')
        ->toContain('Chat transcript')
        // Still Alex's, still pending - nothing was handed to anyone else.
        ->and($ticket->assignee_id)->toBe($this->alex->id);

    // The second conversation points at the same ticket, and says it joined one.
    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $second['token']);
    $event = ChatEvent::withoutGlobalScope('tenant')->where('chat_conversation_id', $conversation->id)->where('type', 'complete')->sole();
    expect($conversation->answers['_ticket'])->toBe($ticket->id)
        ->and($event->meta['joined'] ?? false)->toBeTrue();

    Notification::assertSentTo($this->alex, TicketNotification::class, fn ($n) => $n->title() === 'New reply on your ticket');

    // The client is told by email - and the chat itself says nothing different,
    // because whoever typed that address is not owed "ticket #N is open".
    Notification::assertSentOnDemand(TicketRequesterNotification::class, function ($n, array $channels, AnonymousNotifiable $to) use ($ticket) {
        return $to->routes['mail'] === 'dana@client.test'
            && $n->toMail($to)->subject === "We have added to your request (#{$ticket->id}) - PioManage";
    });
    expect(collect($second['reply']['messages'])->pluck('body')->all())->toBe(collect($first['reply']['messages'])->pluck('body')->all())
        ->and(json_encode($second['reply']))->not->toContain((string) "#{$ticket->id}");
});

it('tells the owners again when a client adds to a ticket nobody picked up', function () {
    useSupportFlow($this, supportByTopic(null, null));

    askAbout($this, 'technical');
    Notification::fake();
    askAbout($this, 'technical', issue: 'Is anyone there?');

    $ticket = ticketsOf($this)->sole();
    Notification::assertSentTo($this->owner, ChatTicketOpenedNotification::class, fn ($n) => $n->title() === 'A client has added to a support request that nobody has picked up'
        && str_contains($n->body(), "Ticket #{$ticket->id}: Technical support"));
});

it('opens a new ticket for a different matter, a closed one, or one gone quiet', function () {
    useSupportFlow($this, supportByTopic(null, null));

    askAbout($this, 'billing');
    // A different kind of request is a different ticket.
    askAbout($this, 'technical');
    expect(ticketsOf($this))->toHaveCount(2);

    // Once resolved, the matter is closed: asking again starts afresh.
    app(CurrentOrganization::class)->set($this->org);
    ticketsOf($this)->first()->update(['status' => 'resolved', 'resolved_at' => now()]);
    app(CurrentOrganization::class)->forget();
    askAbout($this, 'billing');
    expect(ticketsOf($this))->toHaveCount(3);

    // And so does an open ticket nobody has touched for longer than the window.
    $this->travel((int) config('chat.ticket_merge_hours') + 1)->hours();
    askAbout($this, 'technical');
    expect(ticketsOf($this))->toHaveCount(4);
});

it('can be switched off', function () {
    config(['chat.ticket_merge_hours' => 0]);
    useSupportFlow($this, supportByTopic(null, null));

    askAbout($this, 'billing');
    askAbout($this, 'billing');

    expect(ticketsOf($this))->toHaveCount(2);
});

it('never joins another workspace\'s ticket, whoever the client is', function () {
    useSupportFlow($this, supportByTopic(null, null));
    app(CurrentOrganization::class)->set($this->rival);
    $theirs = ChatWidget::create(['name' => 'Rival website', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();
    useSupportFlow($this, supportByTopic(null, null), $theirs);

    // The same person is a client of both companies, asking each the same thing.
    askAbout($this, 'billing', 'dana@client.test');
    askAbout($this, 'billing', 'dana@client.test', key: $theirs->public_key);

    expect(ticketsOf($this))->toHaveCount(1)
        ->and(Ticket::withoutGlobalScope('tenant')->where('organization_id', $this->rival->id)->count())->toBe(1)
        ->and(TicketMessage::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('shows on the desk which words are the client\'s own', function () {
    useSupportFlow($this, supportByTopic(null, null));
    askAbout($this, 'billing');
    askAbout($this, 'billing', issue: 'Any news?');

    $this->actingAs($this->owner)->get(route('support.index'))
        ->assertInertia(fn ($page) => $page
            ->has('tickets', 1)
            ->where('tickets.0.messages.0.from_requester', true)
            ->where('tickets.0.messages.0.is_internal', false));

    // A teammate's reply on the same ticket is not marked as the client's.
    $ticket = ticketsOf($this)->sole();
    $this->actingAs($this->owner)->post(route('support.tickets.reply', $ticket), ['body' => 'Looking at it now.'])->assertSessionHasNoErrors();

    $this->actingAs($this->owner)->get(route('support.index'))
        ->assertInertia(fn ($page) => $page->where('tickets.0.messages.1.from_requester', false));
});
