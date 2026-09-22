<?php

declare(strict_types=1);

/**
 * An existing customer who asks the chat for support gets a ticket on the
 * support desk - with what they told us and the whole conversation - instead
 * of a closed chat nobody follows up. Never a sales lead.
 */

use App\Models\ChatConversation;
use App\Models\ChatWidget;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Ticket;
use App\Services\Chat\ChatFlowTemplates;
use App\Services\Chat\DefaultChatFlow;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();

    $this->key = $this->widget->public_key;
});

/** @param list<array<string, string>> $answers */
function askForSupport($test, array $answers): string
{
    $token = $test->postJson("/wc/{$test->key}/conversations", ['page' => 'https://piomanage.test/support'])->assertOk()->json('token');
    foreach ($answers as $answer) {
        $test->postJson("/wc/{$test->key}/conversations/{$token}/messages", $answer)->assertOk();
    }

    return $token;
}

it('opens a support ticket with what the customer told us and the whole conversation', function () {
    $token = askForSupport($this, [
        ['option' => 'existing'],
        ['option' => 'billing'],
        ['value' => 'dana@client.test'],
        ['value' => 'Our last invoice has the wrong seat count'],
    ]);

    $ticket = Ticket::withoutGlobalScope('tenant')->sole();
    expect($ticket->organization_id)->toBe($this->org->id)
        ->and($ticket->subject)->toBe('Website chat: Billing from dana@client.test')
        ->and($ticket->status)->toBe('open')
        ->and($ticket->priority)->toBe('normal')
        ->and($ticket->category)->toBe('website_chat')
        ->and($ticket->body)->toContain('Email: dana@client.test')
        ->toContain('Request: Billing')
        ->toContain('Details: Our last invoice has the wrong seat count')
        ->toContain('Page: https://piomanage.test/support')
        ->toContain("Chat transcript\n")
        ->toContain('Visitor: Existing Customer Support')
        ->toContain('Bot: What email address should we reply to?');

    // The chat is done and points at its ticket; nothing reached sales.
    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token);
    expect($conversation->status)->toBe('closed')
        ->and($conversation->answers['_ticket'])->toBe($ticket->id)
        ->and(Lead::withoutGlobalScope('tenant')->count())->toBe(0)
        ->and(Contact::withoutGlobalScope('tenant')->count())->toBe(0);

    $this->actingAs($this->owner)->get(route('chat.conversations.show', $conversation))
        ->assertInertia(fn ($page) => $page
            ->where('conversation.ticket_id', $ticket->id)
            ->missing('conversation.answers._ticket'));
});

it('does the same for the existing-customer template', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update(['flow' => app(ChatFlowTemplates::class)->flow('existing_customer')]);
    app(CurrentOrganization::class)->forget();

    askForSupport($this, [
        ['option' => 'support'],
        ['value' => 'sam@client.test'],
        ['value' => 'Outlook keeps asking for my password'],
    ]);

    expect(Ticket::withoutGlobalScope('tenant')->sole()->subject)->toBe('Website chat: Technical support from sam@client.test');
});

it('never opens a ticket from a builder preview', function () {
    $this->actingAs($this->owner);
    $flow = $this->widget->flow ?? DefaultChatFlow::definition();

    $token = $this->postJson(route('chat.flow.test', $this->widget), ['flow' => $flow])->assertOk()->json('token');
    foreach ([['option' => 'existing'], ['option' => 'billing'], ['value' => 'dana@client.test'], ['value' => 'A question']] as $answer) {
        $this->postJson(route('chat.flow.test', $this->widget), ['flow' => $flow, 'token' => $token, ...$answer])->assertOk();
    }

    expect(Ticket::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('files the ticket with the widget\'s own organization only', function () {
    [$rival, $rivalOwner] = makeOrganization('Rival MSP');
    subscribeOrganization($rival, 'enterprise');

    askForSupport($this, [['option' => 'existing'], ['option' => 'technical'], ['value' => 'dana@client.test'], ['value' => 'VPN is down']]);

    app(CurrentOrganization::class)->set($rival);
    expect(Ticket::count())->toBe(0);
    app(CurrentOrganization::class)->set($this->org);
    expect(Ticket::count())->toBe(1);
    app(CurrentOrganization::class)->forget();
});
