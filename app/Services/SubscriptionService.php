<?php

namespace App\Services;

use App\Billing\Contracts\PaymentProvider;
use App\Billing\Dto\ProviderResult;
use App\Billing\Entitlements;
use App\Billing\Limit;
use App\Billing\PlanCatalog;
use App\Billing\UsageMeter;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionSuspendedNotification;
use App\Support\AuditLogger;
use App\Support\NotificationDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Orchestrates the subscription lifecycle (BILL-011…017) over our own tables,
 * delegating external side effects to the configured PaymentProvider
 * (ADR-0003). Every transition is audited; invoices are generated where money
 * changes hands.
 */
class SubscriptionService
{
    public function __construct(
        private PaymentProvider $provider,
        private AuditLogger $audit,
        private UsageMeter $usage,
        private Entitlements $entitlements,
        private CouponService $coupons,
        private NotificationDispatcher $notifications,
    ) {}

    /**
     * Start a free trial (used at organization creation). No invoice.
     */
    public function startTrial(Organization $organization, ?Plan $plan = null, string $interval = 'monthly'): Subscription
    {
        $plan ??= Plan::where('code', PlanCatalog::DEFAULT_TRIAL_PLAN)->firstOrFail();
        $trialEnds = now()->addDays(PlanCatalog::TRIAL_DAYS);

        $subscription = Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'provider' => $this->provider->key(),
            'status' => 'trialing',
            'interval' => $interval,
            'quantity' => 1,
            'trial_ends_at' => $trialEnds,
            'current_period_start' => now(),
            'current_period_end' => $trialEnds,
        ]);

        $this->entitlements->forget($organization);

        $this->audit->log('subscription.trial_started', context: [
            'plan' => $plan->code,
        ], resourceType: 'subscription', resourceId: (string) $subscription->id, organizationId: $organization->id);

        return $subscription;
    }

    /**
     * Check out a paid plan. On an immediate provider (manual) the subscription
     * activates and an invoice is generated and paid; on a redirect provider a
     * ProviderResult with a URL is returned and activation completes on webhook.
     */
    public function checkout(
        Organization $organization,
        Plan $plan,
        string $interval,
        int $quantity = 1,
        ?Coupon $coupon = null,
    ): ProviderResult {
        return DB::transaction(function () use ($organization, $plan, $interval, $quantity, $coupon) {
            $subscription = $organization->activeSubscription() ?? new Subscription([
                'organization_id' => $organization->id,
            ]);

            $subscription->fill([
                'plan_id' => $plan->id,
                'coupon_id' => $coupon?->id,
                'provider' => $this->provider->key(),
                'interval' => $interval,
                'quantity' => max(1, $quantity),
                'status' => $subscription->status ?? 'trialing',
            ]);
            $subscription->save();

            $result = $this->provider->startSubscription($subscription);
            $subscription->provider_id = $result->providerId;
            $subscription->save();

            if ($result->immediate) {
                $this->activate($subscription, $coupon);
            }

            return $result;
        });
    }

    /**
     * Activate a subscription for the current period and bill it.
     */
    public function activate(Subscription $subscription, ?Coupon $coupon = null): void
    {
        $start = now();
        $end = $this->periodEnd($start, $subscription->interval);

        $subscription->forceFill([
            'status' => 'active',
            'current_period_start' => $start,
            'current_period_end' => $end,
            'trial_ends_at' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'ends_at' => null,
        ])->save();

        $this->entitlements->forget($subscription->organization);

        $this->audit->log('subscription.activated', context: ['plan' => $subscription->plan->code], resourceType: 'subscription', resourceId: (string) $subscription->id, organizationId: $subscription->organization_id);

        $amount = $this->periodAmount($subscription);
        $this->generatePaidInvoice($subscription, $amount, "{$subscription->plan->name} ({$subscription->interval})", $coupon);
    }

    /**
     * Advance the billing period and issue a renewal invoice (BILL-012). Called
     * by the scheduler when an active subscription reaches its period end.
     */
    public function renew(Subscription $subscription): void
    {
        // Metered overage is settled for the period just ENDING, so it is
        // computed before the period advances (BILL-004).
        $extraLines = array_merge($this->overageLines($subscription), $this->addonLines($subscription));

        $start = $subscription->current_period_end ?? now();
        $end = $this->periodEnd($start, $subscription->interval);

        $subscription->forceFill([
            'status' => 'active',
            'current_period_start' => $start,
            'current_period_end' => $end,
        ])->save();

        $this->audit->log('subscription.renewed', context: ['plan' => $subscription->plan->code], resourceType: 'subscription', resourceId: (string) $subscription->id, organizationId: $subscription->organization_id);

        $amount = $this->periodAmount($subscription);
        $this->generatePaidInvoice($subscription, $amount, "{$subscription->plan->name} renewal ({$subscription->interval})", null, $extraLines);
    }

    /**
     * Attach a catalog add-on to the subscription (BILL-005). Entitlement
     * boosts apply immediately; billing starts with the next renewal, which is
     * stated in the UI rather than silently prorated. Idempotent per code.
     */
    public function addAddon(Subscription $subscription, string $code, int $quantity = 1): SubscriptionAddon
    {
        $definition = PlanCatalog::addons()[$code] ?? null;
        if ($definition === null) {
            throw new RuntimeException('Unknown add-on.');
        }

        $addon = SubscriptionAddon::firstOrCreate(
            ['subscription_id' => $subscription->id, 'code' => $code],
            [
                'organization_id' => $subscription->organization_id,
                'name' => $definition['name'],
                'price' => $definition['price'],
                'grants' => $definition['grants'],
                'quantity' => max(1, min(10, $quantity)),
            ],
        );

        $this->entitlements->forget($subscription->organization);
        $this->audit->log('subscription.addon_added', context: ['code' => $code], resourceType: 'subscription', resourceId: (string) $subscription->id, organizationId: $subscription->organization_id);

        return $addon;
    }

    public function removeAddon(Subscription $subscription, string $code): void
    {
        SubscriptionAddon::where('subscription_id', $subscription->id)->where('code', $code)->delete();

        $this->entitlements->forget($subscription->organization);
        $this->audit->log('subscription.addon_removed', context: ['code' => $code], resourceType: 'subscription', resourceId: (string) $subscription->id, organizationId: $subscription->organization_id);
    }

    /**
     * Overage lines for the period being settled (BILL-004): usage past a
     * metered plan limit at the plan's per-unit price. Unlimited limits never
     * bill overage; no overage, no line.
     *
     * @return list<array{description: string, quantity: int, unit_amount: int, amount: int}>
     */
    private function overageLines(Subscription $subscription): array
    {
        $prices = $subscription->plan->overage_prices ?? [];
        if ($prices === []) {
            return [];
        }

        $lines = [];
        foreach ($prices as $limitKey => $unitPrice) {
            $limit = $this->entitlements->limit($subscription->organization, $limitKey);
            if ($limit === null) {
                continue; // unlimited: nothing to exceed
            }

            $used = $this->usage->usage($subscription->organization, $limitKey);
            if ($used > $limit) {
                $over = $used - $limit;
                $lines[] = [
                    'description' => 'Overage: '.str_replace('_', ' ', $limitKey)." ({$over} over {$limit})",
                    'quantity' => $over,
                    'unit_amount' => (int) $unitPrice,
                    'amount' => $over * (int) $unitPrice,
                ];
            }
        }

        return $lines;
    }

    /**
     * One line per attached add-on, each renewal (BILL-005).
     *
     * @return list<array{description: string, quantity: int, unit_amount: int, amount: int}>
     */
    private function addonLines(Subscription $subscription): array
    {
        return $subscription->addons()->get()->map(fn (SubscriptionAddon $addon) => [
            'description' => 'Add-on: '.$addon->name,
            'quantity' => $addon->quantity,
            'unit_amount' => $addon->price,
            'amount' => $addon->price * $addon->quantity,
        ])->values()->all();
    }

    /**
     * Change plan/interval with proration (BILL-013). Downgrades that would put
     * the organization over the new plan's limits are blocked.
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, string $interval): void
    {
        $organization = $subscription->organization;
        $newLimit = $this->planLimit($newPlan, Limit::Members->value);

        if ($newLimit !== null && $this->usage->usage($organization, Limit::Members) > $newLimit) {
            throw ValidationException::withMessages([
                'plan' => __('This plan allows :n members, but your organization has more. Remove members before downgrading.', ['n' => $newLimit]),
            ]);
        }

        $oldAmount = $this->periodAmount($subscription);
        $subscription->fill(['plan_id' => $newPlan->id, 'interval' => $interval])->save();
        $newAmount = $this->periodAmount($subscription->refresh());

        $this->provider->changeSubscription($subscription);
        $this->entitlements->forget($organization);

        $this->audit->log('subscription.plan_changed', context: ['plan' => $newPlan->code, 'interval' => $interval], resourceType: 'subscription', resourceId: (string) $subscription->id, organizationId: $organization->id);

        // Charge the prorated increase immediately; downgrades carry as credit
        // (no refund) and simply reduce the next renewal.
        $proration = $this->proratedDifference($subscription, $oldAmount, $newAmount);
        if ($proration > 0) {
            $this->generatePaidInvoice($subscription, $proration, "Proration — {$newPlan->name}", null);
        }
    }

    public function changeQuantity(Subscription $subscription, int $quantity): void
    {
        $quantity = max(1, $quantity);
        $oldAmount = $this->periodAmount($subscription);
        $subscription->fill(['quantity' => $quantity])->save();
        $newAmount = $this->periodAmount($subscription->refresh());

        $this->provider->changeSubscription($subscription);

        $this->audit->log('subscription.quantity_changed', context: ['quantity' => $quantity], resourceType: 'subscription', resourceId: (string) $subscription->id, organizationId: $subscription->organization_id);

        $proration = $this->proratedDifference($subscription, $oldAmount, $newAmount);
        if ($proration > 0) {
            $this->generatePaidInvoice($subscription, $proration, "Proration — {$quantity} seats", null);
        }
    }

    /**
     * Cancel — scheduled at period end by default, or immediately (BILL-015).
     */
    public function cancel(Subscription $subscription, bool $immediately = false): void
    {
        $this->provider->cancelSubscription($subscription, $immediately);

        if ($immediately) {
            $subscription->forceFill(['status' => 'canceled', 'canceled_at' => now(), 'ends_at' => now(), 'cancel_at_period_end' => false])->save();
            $this->entitlements->forget($subscription->organization);
            $action = 'subscription.canceled';
        } else {
            $subscription->forceFill(['cancel_at_period_end' => true, 'canceled_at' => now(), 'ends_at' => $subscription->current_period_end])->save();
            $action = 'subscription.cancellation_scheduled';
        }

        $this->audit->log($action, resourceType: 'subscription', resourceId: (string) $subscription->id, organizationId: $subscription->organization_id);
    }

    public function resume(Subscription $subscription): void
    {
        abort_unless($subscription->cancel_at_period_end && $subscription->status !== 'canceled', 422, 'Subscription cannot be resumed.');

        $subscription->forceFill(['cancel_at_period_end' => false, 'canceled_at' => null, 'ends_at' => null, 'status' => 'active'])->save();

        $this->audit->log('subscription.resumed', resourceType: 'subscription', resourceId: (string) $subscription->id, organizationId: $subscription->organization_id);
    }

    /**
     * A failed payment opens a grace period (BILL-016).
     */
    public function markPastDue(Subscription $subscription): void
    {
        $subscription->forceFill([
            'status' => 'past_due',
            'ends_at' => now()->addDays((int) config('billing.grace_days', 7)),
        ])->save();

        $this->audit->log('subscription.past_due', resourceType: 'subscription', resourceId: (string) $subscription->id, organizationId: $subscription->organization_id);

        $this->notifications->toOrganizationOwners(
            $subscription->organization,
            new PaymentFailedNotification($subscription->organization->name),
        );
    }

    /**
     * Grace elapsed → suspended (BILL-017).
     */
    public function suspend(Subscription $subscription): void
    {
        $subscription->forceFill(['status' => 'suspended'])->save();
        $this->entitlements->forget($subscription->organization);

        $this->audit->log('subscription.suspended', resourceType: 'subscription', resourceId: (string) $subscription->id, organizationId: $subscription->organization_id);

        $this->notifications->toOrganizationOwners(
            $subscription->organization,
            new SubscriptionSuspendedNotification($subscription->organization->name),
        );
    }

    /**
     * Trial ended without conversion → expired.
     */
    public function expire(Subscription $subscription): void
    {
        $subscription->forceFill(['status' => 'expired', 'ends_at' => now()])->save();
        $this->entitlements->forget($subscription->organization);

        $this->audit->log('subscription.expired', resourceType: 'subscription', resourceId: (string) $subscription->id, organizationId: $subscription->organization_id);
    }

    // ---------------------------------------------------------------------
    // Invoicing
    // ---------------------------------------------------------------------

    /**
     * @param  list<array{description: string, quantity: int, unit_amount: int, amount: int}>  $extraLines
     */
    private function generatePaidInvoice(Subscription $subscription, int $amount, string $description, ?Coupon $coupon, array $extraLines = []): Invoice
    {
        $extraTotal = (int) array_sum(array_column($extraLines, 'amount'));
        $subtotal = $amount + $extraTotal;
        $discount = $coupon !== null ? $coupon->discountFor($amount) : 0;
        $total = max(0, $subtotal - $discount);

        $invoice = Invoice::create([
            'organization_id' => $subscription->organization_id,
            'subscription_id' => $subscription->id,
            'provider' => $subscription->provider,
            'number' => $this->nextInvoiceNumber(),
            'status' => 'open',
            'currency' => config('billing.currency', 'USD'),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => 0,
            'total' => $total,
            'period_start' => $subscription->current_period_start,
            'period_end' => $subscription->current_period_end,
            'due_at' => now(),
        ]);

        $invoice->lineItems()->create([
            'description' => $description,
            'quantity' => $subscription->quantity,
            'unit_amount' => $subscription->quantity > 0 ? intdiv($amount, $subscription->quantity) : $amount,
            'amount' => $amount,
        ]);

        // BILL-004/005: overage and add-on lines ride the same invoice.
        foreach ($extraLines as $line) {
            $invoice->lineItems()->create($line);
        }

        if ($discount > 0) {
            // A positive discount implies a coupon was applied (see above).
            $invoice->lineItems()->create([
                'description' => "Discount ({$coupon->code})",
                'quantity' => 1,
                'unit_amount' => -$discount,
                'amount' => -$discount,
            ]);
        }

        $this->audit->log('invoice.created', context: ['number' => $invoice->number, 'total' => $total], resourceType: 'invoice', resourceId: (string) $invoice->id, organizationId: $subscription->organization_id);

        if ($coupon !== null) {
            $this->coupons->redeem($coupon);
            $this->audit->log('coupon.applied', context: ['code' => $coupon->code], organizationId: $subscription->organization_id);
        }

        if ($this->provider->payInvoice($invoice)) {
            $invoice->forceFill(['status' => 'paid', 'amount_paid' => $total, 'paid_at' => now()])->save();
            $this->audit->log('invoice.paid', context: ['number' => $invoice->number], resourceType: 'invoice', resourceId: (string) $invoice->id, organizationId: $subscription->organization_id);
        } else {
            $this->audit->log('invoice.payment_failed', context: ['number' => $invoice->number], resourceType: 'invoice', resourceId: (string) $invoice->id, organizationId: $subscription->organization_id);
            $this->markPastDue($subscription);
        }

        return $invoice;
    }

    private function nextInvoiceNumber(): string
    {
        return 'INV-'.str_pad((string) (Invoice::max('id') + 1), 6, '0', STR_PAD_LEFT);
    }

    // ---------------------------------------------------------------------
    // Pricing helpers
    // ---------------------------------------------------------------------

    private function periodAmount(Subscription $subscription): int
    {
        $price = $subscription->plan->priceFor($subscription->interval);

        if ($price === null) {
            return 0;
        }

        return $price->per_seat ? $price->amount * $subscription->quantity : $price->amount;
    }

    private function planLimit(Plan $plan, string $key): ?int
    {
        $entitlement = $plan->entitlements->firstWhere('key', $key);

        return $entitlement !== null ? $entitlement->int_value : null;
    }

    private function proratedDifference(Subscription $subscription, int $oldAmount, int $newAmount): int
    {
        $delta = $newAmount - $oldAmount;
        if ($delta <= 0) {
            return 0;
        }

        $start = $subscription->current_period_start;
        $end = $subscription->current_period_end;
        if ($start === null || $end === null || $end->lessThanOrEqualTo(now())) {
            return $delta;
        }

        $total = $start->diffInSeconds($end);
        $remaining = now()->diffInSeconds($end, absolute: false);
        $ratio = $total > 0 ? max(0, min(1, $remaining / $total)) : 1;

        return (int) round($delta * $ratio);
    }

    private function periodEnd(Carbon $start, string $interval): Carbon
    {
        return $interval === 'annual' ? $start->copy()->addYear() : $start->copy()->addMonthNoOverflow();
    }
}
