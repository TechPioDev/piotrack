<?php

declare(strict_types=1);

/**
 * Three things the inbox and the widget were missing.
 *
 * Handing a conversation to a colleague was possible through the API and
 * nowhere on the screen; there was no way to take a transcript out of the
 * product at all; and nothing told a visitor they were talking to a machine,
 * which the EU AI Act has required since 2 August 2026.
 */

use App\Authorization\Role;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatWidget;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'active']);
    $this->conversation = ChatConversation::create([
        'chat_widget_id' => $this->widget->id,
        'token' => 'tok-inbox-gaps',
        'visitor_id' => 'v-9',
        'status' => 'open',
    ]);
    ChatMessage::create(['chat_conversation_id' => $this->conversation->id, 'role' => 'bot', 'body' => 'Hi! How can we help?']);
    ChatMessage::create(['chat_conversation_id' => $this->conversation->id, 'role' => 'visitor', 'body' => 'Our email is down.']);
    ChatMessage::create(['chat_conversation_id' => $this->conversation->id, 'role' => 'note', 'body' => 'Existing client, gold cover.']);
    app(CurrentOrganization::class)->forget();
});

it('offers the team to hand a conversation to, and hands it over', function () {
    $this->actingAs($this->owner)
        ->get("/chat/conversations/{$this->conversation->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('teammates.0.name', $this->owner->name));

    $this->actingAs($this->owner)
        ->patch("/chat/conversations/{$this->conversation->id}", ['assignee_id' => $this->owner->id])
        ->assertRedirect();

    expect(ChatConversation::withoutGlobalScopes()->find($this->conversation->id)->assignee_id)->toBe($this->owner->id);

    // And back to nobody, which is how a conversation returns to the queue.
    $this->actingAs($this->owner)
        ->patch("/chat/conversations/{$this->conversation->id}", ['assignee_id' => null])
        ->assertRedirect();

    expect(ChatConversation::withoutGlobalScopes()->find($this->conversation->id)->assignee_id)->toBeNull();
});

it('downloads the whole conversation as a text file, internal notes and all', function () {
    $response = $this->actingAs($this->owner)
        ->get("/chat/conversations/{$this->conversation->id}/transcript")
        ->assertOk()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8');

    $text = $response->streamedContent();

    expect($text)->toContain('Conversation #'.$this->conversation->id)
        ->and($text)->toContain('PioManage website')
        ->and($text)->toContain('Our email is down.')
        ->and($text)->toContain('Internal note: Existing client, gold cover.');
});

it('keeps a transcript away from someone who may not read the inbox', function () {
    // Billing administrators run the subscription; they have no business
    // reading what customers said in chat.
    $billing = addMember($this->org, Role::BillingAdministrator);

    $this->actingAs($billing)
        ->get("/chat/conversations/{$this->conversation->id}/transcript")
        ->assertForbidden();
});

it('tells a visitor they are talking to a machine, in the owner’s own words if they wrote some', function () {
    $key = $this->widget->public_key;

    $this->getJson("/wc/{$key}/config")
        ->assertOk()
        ->assertJsonPath('ai_notice', 'You are chatting with an automated assistant. Ask for a person at any time.');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget->forceFill(['settings' => ['ai_disclosure_text' => 'Pio is our automated assistant. Say "person" for a human.']])->save();
    app(CurrentOrganization::class)->forget();

    $this->getJson("/wc/{$key}/config")
        ->assertOk()
        ->assertJsonPath('ai_notice', 'Pio is our automated assistant. Say "person" for a human.');
});

it('says nothing when the chat goes straight to a person, or when the owner turns it off', function () {
    $key = $this->widget->public_key;

    app(CurrentOrganization::class)->set($this->org);
    $this->widget->forceFill(['settings' => ['mode' => 'live']])->save();
    app(CurrentOrganization::class)->forget();
    $this->getJson("/wc/{$key}/config")->assertOk()->assertJsonPath('ai_notice', null);

    app(CurrentOrganization::class)->set($this->org);
    $this->widget->forceFill(['settings' => ['mode' => 'bot', 'ai_disclosure' => false]])->save();
    app(CurrentOrganization::class)->forget();
    $this->getJson("/wc/{$key}/config")->assertOk()->assertJsonPath('ai_notice', null);
});
