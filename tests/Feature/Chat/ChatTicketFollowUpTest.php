<?php

declare(strict_types=1);

/**
 * What happens after the website chat opens a support ticket.
 *
 * The ticket used to arrive with no requester and no assignee: nobody was told,
 * a reply on the desk reached nobody - although the chat had just promised the
 * client "our team will follow up by email" - and there was no way back to the
 * conversation or the client's record. Now somebody is told, the client gets a
 * receipt and the team's replies, and the ticket knows where it came from.
 */

use App\Authorization\Role;
use App\Models\ChatConversation;
use App\Models\ChatWidget;
use App\Models\Contact;
use App\Models\NotificationChannel;
use App\Models\Ticket;
use App\Notifications\ChatTicketOpenedNotification;
use App\Notifications\TicketNotification;
use App\Notifications\TicketRequesterNotification;
use App\Support\CurrentOrganization;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'active', 'theme' => ['company' => 'PioManage']]);
    app(CurrentOrganization::class)->forget();

    $this->key = $this->widget->public_key;
    Notification::fake();
});

/**
 * A client asks the chat for help. $before runs once the chat has started,
 * before the last answer turns it into a ticket.
 */
function reachSupportTicket($test, string $email = 'dana@client.test', string $issue = 'Our last invoice has the wrong seat count', ?Closure $before = null): Ticket
{
    $token = $test->postJson("/wc/{$test->key}/conversations", ['page' => 'https://piomanage.test/support'])->assertOk()->json('token');
    foreach ([['option' => 'existing'], ['option' => 'billing'], ['value' => $email]] as $answer) {
        $test->postJson("/wc/{$test->key}/conversations/{$token}/messages", $answer)->assertOk();
    }

    if ($before !== null) {
        $before(ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token));
    }

    $test->postJson("/wc/{$test->key}/conversations/{$token}/messages", ['value' => $issue])->assertOk();

    return Ticket::withoutGlobalScope('tenant')->latest('id')->firstOrFail();
}

/** @return list<TicketRequesterNotification> the emails sent to one outside address */
function emailsTo(string $address): array
{
    $sent = [];
    Notification::assertSentOnDemand(TicketRequesterNotification::class, function (TicketRequesterNotification $n, array $c, AnonymousNotifiable $to) use ($address, &$sent) {
        if ($to->routes['mail'] === $address) {
            $sent[] = $n;
        }

        return true;
    });

    return $sent;
}

it('tells the owners and the team\'s own channels, without repeating what the visitor typed', function () {
    Http::fake(['*' => Http::response('ok')]);
    app(CurrentOrganization::class)->set($this->org);
    NotificationChannel::create(['kind' => 'slack', 'url' => 'https://93.184.216.34/slack-hook', 'is_active' => true]);
    app(CurrentOrganization::class)->forget();

    $ticket = reachSupportTicket($this, issue: 'Invoice wrong <!channel>');

    Notification::assertSentTo($this->owner, ChatTicketOpenedNotification::class, fn (ChatTicketOpenedNotification $n) => $n->body() === "Ticket #{$ticket->id}: Billing - nobody is assigned yet."
        && $n->url() === url('/support#ticket-'.$ticket->id));

    // Slack would turn "<!channel>" into a page for the whole company.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'slack-hook')
        && str_contains($request['text'], 'New support request from the website chat')
        && ! str_contains($request['text'], '<!channel>'));
});

it('sends the client a receipt with their ticket number, and none of their own words', function () {
    $ticket = reachSupportTicket($this);

    $emails = emailsTo('dana@client.test');
    expect($emails)->toHaveCount(1);

    $mail = $emails[0]->toMail(new AnonymousNotifiable);
    expect($mail->subject)->toBe("We have your request (#{$ticket->id}) - PioManage")
        ->and(implode(' ', $mail->introLines))->toContain("support ticket #{$ticket->id}")
        ->not->toContain('seat count');
});

it('sends at most three receipts a day to one address, however often the chat is run', function () {
    foreach (range(1, 4) as $i) {
        reachSupportTicket($this, 'victim@elsewhere.test');
    }

    expect(Ticket::withoutGlobalScope('tenant')->count())->toBe(4)
        ->and(emailsTo('victim@elsewhere.test'))->toHaveCount(3);
});

it('keeps the teammate who was already in the chat, and tells them', function () {
    $sam = addMember($this->org, Role::SalesRepresentative);

    $ticket = reachSupportTicket($this, before: fn (ChatConversation $c) => $c->forceFill(['assignee_id' => $sam->id])->save());

    expect($ticket->assignee_id)->toBe($sam->id)->and($ticket->status)->toBe('pending');
    Notification::assertSentTo($sam, TicketNotification::class, fn (TicketNotification $n) => $n->title() === 'Ticket assigned to you');
    Notification::assertSentTo($this->owner, ChatTicketOpenedNotification::class, fn ($n) => str_contains($n->body(), "assigned to {$sam->name}"));
});

it('does not tell an owner twice about a ticket they were just given', function () {
    reachSupportTicket($this, before: fn (ChatConversation $c) => $c->forceFill(['assignee_id' => $this->owner->id])->save());

    Notification::assertSentTo($this->owner, TicketNotification::class);
    Notification::assertNotSentTo($this->owner, ChatTicketOpenedNotification::class);
});

it('falls back to whoever the chat sends conversations to, but never to someone outside the workspace', function () {
    [, $stranger] = makeOrganization('Rival MSP');
    $this->widget->forceFill(['routing' => ['assignee_id' => $stranger->id]])->save();

    expect(reachSupportTicket($this)->assignee_id)->toBeNull();

    $sam = addMember($this->org, Role::SalesRepresentative);
    $this->widget->forceFill(['routing' => ['assignee_id' => $sam->id]])->save();

    expect(reachSupportTicket($this, 'sam@client.test')->assignee_id)->toBe($sam->id);
});

it('points at the chat it came from, and at the client\'s record when they are already in the CRM', function () {
    app(CurrentOrganization::class)->set($this->org);
    $dana = Contact::create(['first_name' => 'Dana', 'last_name' => 'Reyes', 'email' => 'Dana@Client.test']);
    app(CurrentOrganization::class)->forget();

    $ticket = reachSupportTicket($this);
    $conversation = ChatConversation::withoutGlobalScope('tenant')->findOrFail($ticket->chat_conversation_id);

    expect($ticket->requester_email)->toBe('dana@client.test')
        ->and($ticket->requester_id)->toBeNull()
        ->and($ticket->contact_id)->toBe($dana->id)
        ->and($conversation->contact_id)->toBe($dana->id)
        // A client is found, never invented.
        ->and(Contact::withoutGlobalScope('tenant')->count())->toBe(1);

    $this->actingAs($this->owner)->get(route('support.index'))
        ->assertInertia(fn ($page) => $page
            ->where('tickets.0.requester.email', 'dana@client.test')
            ->where('tickets.0.contact.id', $dana->id)
            ->where('tickets.0.contact.name', 'Dana Reyes')
            ->where('tickets.0.conversation_id', $conversation->id));
});

it('emails the team\'s reply to the client, with their answer going back to whoever wrote it', function () {
    $ticket = reachSupportTicket($this);
    Notification::fake();

    $this->actingAs($this->owner)
        ->post(route('support.tickets.reply', $ticket), ['body' => "Hi Dana, you are right.\n\nWe have credited two seats."])
        ->assertSessionHasNoErrors();

    $emails = emailsTo('dana@client.test');
    expect($emails)->toHaveCount(1);
    $mail = $emails[0]->toMail(new AnonymousNotifiable);
    expect($mail->subject)->toBe("Re: your request #{$ticket->id} - PioManage")
        ->and($mail->introLines)->toContain('Hi Dana, you are right.')->toContain('We have credited two seats.')
        ->and($mail->replyTo)->toBe([[$this->owner->email, $this->owner->name]]);
});

it('never emails an internal note to the client', function () {
    $ticket = reachSupportTicket($this);
    Notification::fake();

    $this->actingAs($this->owner)
        ->post(route('support.tickets.reply', $ticket), ['body' => 'Seat count was our mistake.', 'is_internal' => true])
        ->assertSessionHasNoErrors();

    Notification::assertSentOnDemandTimes(TicketRequesterNotification::class, 0);
});

it('tells the client when their request is resolved', function () {
    $ticket = reachSupportTicket($this);
    Notification::fake();

    $this->actingAs($this->owner)->post(route('support.tickets.resolve', $ticket))->assertSessionHasNoErrors();

    $emails = emailsTo('dana@client.test');
    expect($emails)->toHaveCount(1)
        ->and($emails[0]->toMail(new AnonymousNotifiable)->subject)->toBe("Your request #{$ticket->id} is resolved - PioManage");
});

it('never emails a ticket raised by someone signed in to the workspace', function () {
    $this->actingAs($this->owner)
        ->post(route('support.tickets.store'), ['subject' => 'Printer', 'body' => 'It is on fire', 'requester_email' => 'someone@else.test'])
        ->assertSessionHasNoErrors();
    $ticket = Ticket::withoutGlobalScope('tenant')->sole();

    $this->actingAs($this->owner)->post(route('support.tickets.reply', $ticket), ['body' => 'Noted.']);

    expect($ticket->requester_email)->toBeNull();
    Notification::assertSentOnDemandTimes(TicketRequesterNotification::class, 0);
});

it('offers only this workspace\'s people to assign tickets and projects to', function () {
    [, $stranger] = makeOrganization('Rival MSP');

    $this->actingAs($this->owner)->get(route('support.index'))
        ->assertInertia(fn ($page) => $page->where('members', fn ($members) => collect($members)->pluck('id')->all() === [$this->owner->id]));

    $this->actingAs($this->owner)->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page->where('members', fn ($members) => ! collect($members)->pluck('id')->contains($stranger->id)
            && collect($members)->pluck('id')->contains($this->owner->id)));
});
