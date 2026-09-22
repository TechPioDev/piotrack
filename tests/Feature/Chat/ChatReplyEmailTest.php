<?php

declare(strict_types=1);

/**
 * A team reply the visitor never saw - because they had left the chat - is
 * emailed to them, once, with replies going straight back to whoever wrote
 * it. A visitor still in the chat sees it there and gets no email.
 */

use App\Jobs\EmailChatReplies;
use App\Models\ChatConversation;
use App\Models\ChatWidget;
use App\Notifications\ChatReplyNotification;
use App\Services\Chat\ChatReplyMailer;
use App\Support\CurrentOrganization;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'active', 'theme' => ['company' => 'PioManage']]);
    app(CurrentOrganization::class)->forget();

    $this->key = $this->widget->public_key;
    $token = $this->postJson("/wc/{$this->key}/conversations", ['page' => 'https://piomanage.test/pricing'])->assertOk()->json('token');
    $this->conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token);
    $this->conversation->forceFill(['answers' => [...($this->conversation->answers ?? []), 'email' => 'dana@client.test', 'first_name' => 'Dana']])->save();
});

function visitorLeft(ChatConversation $conversation, int $minutesAgo = 5): void
{
    $conversation->forceFill(['visitor_seen_at' => now()->subMinutes($minutesAgo)])->save();
}

function agentReplies($test, string $body)
{
    return $test->actingAs($test->owner)
        ->post(route('chat.conversations.reply', $test->conversation), ['body' => $body])
        ->assertSessionHasNoErrors();
}

it('emails a reply to a visitor who has left, with replies going back to the agent', function () {
    visitorLeft($this->conversation);

    agentReplies($this, 'Hi Dana - yes, we support Microsoft 365.');

    Notification::assertSentOnDemand(ChatReplyNotification::class, function (ChatReplyNotification $notification, array $channels, AnonymousNotifiable $notifiable) {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === 'dana@client.test'
            && $mail->subject === "{$this->owner->name} replied to your chat with PioManage"
            && $mail->greeting === 'Hi Dana,'
            && in_array('"Hi Dana - yes, we support Microsoft 365."', $mail->introLines, true)
            && $mail->replyTo === [[$this->owner->email, $this->owner->name]]
            && $mail->actionUrl === 'https://piomanage.test/pricing';
    });

    // The inbox shows the reply went by email.
    $this->actingAs($this->owner)->get(route('chat.conversations.show', $this->conversation))
        ->assertInertia(fn ($page) => $page->where('messages', fn ($messages) => collect($messages)->firstWhere('role', 'agent')['emailed'] === true));
});

it('sends nothing while the visitor is still in the chat', function () {
    // The widget polled a moment ago: the reply reaches them on screen.
    $this->getJson("/wc/{$this->key}/conversations/{$this->conversation->token}/poll?since=0")->assertOk();

    agentReplies($this, 'On it now.');

    Notification::assertNothingSent();
});

it('never emails a reply the widget already showed, and sends each reply once', function () {
    agentReplies($this, 'First reply');
    // The open widget picks it up...
    $this->getJson("/wc/{$this->key}/conversations/{$this->conversation->token}/poll?since=0")->assertOk();
    // ...then the visitor closes the chat, and the agent writes again twice.
    visitorLeft($this->conversation->refresh());
    Notification::fake();

    agentReplies($this, 'Second reply');
    agentReplies($this, 'Third reply');

    Notification::assertSentOnDemandTimes(ChatReplyNotification::class, 2);
    $sent = [];
    Notification::assertSentOnDemand(ChatReplyNotification::class, function (ChatReplyNotification $n, array $c, AnonymousNotifiable $to) use (&$sent) {
        $sent[] = $n->toMail($to)->introLines;

        return true;
    });
    $lines = collect($sent)->flatten()->implode(' ');
    expect($lines)->not->toContain('First reply')->toContain('Second reply')->toContain('Third reply');

    // A later run of the same check finds nothing new to send.
    Notification::fake();
    (new EmailChatReplies($this->conversation->id, $this->org->id))->handle(app(ChatReplyMailer::class), app(CurrentOrganization::class));
    Notification::assertNothingSent();
});

it('sends nothing without an email address, or when the widget turns it off', function () {
    visitorLeft($this->conversation);
    $this->conversation->forceFill(['answers' => ['first_name' => 'Dana']])->save();
    agentReplies($this, 'Anyone there?');
    Notification::assertNothingSent();

    $this->conversation->forceFill(['answers' => ['email' => 'dana@client.test']])->save();
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update(['settings' => ['email_replies' => false]]);
    app(CurrentOrganization::class)->forget();
    agentReplies($this, 'Still here if you need us.');
    Notification::assertNothingSent();
});

it('learns from the widget\'s own polling how far the visitor has read', function () {
    agentReplies($this, 'Hello!');
    $agentMessage = $this->conversation->messages()->where('role', 'agent')->sole();

    $this->getJson("/wc/{$this->key}/conversations/{$this->conversation->token}/poll?since=0")->assertOk();

    $conversation = $this->conversation->refresh();
    expect($conversation->visitor_seen_message_id)->toBe($agentMessage->id)
        ->and($conversation->visitor_seen_at->gt(now()->subMinute()))->toBeTrue();
});
