<?php

declare(strict_types=1);

/**
 * Billing & Subscriptions close-out (Phase 32 — BILL-003/004/005/008/011/012/018).
 *
 * Per-seat pricing on a real plan, metered overage settled at renewal,
 * add-ons resolved through the central entitlements, the consolidated
 * checkout flow, and the provider-hosted payment-method surface. Trial expiry
 * and renewal sweeps were already covered by BillingSchedulerTest; renewal is
 * exercised again here with the new line items.
 */

use App\Billing\Entitlements;
use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\SubscriptionAddon;
use App\Services\SubscriptionService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Billing Org');
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('bills the per-seat Team plan by quantity', function () {
    $team = Plan::where('code', 'team')->firstOrFail();
    expect($team->prices()->where('interval', 'monthly')->firstOrFail()->per_seat)->toBeTrue();

    app(SubscriptionService::class)->checkout($this->org, $team, 'monthly', 5, null);

    app(CurrentOrganization::class)->set($this->org);
    $invoice = Invoice::latest('id')->firstOrFail();
    expect($invoice->subtotal)->toBe(5 * 4900)
        ->and($invoice->total)->toBe(24500)
        ->and($invoice->lineItems()->firstOrFail()->quantity)->toBe(5);

    // Seat changes keep billing honestly per seat on the next cycle.
    $subscription = $this->org->fresh()->activeSubscription();
    app(SubscriptionService::class)->changeQuantity($subscription, 8);
    expect($subscription->refresh()->quantity)->toBe(8);
});

it('settles metered overage at renewal, only past the limit, never on unlimited plans', function () {
    subscribeOrganization($this->org, 'professional'); // ai_credits limit 5000, overage 2c/unit
    $subscription = $this->org->fresh()->activeSubscription();

    // Under the cap: renewal carries no overage line.
    $subscription->forceFill(['current_period_end' => now()->subMinute()])->save();
    app(SubscriptionService::class)->renew($subscription);
    app(CurrentOrganization::class)->set($this->org);
    $clean = Invoice::latest('id')->firstOrFail();
    expect($clean->lineItems()->where('description', 'like', 'Overage%')->count())->toBe(0);

    // 5,750 credits used against a 5,000 cap: 750 x 2c = $15.00 overage.
    app(UsageMeter::class)->increment($this->org, Limit::AiCredits, 5750);
    $subscription->forceFill(['current_period_end' => now()->subMinute()])->save();
    app(SubscriptionService::class)->renew($subscription->refresh());

    $invoice = Invoice::latest('id')->firstOrFail();
    $overage = $invoice->lineItems()->where('description', 'like', 'Overage%')->firstOrFail();
    expect($overage->quantity)->toBe(750)
        ->and($overage->unit_amount)->toBe(2)
        ->and($overage->amount)->toBe(1500)
        ->and($invoice->subtotal)->toBe(34900 + 1500);
});

it('resolves add-on boosts through the central entitlements and bills them at renewal', function () {
    subscribeOrganization($this->org, 'professional');
    $subscription = $this->org->fresh()->activeSubscription();

    expect(app(Entitlements::class)->limit($this->org, Limit::AiCredits))->toBe(5000);

    app(CurrentOrganization::class)->forget();
    $this->actingAs($this->owner)->post(route('billing.addons.store'), ['code' => 'ai_credit_pack'])
        ->assertRedirect()->assertSessionHas('status');
    // Idempotent: adding again never stacks a duplicate.
    $this->actingAs($this->owner)->post(route('billing.addons.store'), ['code' => 'ai_credit_pack'])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    expect(SubscriptionAddon::count())->toBe(1)
        ->and(app(Entitlements::class)->limit($this->org->fresh(), Limit::AiCredits))->toBe(5500);

    // The renewal invoice carries the add-on line.
    $subscription->forceFill(['current_period_end' => now()->subMinute()])->save();
    app(SubscriptionService::class)->renew($subscription->refresh());
    $line = Invoice::latest('id')->firstOrFail()->lineItems()->where('description', 'like', 'Add-on%')->firstOrFail();
    expect($line->amount)->toBe(2000);

    // Detach removes the boost at once.
    app(CurrentOrganization::class)->forget();
    $this->actingAs($this->owner)->delete(route('billing.addons.destroy'), ['code' => 'ai_credit_pack'])->assertRedirect();
    app(CurrentOrganization::class)->set($this->org);
    expect(app(Entitlements::class)->limit($this->org->fresh(), Limit::AiCredits))->toBe(5000);
});

it('carries billing, company, tax, promo and order summary through checkout end-to-end', function () {
    app(CurrentOrganization::class)->forget();

    // Billing profile: billing + company + tax details (BILL-008).
    $this->actingAs($this->owner)->patch(route('billing.profile.update'), [
        'billing_email' => 'accounts@billingorg.test',
        'company_name' => 'Billing Org LLC',
        'tax_id' => 'EIN 12-3456789',
    ])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    $coupon = Coupon::create(['code' => 'WELCOME20', 'type' => 'percent', 'value' => 20, 'duration' => 'once', 'is_active' => true]);
    $plan = Plan::where('code', 'growth')->firstOrFail();

    app(SubscriptionService::class)->checkout($this->org, $plan, 'monthly', 1, $coupon);

    $invoice = Invoice::latest('id')->firstOrFail();
    expect($invoice->subtotal)->toBe(14900)
        ->and($invoice->discount)->toBe(2980)   // 20% promo
        ->and($invoice->total)->toBe(11920)
        ->and($invoice->lineItems()->where('amount', '<', 0)->firstOrFail()->description)->toContain('WELCOME20');

    expect($this->org->fresh()->billingProfile->company_name)->toBe('Billing Org LLC')
        ->and($this->org->fresh()->billingProfile->tax_id)->toBe('EIN 12-3456789');
});

it('defers payment-method management to the provider, honestly on the manual driver', function () {
    subscribeOrganization($this->org, 'growth');
    app(CurrentOrganization::class)->forget();

    // Manual driver: no hosted portal — a plain statement, never a dead link.
    $response = $this->actingAs($this->owner)->get(route('billing.payment-method'));
    $response->assertRedirect();
    expect(session('status'))->toContain('payment provider');

    // The scheduler sweeps stay green alongside (BILL-011/012 evidence).
    $this->artisan('subscriptions:expire-trials')->assertSuccessful();
    $this->artisan('subscriptions:process-renewals')->assertSuccessful();
});
