<?php

namespace App\Billing\Providers;

use App\Billing\Contracts\PaymentProvider;
use App\Billing\Dto\ProviderResult;
use App\Billing\Dto\WebhookEvent;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The default, fully-working provider (ADR-0003). Runs the entire subscription
 * lifecycle in our own database — the mode used for development, tests, and any
 * tenant billed manually/offline (PO, invoice, wire). No external calls.
 *
 * Its webhook channel is authenticated by a shared secret so internal billing
 * tooling (or tests) can drive events through the same idempotent pipeline the
 * hosted providers use.
 */
class ManualPaymentProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'manual';
    }

    public function startSubscription(Subscription $subscription): ProviderResult
    {
        return ProviderResult::immediate('manual_'.Str::uuid());
    }

    public function changeSubscription(Subscription $subscription): void
    {
        // No external system to update — our tables are the source of truth.
    }

    public function cancelSubscription(Subscription $subscription, bool $immediately): void
    {
        // No-op for offline billing.
    }

    public function payInvoice(Invoice $invoice): bool
    {
        // Offline payments are recorded as settled by the operator; treat as paid.
        return true;
    }

    public function paymentMethodPortalUrl(Subscription $subscription): ?string
    {
        // Offline billing has no card on file to manage.
        return null;
    }

    public function verifyWebhook(Request $request): ?WebhookEvent
    {
        $secret = (string) config('billing.manual.webhook_secret');

        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Billing-Signature'))) {
            Log::warning('Rejected manual billing webhook: bad signature');

            return null;
        }

        $id = (string) $request->input('id');
        $type = (string) $request->input('type');

        if ($id === '' || $type === '') {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $request->input('data', []);

        return new WebhookEvent($id, $type, $data);
    }
}
