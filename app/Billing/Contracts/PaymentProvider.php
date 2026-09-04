<?php

namespace App\Billing\Contracts;

use App\Billing\Dto\ProviderResult;
use App\Billing\Dto\WebhookEvent;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Http\Request;

/**
 * The seam that keeps business logic independent of any one payment provider
 * (Master Prompt §7, ADR-0003). Our subscriptions/invoices tables are the
 * source of truth; a provider handles external side effects and payment
 * confirmation, and normalizes inbound webhooks.
 */
interface PaymentProvider
{
    public function key(): string;

    /**
     * Provision the subscription at the provider. The returned result says
     * whether activation is immediate (manual) or deferred to a redirect /
     * webhook (hosted checkout).
     */
    public function startSubscription(Subscription $subscription): ProviderResult;

    /**
     * Push a plan/interval/quantity change to the provider.
     */
    public function changeSubscription(Subscription $subscription): void;

    public function cancelSubscription(Subscription $subscription, bool $immediately): void;

    /**
     * Attempt payment for an invoice; true on success.
     */
    public function payInvoice(Invoice $invoice): bool;

    /**
     * BILL-018: where the customer manages their payment method. Card data
     * never touches this application — providers host that surface; null when
     * the provider has none (manual/offline billing).
     */
    public function paymentMethodPortalUrl(Subscription $subscription): ?string;

    /**
     * Verify the request's authenticity and normalize it into an event, or
     * null if verification fails.
     */
    public function verifyWebhook(Request $request): ?WebhookEvent;
}
