<?php

declare(strict_types=1);

/**
 * The step that hands a conversation to the owner's own system.
 *
 * This is how a chat reaches a PSA, a Zapier hook or an internal API while the
 * visitor is still in it - and how a value from the reply ("your ticket is
 * 4821") gets back into the conversation. Someone else's server having a bad
 * day must never strand a visitor, and a tenant must never be able to point it
 * at our own network.
 */

use App\Models\ChatConversation;
use App\Models\ChatWidget;
use App\Services\Chat\ChatFlowValidator;
use App\Support\CurrentOrganization;
use App\Support\UrlGuard;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();
    $this->key = $this->widget->public_key;

    // The SSRF guard resolves hostnames for real. These tests must not depend on
    // DNS, so a double waves through the example host and defers to the real
    // guard for everything else - including the literal address below, which
    // needs no lookup and so exercises the guard properly.
    $guard = Mockery::mock(UrlGuard::class);
    $guard->shouldReceive('assertFetchable')->andReturnUsing(function (string $url) {
        if (! str_starts_with($url, 'https://desk.example.com')) {
            (new UrlGuard)->assertFetchable($url);
        }
    });
    app()->instance(UrlGuard::class, $guard);
});

function flowThatPosts(string $url, ?string $fallback = null): array
{
    $hook = ['type' => 'webhook', 'url' => $url, 'path' => 'ticket.number', 'field' => 'ticket_number', 'next' => 'told'];
    if ($fallback !== null) {
        $hook['fallback'] = $fallback;
    }

    return [
        'start' => 'ask',
        'nodes' => [
            'ask' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'Your email?', 'next' => 'hook'],
            'hook' => $hook,
            'told' => ['type' => 'message', 'text' => 'Your ticket is {{ticket_number}}.', 'next' => 'done'],
            'sorry' => ['type' => 'message', 'text' => 'We could not reach the desk, so a person will follow up.', 'next' => 'done'],
            'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks!'],
        ],
    ];
}

function runPosting($test, array $flow): array
{
    app(CurrentOrganization::class)->set($test->org);
    $test->widget->forceFill(['flow' => $flow])->save();
    app(CurrentOrganization::class)->forget();

    $token = $test->postJson("/wc/{$test->key}/conversations", ['page' => 'https://piomanage.test/support'])->assertOk()->json('token');
    $reply = $test->postJson("/wc/{$test->key}/conversations/{$token}/messages", ['value' => 'someone@piomanage.test'])->assertOk()->json();

    return [$token, $reply];
}

it('posts what the visitor answered, and keeps a value from the reply for a later step', function () {
    Http::fake(['desk.example.com/*' => Http::response(['ticket' => ['number' => 4821]], 200)]);

    [$token, $reply] = runPosting($this, flowThatPosts('https://desk.example.com/chat'));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://desk.example.com/chat'
            && $request['answers']['email'] === 'someone@piomanage.test'
            && $request['page'] === 'https://piomanage.test/support'
            && ! array_key_exists('_node', $request['answers']);
    });

    expect(collect($reply['messages'])->pluck('body')->implode(' '))->toContain('Your ticket is 4821.');
    expect(ChatConversation::withoutGlobalScopes()->where('token', $token)->first()->answers['ticket_number'])->toBe('4821');
});

it('carries on down the fallback when the other end is down, and never shows the visitor an error', function () {
    Http::fake(['desk.example.com/*' => Http::response('nope', 500)]);

    [, $reply] = runPosting($this, flowThatPosts('https://desk.example.com/chat', 'sorry'));

    $said = collect($reply['messages'])->pluck('body')->implode(' ');
    expect($said)->toContain('a person will follow up');
    expect($said)->not->toContain('500');
});

it('carries straight on when it fails and the flow named no fallback', function () {
    Http::fake(['desk.example.com/*' => Http::response('', 503)]);

    [, $reply] = runPosting($this, flowThatPosts('https://desk.example.com/chat'));

    // Nothing kept, so the placeholder disappears rather than showing braces.
    expect(collect($reply['messages'])->pluck('body')->implode(' '))->toContain('Your ticket is');
    expect(collect($reply['messages'])->pluck('body')->implode(' '))->not->toContain('{{');
});

it('refuses to call our own network, whatever a tenant types', function () {
    Http::fake();

    [, $reply] = runPosting($this, flowThatPosts('https://169.254.169.254/latest/meta-data/', 'sorry'));

    Http::assertNothingSent();
    expect(collect($reply['messages'])->pluck('body')->implode(' '))->toContain('a person will follow up');
});

it('never calls anyone from a builder preview', function () {
    Http::fake();
    $this->actingAs($this->owner);
    app(CurrentOrganization::class)->set($this->org);

    $this->postJson(route('chat.flow.test', $this->widget), [
        'flow' => flowThatPosts('https://desk.example.com/chat'),
        'value' => 'someone@piomanage.test',
    ])->assertOk();

    app(CurrentOrganization::class)->forget();
    Http::assertNothingSent();
});

it('will not publish a step with no address, or one that is not https', function () {
    $validator = app(ChatFlowValidator::class);

    $missing = $validator->validate(flowThatPosts(''));
    expect($missing['valid'])->toBeFalse();
    expect(collect($missing['errors'])->pluck('message')->implode(' '))->toContain('does not say which address');

    $plain = $validator->validate(flowThatPosts('http://desk.example.com/chat'));
    expect($plain['valid'])->toBeFalse();
    expect(collect($plain['errors'])->pluck('message')->implode(' '))->toContain('https://');

    expect($validator->validate(flowThatPosts('https://desk.example.com/chat'))['valid'])->toBeTrue();
});
