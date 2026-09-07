<?php

declare(strict_types=1);

/**
 * QA §38 - notification triggers and channels.
 *
 * §38 names six triggers (hot lead, new meeting, payment failed, integration
 * disconnected, usage threshold, workflow failed) and five channels (in-app,
 * email, SMS, Slack, Teams). This checks which actually fire and which channels
 * are genuinely offered, so the register reflects reality in both directions.
 *
 * Two of §38's triggers work and one register row understated that; three do
 * not fire and their row is honest that they will "as INTG/automation land".
 * Only two channels are real, and - unlike the booking reminder earlier this
 * run - the product does not offer the other three, so nothing is silently
 * dropped.
 */

use App\Models\AlertRule;
use App\Models\Contact;
use App\Models\NotificationPreference;
use App\Notifications\MemberJoinedNotification;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SalesAlertNotification;
use App\Services\Sales\AlertService;
use App\Services\SubscriptionService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

/*
|--------------------------------------------------------------------------
| Triggers that fire
|--------------------------------------------------------------------------
*/

it('notifies owners of a hot-lead alert - the NOTIF-006 piece marked Planned', function () {
    Notification::fake();

    $contact = Contact::create([
        'first_name' => 'Michael', 'last_name' => 'Rodriguez',
        'email' => 'michael.rodriguez@precisionmfg-test.com', 'lead_score' => 85,
    ]);

    app(AlertRule::class)::create([
        'name' => 'Hot lead', 'trigger' => 'score_threshold',
        'threshold' => 80, 'channel' => 'in_app', 'is_active' => true,
    ]);

    app(AlertService::class)->evaluate($contact);

    // A hot-lead business alert reaches the owner: this trigger works, so
    // NOTIF-006 being fully Planned understates it.
    Notification::assertSentTo($this->owner, SalesAlertNotification::class);
});

it('notifies owners of a payment failure', function () {
    Notification::fake();

    $subscription = $this->org->activeSubscription();
    app(SubscriptionService::class)->markPastDue($subscription);

    Notification::assertSentTo($this->owner, PaymentFailedNotification::class);
});

/*
|--------------------------------------------------------------------------
| Triggers §38 names that genuinely do not fire yet
|--------------------------------------------------------------------------
*/

it('has no notification for workflow failure, integration disconnect or usage threshold', function () {
    // Pins NOTIF-007's "as INTG/automation land": the classes do not exist, so
    // the note stays honest and cannot quietly drift to Tested. When any is
    // built this fails and the row must be revisited.
    foreach ([
        'App\\Notifications\\WorkflowFailedNotification',
        'App\\Notifications\\IntegrationDisconnectedNotification',
        'App\\Notifications\\UsageThresholdNotification',
    ] as $class) {
        expect(class_exists($class))->toBeFalse("{$class} now exists");
    }
});

/*
|--------------------------------------------------------------------------
| Channels - only the two that are real are offered
|--------------------------------------------------------------------------
*/

it('offers only the channels it can deliver', function () {
    // SMS became deliverable in Phase 40 (opt-in, over the provider seam), so
    // it joined the user-facing channels. Slack/Teams are ORGANIZATION-level
    // channels (notification_channels), not per-user toggles, so a user still
    // cannot pick them here — nothing offered is ever silently dropped.
    expect(NotificationPreference::CHANNELS)->toBe(['in_app', 'email', 'sms']);

    foreach (['slack', 'teams'] as $orgLevel) {
        $this->actingAs($this->owner)->patch(route('notifications.preferences'), [
            'category' => 'members', 'channel' => $orgLevel, 'enabled' => true,
        ])->assertSessionHasErrors('channel');
    }
});

it('resolves an opted-out email channel out of via() while keeping in-app', function () {
    $notification = new MemberJoinedNotification('new@precisionmfg-test.com', 'Acme Managed IT Services');

    // By default both channels are live.
    expect($notification->via($this->owner->fresh()))->toBe(['database', 'mail']);

    // Opt out of member emails; in-app (database) must survive, mail must drop.
    NotificationPreference::updateOrCreate(
        ['user_id' => $this->owner->id, 'category' => 'members', 'channel' => 'email'],
        ['enabled' => false],
    );

    expect($notification->via($this->owner->fresh()))->toBe(['database']);
});
