<?php

declare(strict_types=1);

/**
 * "How did we do?" - one star to five, asked once the chat has finished.
 *
 * The score belongs to the conversation the team reads and to the chat
 * analytics, so an owner can see whether the conversations are any good rather
 * than only how many there were.
 */

use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatWidget;
use App\Services\Chat\ChatAnalyticsService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();

    $this->key = $this->widget->public_key;
});

function chatToRate($test): string
{
    return $test->postJson("/wc/{$test->key}/conversations", ['page' => 'https://piomanage.test/'])->assertOk()->json('token');
}

it('keeps the score a visitor gives, and lets them change their mind', function () {
    $token = chatToRate($this);

    $this->postJson("/wc/{$this->key}/conversations/{$token}/rating", ['rating' => 4])->assertOk();
    $conversation = ChatConversation::withoutGlobalScopes()->where('token', $token)->firstOrFail();
    expect($conversation->rating)->toBe(4);
    expect($conversation->rated_at)->not->toBeNull();

    $this->postJson("/wc/{$this->key}/conversations/{$token}/rating", ['rating' => 5])->assertOk();
    expect(ChatConversation::withoutGlobalScopes()->where('token', $token)->value('rating'))->toBe(5);

    expect(ChatEvent::withoutGlobalScopes()->where('type', 'rating')->count())->toBe(2);
});

it('refuses a score that is not one to five', function () {
    $token = chatToRate($this);

    $this->postJson("/wc/{$this->key}/conversations/{$token}/rating", ['rating' => 9])->assertStatus(422);
    $this->postJson("/wc/{$this->key}/conversations/{$token}/rating", ['rating' => 0])->assertStatus(422);
    $this->postJson("/wc/{$this->key}/conversations/{$token}/rating", [])->assertStatus(422);

    expect(ChatConversation::withoutGlobalScopes()->where('token', $token)->value('rating'))->toBeNull();
});

it('does not ask, or accept, when the owner has turned the question off', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->forceFill(['settings' => [...($this->widget->settings ?? []), 'rating' => false]])->save();
    app(CurrentOrganization::class)->forget();

    $this->getJson("/wc/{$this->key}/config")->assertOk()->assertJsonPath('rating', false);

    $token = chatToRate($this);
    $this->postJson("/wc/{$this->key}/conversations/{$token}/rating", ['rating' => 5])->assertNotFound();
});

it('tells the widget to ask by default', function () {
    $this->getJson("/wc/{$this->key}/config")->assertOk()->assertJsonPath('rating', true);
});

it('shows the score on the conversation and averages it in the analytics', function () {
    $tokens = [chatToRate($this), chatToRate($this), chatToRate($this)];
    foreach ([5, 4, 3] as $index => $score) {
        $this->postJson("/wc/{$this->key}/conversations/{$tokens[$index]}/rating", ['rating' => $score])->assertOk();
    }

    $conversation = ChatConversation::withoutGlobalScopes()->where('token', $tokens[0])->firstOrFail();
    $this->actingAs($this->owner)
        ->get("/chat/conversations/{$conversation->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('conversation.rating', 5));

    app(CurrentOrganization::class)->set($this->org);
    $summary = app(ChatAnalyticsService::class)->summary();
    app(CurrentOrganization::class)->forget();

    expect($summary['ratings'])->toBe(3);
    expect($summary['rating'])->toBe(4.0);
});

it('reports no average at all when nobody has rated', function () {
    chatToRate($this);

    app(CurrentOrganization::class)->set($this->org);
    $summary = app(ChatAnalyticsService::class)->summary();
    app(CurrentOrganization::class)->forget();

    expect($summary['ratings'])->toBe(0);
    expect($summary['rating'])->toBeNull();
});
