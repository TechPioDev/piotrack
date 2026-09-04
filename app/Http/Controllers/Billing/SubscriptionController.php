<?php

namespace App\Http\Controllers\Billing;

use App\Billing\Contracts\PaymentProvider;
use App\Billing\PlanCatalog;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private SubscriptionService $subscriptions,
    ) {}

    /**
     * BILL-005: attach a catalog add-on. Entitlement boosts apply now;
     * billing starts with the next renewal (stated in the UI).
     */
    public function addAddon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', Rule::in(array_keys(PlanCatalog::addons()))],
        ]);

        $subscription = $this->activeSubscriptionOrAbort();
        $addon = $this->subscriptions->addAddon($subscription, $data['code']);

        return back()->with('status', __('":name" added — entitlements apply now, billing starts with the next renewal.', ['name' => $addon->name]));
    }

    /** BILL-005: detach an add-on (its boost leaves the entitlements at once). */
    public function removeAddon(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:60']]);

        $this->subscriptions->removeAddon($this->activeSubscriptionOrAbort(), $data['code']);

        return back()->with('status', __('Add-on removed.'));
    }

    /**
     * BILL-018: payment-method management is provider-hosted by design — card
     * data never touches this application. Redirects when the provider offers
     * a portal; says so plainly when it does not (manual/offline billing).
     */
    public function paymentMethod(PaymentProvider $provider): RedirectResponse
    {
        $url = $provider->paymentMethodPortalUrl($this->activeSubscriptionOrAbort());

        if ($url !== null) {
            return redirect()->away($url);
        }

        return back()->with('status', __('Payment methods are managed by your payment provider. Card management activates once live Stripe billing is connected; until then invoices are settled offline.'));
    }

    private function activeSubscriptionOrAbort(): Subscription
    {
        $subscription = $this->currentOrganization->get()?->activeSubscription();
        abort_if($subscription === null, 404, __('No active subscription.'));

        return $subscription;
    }

    /**
     * Change plan / interval (BILL-013) and/or quantity (BILL-014).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', Rule::exists('plans', 'code')],
            'interval' => ['required', Rule::in(['monthly', 'annual'])],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $subscription = $this->requireSubscription();
        $plan = Plan::with(['prices', 'entitlements'])->where('code', $validated['plan'])->firstOrFail();

        abort_if($plan->is_custom_priced, 422, 'Enterprise plans are set up by our sales team.');

        $this->subscriptions->changePlan($subscription, $plan, $validated['interval']);

        if (isset($validated['quantity']) && $validated['quantity'] !== $subscription->quantity) {
            $this->subscriptions->changeQuantity($subscription->refresh(), $validated['quantity']);
        }

        return redirect()->route('billing.index')->with('status', __('Your plan has been updated.'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'immediately' => ['nullable', 'boolean'],
        ]);

        $this->subscriptions->cancel($this->requireSubscription(), (bool) ($validated['immediately'] ?? false));

        return back()->with('status', __('Your subscription has been cancelled.'));
    }

    public function resume(): RedirectResponse
    {
        $this->subscriptions->resume($this->requireSubscription());

        return back()->with('status', __('Your subscription has been resumed.'));
    }

    private function requireSubscription(): Subscription
    {
        $subscription = $this->currentOrganization->get()->activeSubscription();

        abort_if($subscription === null, 404, 'No active subscription.');

        return $subscription;
    }
}
