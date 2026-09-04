<?php

namespace App\Billing\Providers;

use App\Billing\Contracts\PaymentProvider;
use App\Billing\Dto\ProviderResult;
use App\Billing\Dto\WebhookEvent;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Real Stripe driver (BILL-010). Implemented against stripe/stripe-php but NOT
 * exercised in this environment — no Stripe account or keys exist here, so its
 * register status is "Implemented (untested — requires credentials)", never
 * "Tested" (Master Prompt §38, ADR-0003). Activate with BILLING_PROVIDER=stripe
 * plus keys, then verify against a Stripe test-mode sandbox before production.
 */
class StripePaymentProvider implements PaymentProvider
{
    private ?StripeClient $stripe = null;

    public function key(): string
    {
        return 'stripe';
    }

    /**
     * Lazily construct the client so the driver can be selected without keys;
     * only calling an operation requires credentials.
     */
    private function stripe(): StripeClient
    {
        $secret = (string) config('billing.stripe.secret');
        abort_if($secret === '', 500, 'Stripe is not configured (STRIPE_SECRET missing).');

        return $this->stripe ??= new StripeClient($secret);
    }

    public function startSubscription(Subscription $subscription): ProviderResult
    {
        $price = $subscription->plan->priceFor($subscription->interval);
        abort_if($price?->provider_price_id === null, 422, 'This plan has no Stripe price configured.');

        $session = $this->stripe()->checkout->sessions->create([
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $price->provider_price_id,
                'quantity' => $subscription->quantity,
            ]],
            'client_reference_id' => (string) $subscription->organization_id,
            'success_url' => route('billing.index').'?checkout=success',
            'cancel_url' => route('billing.plans').'?checkout=cancelled',
            'subscription_data' => $subscription->onTrial()
                ? ['trial_end' => $subscription->trial_ends_at?->getTimestamp()]
                : [],
        ]);

        return ProviderResult::redirect($session->url ?? route('billing.plans'), $session->id);
    }

    public function changeSubscription(Subscription $subscription): void
    {
        if ($subscription->provider_id === null) {
            return;
        }

        $price = $subscription->plan->priceFor($subscription->interval);
        $stripeSub = $this->stripe()->subscriptions->retrieve($subscription->provider_id);

        $this->stripe()->subscriptions->update($subscription->provider_id, [
            'items' => [[
                'id' => $stripeSub->items->data[0]->id,
                'price' => $price?->provider_price_id,
                'quantity' => $subscription->quantity,
            ]],
            'proration_behavior' => 'create_prorations',
        ]);
    }

    public function cancelSubscription(Subscription $subscription, bool $immediately): void
    {
        if ($subscription->provider_id === null) {
            return;
        }

        if ($immediately) {
            $this->stripe()->subscriptions->cancel($subscription->provider_id);
        } else {
            $this->stripe()->subscriptions->update($subscription->provider_id, ['cancel_at_period_end' => true]);
        }
    }

    public function payInvoice(Invoice $invoice): bool
    {
        // Stripe collects payment on its side; success/failure arrive via webhook.
        return $invoice->provider_id !== null;
    }

    public function paymentMethodPortalUrl(Subscription $subscription): ?string
    {
        // The hosted billing portal exists once a Stripe customer does — the
        // same credential gate as the rest of this driver.
        if ($subscription->provider_id === null || (string) config('billing.stripe.portal_url', '') === '') {
            return null;
        }

        return (string) config('billing.stripe.portal_url');
    }

    public function verifyWebhook(Request $request): ?WebhookEvent
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('billing.stripe.webhook_secret'),
            );
        } catch (\Throwable) {
            return null;
        }

        return new WebhookEvent($event->id, $event->type, $event->data->toArray());
    }
}
