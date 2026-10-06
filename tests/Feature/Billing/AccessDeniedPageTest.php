<?php

declare(strict_types=1);

/**
 * A refused page says why.
 *
 * Reported from a live workspace: Website Chat and Local SEO both answered
 * "403 Not authorized - contact your administrator" for the workspace's own
 * owner. Nothing was wrong with their role. The 14-day trial had ended, the
 * workspace had dropped to the free tier, and every module outside it was
 * refused with the same sentence a missing permission gets - nothing to say the
 * plan was the cause, or that Billing was the way out.
 */

use App\Authorization\Role;
use App\Billing\Entitlements;
use App\Billing\PlanCatalog;

/** Let the trial run out, the way the hourly job does it. */
function letTheTrialEnd($test): void
{
    $test->travel(PlanCatalog::TRIAL_DAYS + 1)->days();
    $test->artisan('subscriptions:expire-trials')->assertSuccessful();
    app(Entitlements::class)->forget($test->org);
}

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
});

it('tells the owner their trial ended, on the pages that stopped opening', function () {
    $this->actingAs($this->owner)->get(route('chat.widgets.index'))->assertOk();
    $endedOn = $this->org->activeSubscription()->trial_ends_at->toDateString();

    letTheTrialEnd($this);

    foreach (['chat.widgets.index', 'seo.local.index'] as $page) {
        $this->actingAs($this->owner)->get(route($page))
            ->assertForbidden()
            ->assertInertia(fn ($inertia) => $inertia
                ->component('errors/error')
                ->where('status', 403)
                ->where('denied.reason', 'plan')
                ->where('denied.state', 'trial_ended')
                ->where('denied.workspace', 'PioManage')
                ->where('denied.ended_on', fn ($date) => $date >= $endedOn)
                ->where('denied.can_manage_billing', true));
    }
});

it('leaves the way out open, and choosing a plan brings the pages back', function () {
    letTheTrialEnd($this);

    // Billing is not behind the plan it is there to change.
    $this->actingAs($this->owner)->get(route('billing.index'))->assertOk();
    $this->actingAs($this->owner)->get(route('billing.plans'))->assertOk();

    subscribeOrganization($this->org, 'growth');

    $this->actingAs($this->owner)->get(route('chat.widgets.index'))->assertOk();
    $this->actingAs($this->owner)->get(route('seo.local.index'))->assertOk();
});

it('sends someone who cannot change the plan to an owner, not to billing', function () {
    $viewer = addMember($this->org, Role::Viewer);
    letTheTrialEnd($this);

    $this->actingAs($viewer)->get(route('chat.widgets.index'))
        ->assertForbidden()
        ->assertInertia(fn ($inertia) => $inertia
            ->where('denied.reason', 'plan')
            ->where('denied.can_manage_billing', false));
});

it('names the plan when the plan simply does not include the feature', function () {
    subscribeOrganization($this->org, 'starter'); // no teams

    $this->actingAs($this->owner)->get(route('teams.index'))
        ->assertForbidden()
        ->assertInertia(fn ($inertia) => $inertia
            ->where('denied.reason', 'plan')
            ->where('denied.state', 'not_included')
            ->where('denied.plan', 'Starter')
            ->where('denied.ended_on', null));
});

it('keeps a role refusal about the role', function () {
    subscribeOrganization($this->org, 'enterprise');
    $billing = addMember($this->org, Role::BillingAdministrator);

    $this->actingAs($billing)->get(route('chat.widgets.index'))
        ->assertForbidden()
        ->assertInertia(fn ($inertia) => $inertia
            ->component('errors/error')
            ->where('denied.reason', 'role')
            ->where('denied.workspace', 'PioManage')
            ->where('denied.role', Role::BillingAdministrator->label()));
});

it('still answers an API client with a plain 403 and the reason', function () {
    letTheTrialEnd($this);

    $this->actingAs($this->owner)->getJson(route('chat.widgets.index'))
        ->assertForbidden()
        ->assertJson(['message' => 'Your plan does not include this feature.']);
});
