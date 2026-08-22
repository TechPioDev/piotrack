<?php

declare(strict_types=1);

/**
 * Website Chat Phase 4 — analytics, targeting and progressive profiling.
 *
 * Reporting is only useful if it is honest, so these tests pin the arithmetic:
 * every number must come from something that actually happened, previews must
 * never inflate a funnel, and a rate with no denominator must be zero rather
 * than a divide-by-zero or an invented figure.
 */

use App\Authorization\Role;
use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatWidget;
use App\Models\Contact;
use App\Services\Chat\ChatAnalyticsService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create([
        'name' => 'Homepage',
        'status' => 'active',
        'flow' => [
            'start' => 'q_service',
            'nodes' => [
                'q_service' => [
                    'type' => 'choice', 'text' => 'What can we help with?', 'field' => 'service',
                    'options' => [['id' => 'it', 'label' => 'Managed IT', 'score' => 10, 'next' => 'in_email']],
                ],
                'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'Your email?', 'next' => 'done'],
                'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks!'],
            ],
        ],
    ]);
    app(CurrentOrganization::class)->forget();
});

/** Convenience: run the whole flow to completion for one visitor. */
function completeChat(string $key, string $email, ?string $visitor = null): string
{
    $start = test()->postJson("/wc/{$key}/conversations", array_filter(['visitor' => $visitor]));
    $token = $start->json('token');
    test()->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => 'it']);
    test()->postJson("/wc/{$key}/conversations/{$token}/messages", ['value' => $email]);

    return $token;
}

// ----------------------------------------------------------------- analytics

it('counts only what actually happened', function () {
    $key = $this->widget->public_key;

    // Two visitors see the widget, one opens it and finishes.
    $this->postJson("/wc/{$key}/events", ['type' => 'impression']);
    $this->postJson("/wc/{$key}/events", ['type' => 'impression']);
    $this->postJson("/wc/{$key}/events", ['type' => 'open']);
    completeChat($key, 'one@example.test');

    app(CurrentOrganization::class)->set($this->org);
    $summary = app(ChatAnalyticsService::class)->summary();
    app(CurrentOrganization::class)->forget();

    expect($summary['impressions'])->toBe(2)
        ->and($summary['opens'])->toBe(1)
        ->and($summary['conversations'])->toBe(1)
        ->and($summary['leads'])->toBe(1)
        ->and($summary['open_rate'])->toBe(50.0)
        ->and($summary['lead_rate'])->toBe(100.0);
});

it('reports zero rather than dividing by zero when nothing has happened', function () {
    app(CurrentOrganization::class)->set($this->org);
    $summary = app(ChatAnalyticsService::class)->summary();
    $funnel = app(ChatAnalyticsService::class)->funnel();
    app(CurrentOrganization::class)->forget();

    expect($summary['open_rate'])->toBe(0.0)
        ->and($summary['lead_rate'])->toBe(0.0)
        ->and($summary['revenue'])->toBe(0)
        ->and($funnel[0]['rate'])->toBeNull()          // nothing above the first rung
        ->and($funnel[1]['rate'])->toBe(0.0);
});

it('never lets a builder preview inflate the funnel', function () {
    $this->actingAs($this->owner);

    // A real conversation plus three previews.
    completeChat($this->widget->public_key, 'real@example.test');
    $flow = $this->widget->flow;
    foreach (range(1, 3) as $ignored) {
        $this->postJson(route('chat.flow.test', $this->widget), ['flow' => $flow]);
    }

    app(CurrentOrganization::class)->set($this->org);
    $summary = app(ChatAnalyticsService::class)->summary();
    app(CurrentOrganization::class)->forget();

    expect($summary['conversations'])->toBe(1);
});

it('shows where visitors stop answering', function () {
    $key = $this->widget->public_key;

    // One finishes; two stall on the email question.
    completeChat($key, 'finisher@example.test');
    foreach (['a', 'b'] as $ignored) {
        $token = $this->postJson("/wc/{$key}/conversations")->json('token');
        $this->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => 'it']);
    }

    app(CurrentOrganization::class)->set($this->org);
    $rows = app(ChatAnalyticsService::class)->dropOff();
    app(CurrentOrganization::class)->forget();

    $email = collect($rows)->firstWhere('node', 'in_email');
    expect($email)->not->toBeNull()
        ->and($email['abandoned'])->toBe(2)
        ->and($email['label'])->toBe('Your email?');

    // The worst leak is reported first, because that is what to fix next.
    expect($rows[0]['node'])->toBe('in_email');
});

it('compares widgets without claiming a winner', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update(['settings' => ['experiment' => 'greeting', 'variant' => 'A']]);
    app(CurrentOrganization::class)->forget();

    completeChat($this->widget->public_key, 'a@example.test');

    app(CurrentOrganization::class)->set($this->org);
    $rows = app(ChatAnalyticsService::class)->byWidget();
    app(CurrentOrganization::class)->forget();

    $row = collect($rows)->firstWhere('id', $this->widget->id);
    expect($row['experiment'])->toBe('greeting')
        ->and($row['variant'])->toBe('A')
        ->and($row['conversations'])->toBe(1)
        ->and($row['lead_rate'])->toBe(100.0)
        // No significance/winner field is produced at all.
        ->and($row)->not->toHaveKey('significance')
        ->and($row)->not->toHaveKey('winner');
});

it('serves the analytics page and rejects another tenant widget filter', function () {
    [$otherOrg] = makeOrganization('Rival MSP');
    subscribeOrganization($otherOrg, 'enterprise');
    app(CurrentOrganization::class)->set($otherOrg);
    $rivalWidget = ChatWidget::create(['name' => 'Rival', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();

    // Filtering by a widget from another tenant falls back to "all", never leaks.
    $response = $this->actingAs($this->owner)->get(route('chat.analytics', ['widget' => $rivalWidget->id]));

    $response->assertOk();
    expect($response->viewData('page')['props']['filters']['widget'])->toBeNull();
});

it('keeps analytics behind the plan entitlement', function () {
    // A new organization trials Growth, which includes chat; a Starter plan does
    // not, and must be refused even for its own owner.
    [$starterOrg, $starterOwner] = makeOrganization('Starter Co');
    subscribeOrganization($starterOrg, 'starter');

    $this->actingAs($starterOwner)->get(route('chat.analytics'))->assertForbidden();
});

// ------------------------------------------------------- progressive profiling

it('does not re-ask a returning visitor for details it already has', function () {
    $key = $this->widget->public_key;

    completeChat($key, 'repeat@example.test', 'visitor-123');

    // Same visitor id, second visit: the email step is skipped and the
    // conversation completes on the first answer.
    $start = $this->postJson("/wc/{$key}/conversations", ['visitor' => 'visitor-123']);
    $second = $this->postJson("/wc/{$key}/conversations/{$start->json('token')}/messages", ['option' => 'it']);

    $second->assertOk()->assertJsonPath('done', true);

    // Still one contact — deduplicated, not duplicated.
    expect(Contact::withoutGlobalScope('tenant')->where('email', 'repeat@example.test')->count())->toBe(1);
});

it('still asks a brand-new visitor for everything', function () {
    $key = $this->widget->public_key;

    completeChat($key, 'known@example.test', 'visitor-known');

    $start = $this->postJson("/wc/{$key}/conversations", ['visitor' => 'someone-else']);
    $next = $this->postJson("/wc/{$key}/conversations/{$start->json('token')}/messages", ['option' => 'it']);

    expect($next->json('node.text'))->toBe('Your email?');
});

// ------------------------------------------------------------------ targeting

it('publishes targeting rules to the widget without leaking anything else', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update([
        'targeting' => [
            'include' => ['/cybersecurity'],
            'exclude' => ['/careers'],
            'visitor' => 'first',
            'delay_seconds' => 5,
            'scroll_percent' => 50,
            'exit_intent' => true,
        ],
        'routing' => ['assignee_id' => 99],
    ]);
    app(CurrentOrganization::class)->forget();

    $config = $this->getJson("/wc/{$this->widget->public_key}/config");

    $config->assertOk()
        ->assertJsonPath('targeting.include.0', '/cybersecurity')
        ->assertJsonPath('targeting.exclude.0', '/careers')
        ->assertJsonPath('targeting.visitor', 'first')
        ->assertJsonPath('targeting.delay_seconds', 5)
        ->assertJsonPath('targeting.exit_intent', true);

    // Internal configuration still never leaves the server.
    expect($config->json())->not->toHaveKeys(['routing', 'flow', 'allowed_domains']);
});

it('lets an administrator save targeting and business hours', function () {
    $this->actingAs($this->owner)
        ->patch(route('chat.widgets.update', $this->widget), [
            'targeting' => ['include' => ['/pricing'], 'visitor' => 'returning', 'delay_seconds' => 10],
            'business_hours' => [
                'timezone' => 'America/New_York',
                'days' => ['mon' => ['09:00', '17:00']],
                'closed_message' => 'Back tomorrow.',
            ],
            'settings' => ['mode' => 'bot_then_human'],
        ])
        ->assertSessionHasNoErrors();

    $widget = $this->widget->fresh();
    expect($widget->targeting['include'])->toBe(['/pricing'])
        ->and($widget->business_hours['timezone'])->toBe('America/New_York')
        ->and($widget->settings['mode'])->toBe('bot_then_human');
});

it('rejects an invalid chat mode and a malformed accent colour', function () {
    $this->actingAs($this->owner)
        ->patch(route('chat.widgets.update', $this->widget), ['settings' => ['mode' => 'telepathy']])
        ->assertSessionHasErrors('settings.mode');

    $this->actingAs($this->owner)
        ->patch(route('chat.widgets.update', $this->widget), ['theme' => ['accent' => 'not-a-colour']])
        ->assertSessionHasErrors('theme.accent');
});

it('restricts widget settings to administrators', function () {
    $viewer = addMember($this->org, Role::Viewer);

    $this->actingAs($viewer)->get(route('chat.widgets.edit', $this->widget))->assertForbidden();
    $this->actingAs($viewer)
        ->patch(route('chat.widgets.update', $this->widget), ['name' => 'Hijacked'])
        ->assertForbidden();
});

it('scopes every reported number to the current tenant', function () {
    [$otherOrg] = makeOrganization('Rival MSP');
    subscribeOrganization($otherOrg, 'enterprise');

    app(CurrentOrganization::class)->set($otherOrg);
    $rival = ChatWidget::create(['name' => 'Rival', 'status' => 'active']);
    ChatConversation::create(['chat_widget_id' => $rival->id, 'status' => 'open']);
    ChatEvent::create(['chat_widget_id' => $rival->id, 'type' => 'impression']);
    app(CurrentOrganization::class)->forget();

    completeChat($this->widget->public_key, 'mine@example.test');

    app(CurrentOrganization::class)->set($this->org);
    $summary = app(ChatAnalyticsService::class)->summary();
    app(CurrentOrganization::class)->forget();

    // The rival's conversation and impression are invisible here.
    expect($summary['conversations'])->toBe(1)
        ->and($summary['impressions'])->toBe(0);
});
