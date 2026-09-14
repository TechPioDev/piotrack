<?php

use App\Ai\AiCompletion;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Plan;
use App\Services\Ai\AiGateway;
use App\Support\CurrentOrganization;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

/**
 * Regression: `share()` did not expose session flash, so every
 * `back()->with('status', …)` confirmation in the app (147 of them across
 * Stages 2–12) was invisible to the user.
 */
it('shares the status flash with the client', function () {
    [$org, $owner] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Ann', 'email' => 'ann@x.com']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)
        ->post(route('ai.agent.crm-update'), ['contact_id' => $contact->id, 'changes' => ['title' => 'Director']])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('ai.actions.index'))
        ->assertInertia(fn ($page) => $page->where('flash.status', 'Change proposed — it needs approval before it is applied.'));
});

it('shares an ai_result flash with the client', function () {
    [$org, $owner] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Bob', 'email' => 'bob@x.com']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)
        ->post(route('ai.agent.run'), ['contact_id' => $contact->id, 'task' => 'qualify'])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('ai.agent.index'))
        ->assertInertia(fn ($page) => $page->has('flash.ai_result.qualified'));
});

it('shares a null flash when nothing was flashed', function () {
    [, $owner] = aiOrganization();

    $this->actingAs($owner)
        ->get(route('ai.dashboard'))
        ->assertInertia(fn ($page) => $page->where('flash.status', null)->where('flash.error', null));
});

/*
 * UI-P4 regressions: three refusals were flashed under keys the client never
 * received, so each one failed silently.
 */
it('shows an expired session as an error instead of silently bouncing back', function () {
    [$org, $owner] = makeOrganization('Flash Org');
    subscribeOrganization($org, 'enterprise');
    Route::middleware('web')->post('/__qa/expired-session', fn () => throw new TokenMismatchException);

    $this->actingAs($owner)->from(route('ai.dashboard'))->post('/__qa/expired-session')
        ->assertRedirect(route('ai.dashboard'));

    $this->actingAs($owner)
        ->get(route('ai.dashboard'))
        ->assertInertia(fn ($page) => $page->where('flash.error', 'Your session expired — please try again.'));
});

it('tells the user when an invitation link is no longer valid', function () {
    [$org, $owner] = makeOrganization('Flash Org');
    subscribeOrganization($org, 'enterprise');

    $this->actingAs($owner)->post(route('invitations.accept', 'no-such-token'))->assertRedirect(route('dashboard'));

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('flash.error', 'This invitation is no longer valid.'));
});

it('tells the user why a custom-priced plan cannot be checked out', function () {
    [$org, $owner] = makeOrganization('Flash Org');
    subscribeOrganization($org, 'enterprise');
    $plan = Plan::where('code', 'enterprise')->firstOrFail();
    $plan->forceFill(['is_custom_priced' => true])->save();

    $this->actingAs($owner)->get(route('billing.checkout.show', ['plan' => 'enterprise', 'interval' => 'monthly']))
        ->assertRedirect(route('billing.plans'));

    $this->actingAs($owner)
        ->get(route('billing.plans'))
        ->assertInertia(fn ($page) => $page->where('flash.error', 'Contact sales for Enterprise pricing.'));
});

it('delivers a drafted copy to the content page that displays it', function () {
    [$org, $owner] = makeOrganization('Draft Org');
    subscribeOrganization($org, 'enterprise');
    app(CurrentOrganization::class)->set($org);
    $piece = ContentPiece::create(['title' => 'Managed IT', 'slug' => 'managed-it', 'content_type' => 'article', 'status' => 'draft', 'body' => 'RMM and patching.']);
    app(CurrentOrganization::class)->forget();

    $gateway = Mockery::mock(AiGateway::class);
    $gateway->shouldReceive('run')->once()
        ->andReturn(new AiCompletion(text: "HEADLINE: Faster IT\nCTA: Book a call.", promptTokens: 5, completionTokens: 5, model: 'fixture-1'));
    app()->instance(AiGateway::class, $gateway);

    $this->actingAs($owner)->from(route('content.pieces.show', $piece))
        ->post(route('content.pieces.draft-copy', $piece), ['focus' => 'conversion'])
        ->assertRedirect(route('content.pieces.show', $piece));

    // The page reads flash.draft; before UI-P4 the session held it but the page never received it.
    $this->actingAs($owner)
        ->get(route('content.pieces.show', $piece))
        ->assertInertia(fn ($page) => $page->where('flash.draft', fn ($draft) => str_contains((string) $draft, 'Faster IT')));
});
