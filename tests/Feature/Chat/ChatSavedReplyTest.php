<?php

declare(strict_types=1);

/**
 * The answers a team types over and over, kept once and shared.
 *
 * Saved replies belong to the workspace rather than to one agent: whoever is
 * on chat should give the same answer to "what does onboarding cost?".
 */

use App\Authorization\Role;
use App\Models\ChatConversation;
use App\Models\ChatSavedReply;
use App\Models\ChatWidget;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'active']);
    $this->conversation = ChatConversation::create([
        'chat_widget_id' => $this->widget->id,
        'token' => 'tok-saved-reply',
        'visitor_id' => 'v-1',
        'status' => 'open',
    ]);
    app(CurrentOrganization::class)->forget();
});

it('keeps a reply for the whole team and offers it on the conversation', function () {
    $this->actingAs($this->owner)
        ->post('/chat/saved-replies', ['title' => 'Pricing', 'body' => 'Hi {{first_name}}, onboarding is a one-off fee.'])
        ->assertRedirect();

    $this->actingAs($this->owner)
        ->get("/chat/conversations/{$this->conversation->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('saved_replies.0.title', 'Pricing')
            ->where('saved_replies.0.body', 'Hi {{first_name}}, onboarding is a one-off fee.'));
});

it('refuses a saved reply with no name or no words', function () {
    $this->actingAs($this->owner)->post('/chat/saved-replies', ['title' => '', 'body' => 'Something'])->assertSessionHasErrors('title');
    $this->actingAs($this->owner)->post('/chat/saved-replies', ['title' => 'Pricing', 'body' => ''])->assertSessionHasErrors('body');

    app(CurrentOrganization::class)->set($this->org);
    expect(ChatSavedReply::query()->count())->toBe(0);
    app(CurrentOrganization::class)->forget();
});

it('removes one when the team no longer wants it', function () {
    app(CurrentOrganization::class)->set($this->org);
    $saved = ChatSavedReply::create(['title' => 'Out of hours', 'body' => 'We are closed.']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->delete("/chat/saved-replies/{$saved->id}")->assertRedirect();

    expect(ChatSavedReply::withoutGlobalScopes()->count())->toBe(0);
});

it('keeps one workspace’s replies out of another’s', function () {
    app(CurrentOrganization::class)->set($this->org);
    ChatSavedReply::create(['title' => 'Pricing', 'body' => 'Ours.']);
    app(CurrentOrganization::class)->forget();

    [$other, $otherOwner] = makeOrganization('Someone Else');
    subscribeOrganization($other, 'enterprise');
    app(CurrentOrganization::class)->set($other);
    $theirWidget = ChatWidget::create(['name' => 'Their website', 'status' => 'active']);
    $theirs = ChatConversation::create([
        'chat_widget_id' => $theirWidget->id,
        'token' => 'tok-theirs',
        'visitor_id' => 'v-2',
        'status' => 'open',
    ]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($otherOwner)
        ->get("/chat/conversations/{$theirs->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('saved_replies', []));
});

it('lets nobody without the inbox permission keep or remove one', function () {
    $member = addMember($this->org, Role::Viewer);

    $this->actingAs($member)->post('/chat/saved-replies', ['title' => 'Pricing', 'body' => 'Nope.'])->assertForbidden();

    app(CurrentOrganization::class)->set($this->org);
    $saved = ChatSavedReply::create(['title' => 'Out of hours', 'body' => 'We are closed.']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($member)->delete("/chat/saved-replies/{$saved->id}")->assertForbidden();
    expect(ChatSavedReply::withoutGlobalScopes()->count())->toBe(1);
});
