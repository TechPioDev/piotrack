<?php

declare(strict_types=1);

/**
 * QA §53 - the customer support flow, and the portal boundary around it.
 *
 * §53's chain is: customer raises a ticket, attaches a file, submits; an admin
 * receives it, responds; the customer receives the update; the ticket is
 * resolved. Two of those steps cannot happen today, and both are now recorded
 * rather than left implicit:
 *
 *   Attach File   SUPP-002 names attachments and was marked Tested. Nothing
 *                 implements them - no attachment column, no ticket_attachments
 *                 table, no files relation on Ticket, and the only upload
 *                 endpoint is the general library at /settings/files. Row
 *                 downgraded to Partially Implemented.
 *
 *   Notifications SUPP-004, built in the close-out sprint: reply/assign/resolve
 *                 notify the requester and assignee (never the actor, never the
 *                 requester for internal notes) through PlatformNotification's
 *                 in-app + email channels.
 *
 * What does work - threading, internal-note stripping, reopen-on-reply and the
 * portal boundary - is exercised properly.
 */

use App\Authorization\Role;
use App\Models\Ticket;
use App\Notifications\TicketNotification;
use App\Services\Delivery\TicketService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');

    $this->client = addMember($this->org, Role::Client);
    $this->agent = addMember($this->org, Role::Admin);

    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('runs a ticket from raised to resolved with the client thread kept clean', function () {
    $tickets = app(TicketService::class);

    $ticket = $tickets->open([
        'subject' => 'Microsoft 365 mailbox migration stalled',
        'body' => 'Two mailboxes have not migrated since Tuesday.',
        'priority' => 'high',
        'category' => 'microsoft_365',
    ], $this->client);

    expect($ticket->status)->toBe('open')
        ->and($ticket->requester_id)->toBe($this->client->id)
        ->and($ticket->organization_id)->toBe($this->org->id);

    $tickets->assign($ticket, $this->agent);
    $tickets->reply($ticket->fresh(), 'Internal: throttling on the tenant, raising with Microsoft.', $this->agent, internal: true);
    $tickets->reply($ticket->fresh(), 'We have identified the cause and expect completion today.', $this->agent);

    // The client thread must never carry the internal note.
    $thread = $tickets->clientThread($ticket->fresh());
    $bodies = collect($thread)->pluck('body')->implode(' ');

    expect($bodies)->toContain('expect completion today')
        ->and($bodies)->not->toContain('Internal:')
        ->and($bodies)->not->toContain('throttling');

    $tickets->resolve($ticket->fresh());
    expect($ticket->fresh()->status)->toBe('resolved');

    // A reply after resolution reopens it rather than vanishing.
    $tickets->reply($ticket->fresh(), 'One mailbox is still stuck.', $this->client);

    expect($ticket->fresh()->status)->toBe('open')
        ->and($ticket->fresh()->resolved_at)->toBeNull();
});

it('notifies requester and assignee through the ticket lifecycle, never the actor', function () {
    // SUPP-004 built: assignment notifies the new assignee; a public reply
    // notifies everyone on the ticket except its author; resolution notifies
    // the requester. Internal notes never reach the requester.
    Notification::fake();

    $tickets = app(TicketService::class);

    $ticket = $tickets->open([
        'subject' => 'Backup job failing nightly',
        'body' => 'The Friday backup has failed three nights running.',
    ], $this->client);

    $tickets->assign($ticket, $this->agent);
    Notification::assertSentTo($this->agent, TicketNotification::class, fn ($n) => $n->title() === 'Ticket assigned to you');

    $tickets->reply($ticket->fresh(), 'Investigating now.', $this->agent);
    Notification::assertSentTo($this->client, TicketNotification::class, fn ($n) => $n->title() === 'New reply on your ticket');

    Notification::fake(); // reset: watch the internal note in isolation
    $tickets->reply($ticket->fresh(), 'Root cause: full disk on the target.', $this->agent, internal: true);
    Notification::assertNotSentTo($this->client, TicketNotification::class);

    $tickets->resolve($ticket->fresh());
    Notification::assertSentTo($this->client, TicketNotification::class, fn ($n) => $n->title() === 'Your ticket was resolved');
});

it('cannot attach a file to a ticket', function () {
    // Pins the SUPP-002 downgrade. The polymorphic files table exists, but
    // nothing wires a ticket to it and there is no ticket upload endpoint.
    expect(Schema::hasTable('ticket_attachments'))->toBeFalse()
        ->and(Schema::getColumnListing('tickets'))->not->toContain('attachment_path')
        ->and(Schema::getColumnListing('ticket_messages'))->not->toContain('attachment_path')
        ->and(method_exists(Ticket::class, 'files'))->toBeFalse();

    // The only upload route is the general library, not ticket-scoped.
    $ticketUpload = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => str_contains($route->uri(), 'ticket') && str_contains(strtolower($route->uri()), 'file'));

    expect($ticketUpload)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Portal boundary
|--------------------------------------------------------------------------
*/

it('keeps one tenant support thread away from another', function () {
    $tickets = app(TicketService::class);

    $mine = $tickets->open([
        'subject' => 'Acme confidential outage', 'body' => 'Internal detail.',
    ], $this->client);

    app(CurrentOrganization::class)->forget();
    [$rival, $rivalOwner] = makeOrganization('Northstar Cybersecurity');
    subscribeOrganization($rival, 'enterprise');
    $rivalClient = addMember($rival, Role::Client);

    app(CurrentOrganization::class)->set($rival);
    expect(Ticket::count())->toBe(0);
    app(CurrentOrganization::class)->forget();

    // And not by URL either.
    $this->actingAs($rivalClient)->get(route('portal.support'))->assertSuccessful();
    $response = $this->actingAs($rivalClient)->get(route('portal.support'));

    expect($response->getContent())->not->toContain('Acme confidential outage');

    expect(Ticket::withoutGlobalScope('tenant')->find($mine->id))->not->toBeNull();
});

it('keeps a client out of the internal delivery views', function () {
    // The client role exists to see the portal and approve work, nothing else.
    $this->actingAs($this->client)->get(route('projects.index'))->assertForbidden();
    $this->actingAs($this->client)->get(route('crm.contacts.index'))->assertForbidden();

    $this->actingAs($this->client)->get(route('portal.dashboard'))->assertSuccessful();
});
