<?php

declare(strict_types=1);

/**
 * What a finished chat sends onwards, and what a step can say.
 *
 * A chat lead used to stop inside the product: a form lead fired the
 * `lead.captured` webhook an owner wires to Zapier, n8n or their PSA, and a
 * chat lead fired nothing. Tags were the same story - the "Add Tag" step wrote
 * them where nothing read them. And a step could not greet anyone by name.
 */

use App\Jobs\DeliverWebhook;
use App\Models\ChatConversation;
use App\Models\ChatWidget;
use App\Models\WebhookEndpoint;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();

    $this->key = $this->widget->public_key;
});

/** A short flow: tag it, ask for an email, finish as a lead. */
function taggedLeadFlow(): array
{
    return [
        'start' => 'tag_it',
        'nodes' => [
            'tag_it' => ['type' => 'tag', 'tag' => 'managed-services', 'next' => 'ask_name'],
            'ask_name' => ['type' => 'input', 'input' => 'text', 'field' => 'first_name', 'text' => 'What is your first name?', 'next' => 'ask_email'],
            'ask_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'Thanks {{first_name}}, what is your email?', 'next' => 'done'],
            'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Speak soon, {{first_name}}!'],
        ],
    ];
}

function walkChatFlow($test, array $flow, array $replies): ChatConversation
{
    app(CurrentOrganization::class)->set($test->org);
    $test->widget->forceFill(['flow' => $flow])->save();
    app(CurrentOrganization::class)->forget();

    $token = $test->postJson("/wc/{$test->key}/conversations", ['page' => 'https://piomanage.test/'])->assertOk()->json('token');
    foreach ($replies as $reply) {
        $test->postJson("/wc/{$test->key}/conversations/{$token}/messages", ['value' => $reply])->assertOk();
    }

    return ChatConversation::withoutGlobalScopes()->where('token', $token)->firstOrFail();
}

it('sends a chat lead to the same webhook a form lead uses, with its tags and answers', function () {
    Queue::fake();
    app(CurrentOrganization::class)->set($this->org);
    WebhookEndpoint::create([
        'url' => 'https://hooks.example.test/psa',
        'secret' => 'shh',
        'events' => ['lead.captured'],
        'is_active' => true,
    ]);
    app(CurrentOrganization::class)->forget();

    walkChatFlow($this, taggedLeadFlow(), ['Dalbeir', 'dalbeir@piomanage.test']);

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
        return $job->event === 'lead.captured'
            && $job->payload['source'] === 'website_chat'
            && $job->payload['email'] === 'dalbeir@piomanage.test'
            && $job->payload['tags'] === ['managed-services']
            && $job->payload['answers']['first_name'] === 'Dalbeir'
            // The engine's own bookkeeping never leaves the building.
            && ! array_key_exists('_node', $job->payload['answers']);
    });
});

it('keeps the tags a conversation collected on the conversation, where the team can see them', function () {
    $conversation = walkChatFlow($this, taggedLeadFlow(), ['Dalbeir', 'dalbeir@piomanage.test']);

    expect($conversation->tags)->toBe(['managed-services']);

    $this->actingAs($this->owner)
        ->get("/chat/conversations/{$conversation->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('conversation.tags', ['managed-services']));
});

it('fills a step with what the visitor already told us', function () {
    $conversation = walkChatFlow($this, taggedLeadFlow(), ['Dalbeir', 'dalbeir@piomanage.test']);
    $said = $conversation->messages()->where('role', 'bot')->pluck('body')->all();

    expect($said)->toContain('Thanks Dalbeir, what is your email?');
    expect($said)->toContain('Speak soon, Dalbeir!');
});

it('reads well when the answer is not there, and never shows the visitor a placeholder', function () {
    $flow = [
        'start' => 'hello',
        'nodes' => [
            'hello' => ['type' => 'message', 'text' => 'Hello {{first_name|there}}! {{company_name}} We help with IT.', 'next' => 'done'],
            'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Bye {{first_name}}'],
        ],
    ];

    $conversation = walkChatFlow($this, $flow, []);
    $said = $conversation->messages()->where('role', 'bot')->pluck('body')->all();

    expect($said[0])->toBe('Hello there! We help with IT.');
    expect($said)->toContain('Bye');
    foreach ($said as $line) {
        expect($line)->not->toContain('{{');
    }
});

it('fills the question the widget is still waiting on, not just the lines already said', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->forceFill(['flow' => taggedLeadFlow()])->save();
    app(CurrentOrganization::class)->forget();

    $token = $this->postJson("/wc/{$this->key}/conversations", ['page' => 'https://piomanage.test/'])->assertOk()->json('token');
    $this->postJson("/wc/{$this->key}/conversations/{$token}/messages", ['value' => 'Dalbeir'])->assertOk();

    // Reopening replays the pending question: it must be filled in too.
    $this->getJson("/wc/{$this->key}/conversations/{$token}/poll?since=0&transcript=1")
        ->assertOk()
        ->assertJsonPath('node.text', 'Thanks Dalbeir, what is your email?');
});

it('tells the widget how long to hold a message back, and caps the wait', function () {
    $flow = [
        'start' => 'first',
        'nodes' => [
            'first' => ['type' => 'message', 'text' => 'Hi there!', 'next' => 'second'],
            'second' => ['type' => 'message', 'text' => 'One moment while I look that up.', 'delay' => 2, 'next' => 'third'],
            'third' => ['type' => 'message', 'text' => 'Here you go.', 'delay' => 99, 'next' => 'done'],
            'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Bye!'],
        ],
    ];

    app(CurrentOrganization::class)->set($this->org);
    $this->widget->forceFill(['flow' => $flow])->save();
    app(CurrentOrganization::class)->forget();

    $messages = $this->postJson("/wc/{$this->key}/conversations", ['page' => 'https://piomanage.test/'])
        ->assertOk()
        ->json('messages');

    expect($messages[0])->not->toHaveKey('delay');
    expect((float) $messages[1]['delay'])->toBe(2.0);
    // Nobody waits a minute and a half for a chat line.
    expect((float) $messages[2]['delay'])->toBe(10.0);
});
