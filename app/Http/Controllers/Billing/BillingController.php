<?php

namespace App\Http\Controllers\Billing;

use App\Billing\PlanCatalog;
use App\Billing\UsageMeter;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Support\CurrentOrganization;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private UsageMeter $usage,
    ) {}

    /**
     * Pricing / plan selection (BILL-001).
     */
    public function plans(): Response
    {
        $organization = $this->currentOrganization->get();

        $plans = Plan::with(['prices', 'entitlements'])
            ->where('is_public', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => PlanPresenter::present($plan));

        return Inertia::render('billing/plans', [
            'plans' => $plans,
            'currentPlan' => $organization->activeSubscription()?->plan->code,
        ]);
    }

    /**
     * Billing portal (BILL-018): plan, usage, invoices, actions.
     */
    public function index(): Response
    {
        $organization = $this->currentOrganization->get();
        $subscription = $organization->activeSubscription();

        return Inertia::render('billing/index', [
            'subscription' => $subscription !== null ? $this->presentSubscription($subscription) : null,
            'usage' => $this->usage->summary($organization),
            'invoices' => $organization->invoices()
                ->latest('id')
                ->limit(10)
                ->get(['id', 'number', 'status', 'total', 'currency', 'created_at', 'paid_at'])
                ->map(fn (Invoice $i) => [
                    'id' => $i->id,
                    'number' => $i->number,
                    'status' => $i->status,
                    'total' => $i->total,
                    'currency' => $i->currency,
                    'created_at' => $i->created_at,
                ]),
            'billingProfile' => $organization->billingProfile,
            // BILL-005: the add-on catalog plus what this subscription carries.
            'addonCatalog' => collect(PlanCatalog::addons())->map(fn (array $a, string $code) => [
                'code' => $code, 'name' => $a['name'], 'price' => $a['price'],
            ])->values(),
            'attachedAddons' => $subscription?->addons()->get()
                ->map(fn (SubscriptionAddon $a) => ['code' => $a->code, 'name' => $a->name, 'price' => $a->price])
                ->values() ?? [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSubscription(Subscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'plan' => PlanPresenter::present($subscription->plan),
            'status' => $subscription->status,
            'interval' => $subscription->interval,
            'quantity' => $subscription->quantity,
            'on_trial' => $subscription->onTrial(),
            'trial_ends_at' => $subscription->trial_ends_at,
            'current_period_end' => $subscription->current_period_end,
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'ends_at' => $subscription->ends_at,
        ];
    }
}
