<?php

declare(strict_types=1);

/**
 * A workspace is told its plan is no longer running - on every page, before a
 * page has to be refused for anyone to find out.
 *
 * When a trial ran out, most of the product switched off at once and the first
 * sign was "403 Not authorized" on whichever module was opened next. The same
 * news now rides along with every signed-in page while it is true.
 */

use App\Authorization\Role;
use App\Billing\Entitlements;
use App\Billing\PlanCatalog;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
});

/** Stop the workspace's plan the way it happens for real: 'trial', 'cancel' or 'suspend'. */
function endThePlan($test, string $how): void
{
    $subscriptions = app(SubscriptionService::class);

    match ($how) {
        'trial' => (function () use ($test) {
            $test->travel(PlanCatalog::TRIAL_DAYS + 1)->days();
            $test->artisan('subscriptions:expire-trials')->assertSuccessful();
        })(),
        'cancel' => $subscriptions->cancel($test->org->activeSubscription(), immediately: true),
        default => $subscriptions->suspend($test->org->activeSubscription()),
    };

    app(Entitlements::class)->forget($test->org);
}

it('says nothing while a plan is running', function () {
    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('planNotice', null));
});

it('tells the owner on every page once the trial has ended, not only on a refused one', function () {
    $endedOn = $this->org->activeSubscription()->trial_ends_at->toDateString();
    endThePlan($this, 'trial');

    // The dashboard, and the CRM - which the free tier still opens.
    foreach (['dashboard', 'crm.contacts.index'] as $page) {
        $this->actingAs($this->owner)->get(route($page))
            ->assertOk()
            ->assertInertia(fn ($inertia) => $inertia
                ->where('planNotice.state', 'trial_ended')
                ->where('planNotice.workspace', 'PioManage')
                ->where('planNotice.plan', 'Growth')
                ->where('planNotice.ended_on', fn ($date) => $date >= $endedOn)
                ->where('planNotice.can_manage_billing', true));
    }
});

it('tells a teammate too, but does not send them to change a plan they cannot change', function () {
    $viewer = addMember($this->org, Role::Viewer);
    endThePlan($this, 'trial');

    $this->actingAs($viewer)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('planNotice.state', 'trial_ended')
            ->where('planNotice.can_manage_billing', false));
});

it('never tells an agency\'s client about the agency\'s billing', function () {
    $client = addMember($this->org, Role::Client);
    endThePlan($this, 'trial');

    $this->actingAs($client)->get(route('portal.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('planNotice', null));
});

it('tells a cancelled subscription from one on hold', function () {
    endThePlan($this, 'cancel');
    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('planNotice.state', 'ended')
            ->where('planNotice.ended_on', now()->toDateString()));

    subscribeOrganization($this->org, 'growth');
    endThePlan($this, 'suspend');
    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('planNotice.state', 'suspended')
            // On hold is not an ending: there is no date to give.
            ->where('planNotice.ended_on', null));
});

it('goes quiet again as soon as a plan is chosen', function () {
    endThePlan($this, 'trial');
    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('planNotice.state', 'trial_ended'));

    subscribeOrganization($this->org, 'growth');

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('planNotice', null));
});

/*
 * Before it ends (ENTL-010): the last few days of a trial say so, in time to do
 * something about it.
 */

it('stays quiet for most of a trial, then warns in its last days', function () {
    $endsAt = $this->org->activeSubscription()->trial_ends_at;

    // One day too early: nothing yet.
    $this->travelTo($endsAt->copy()->subDays(PlanCatalog::TRIAL_WARNING_DAYS + 1));
    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('planNotice', null));

    // Inside the window: on the dashboard, and anywhere else.
    $this->travelTo($endsAt->copy()->subDays(PlanCatalog::TRIAL_WARNING_DAYS)->addHour());
    foreach (['dashboard', 'crm.contacts.index'] as $page) {
        $this->actingAs($this->owner)->get(route($page))
            ->assertOk()
            ->assertInertia(fn ($inertia) => $inertia
                ->where('planNotice.state', 'trial_ending')
                ->where('planNotice.workspace', 'PioManage')
                ->where('planNotice.ends_at', $endsAt->toIso8601String())
                // Nothing has ended, so there is no "ended on".
                ->where('planNotice.ended_on', null)
                ->where('planNotice.can_manage_billing', true));
    }
});

it('warns over the same days the trial-ending email goes out', function () {
    // One number decides both, so the app and the inbox cannot disagree.
    expect(Artisan::all()['subscriptions:notify-trial-ending']->getDefinition()->getOption('days')->getDefault())
        ->toBe((string) PlanCatalog::TRIAL_WARNING_DAYS);
});

it('warns a teammate too, and never an agency\'s client', function () {
    $viewer = addMember($this->org, Role::Viewer);
    $client = addMember($this->org, Role::Client);
    $this->travelTo($this->org->activeSubscription()->trial_ends_at->copy()->subDay());

    $this->actingAs($viewer)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('planNotice.state', 'trial_ending')
            ->where('planNotice.can_manage_billing', false));

    $this->actingAs($client)->get(route('portal.dashboard'))
        ->assertInertia(fn ($page) => $page->where('planNotice', null));
});

it('says nothing to a workspace that has already chosen a plan', function () {
    $endsAt = $this->org->activeSubscription()->trial_ends_at->copy();

    // A hosted checkout sits behind the trial: it is charged and carries on by itself.
    $this->org->activeSubscription()->forceFill(['provider_id' => 'sub_hosted_checkout'])->save();
    $this->travelTo($endsAt->copy()->subDay());
    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('planNotice', null));

    // And a paid plan is never warned about a trial, even at the end of its month.
    subscribeOrganization($this->org, 'growth');
    $this->travelTo($this->org->activeSubscription()->current_period_end->copy()->subHour());
    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('planNotice', null));
});

it('still calls a page outside the trial\'s plan "not in your plan", not "trial ended"', function () {
    $this->travelTo($this->org->activeSubscription()->trial_ends_at->copy()->subDay());

    // Advertising is not part of the Growth trial.
    $this->actingAs($this->owner)->get(route('ads.dashboard'))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->where('denied.reason', 'plan')
            ->where('denied.state', 'not_included')
            ->where('denied.plan', 'Growth'));
});

it('turns from a warning into the ended notice when the trial runs out', function () {
    $this->travelTo($this->org->activeSubscription()->trial_ends_at->copy()->subDay());
    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('planNotice.state', 'trial_ending'));

    endThePlan($this, 'trial');

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('planNotice.state', 'trial_ended')->where('planNotice.ends_at', null));
});
