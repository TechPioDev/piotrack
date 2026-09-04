# Phase 32 — Billing & Subscriptions close-out

Register target (7 rows): BILL-003 (per-user pricing), BILL-004 (usage-based
pricing), BILL-005 (add-ons), BILL-008 (checkout flow), BILL-011 (trial expiry),
BILL-012 (renewal), BILL-018 (billing portal).

## Stale notes first

BILL-011/012 were held for "scheduler pending Stage 4 jobs" — `subscriptions:
expire-trials` and `subscriptions:process-renewals` exist, run hourly, and are
covered by BillingSchedulerTest. They close on evidence; the closeout test
additionally exercises renewal end-to-end with the new line items below.

## Builds

- **Per-user pricing (BILL-003)** — the mechanism (per_seat prices ×
  quantity, prorated quantity changes) was tested but no per-seat plan existed.
  PlanCatalog gains per-seat support and a **Team** plan ($49/user/mo); the seeder
  honors `per_seat`. Checkout with a quantity now produces a real per-seat invoice.
- **Usage-based pricing (BILL-004)** — metering existed (ENTL); the charge run
  didn't. `plans.overage_prices` (json: limit key → cents/unit, seeded from the
  catalog) + renewal computes overage lines for the period just ended: usage over
  the plan limit × unit price, itemised on the renewal invoice. No overage, no
  line; unlimited limits never bill overage.
- **Add-ons (BILL-005)** — catalog add-ons (AI credit pack, extra locations, extra
  keywords), each a monthly price plus entitlement grants. `subscription_addons`
  attach/detach through the billing portal (manage-gated, idempotent);
  **Entitlements resolves plan limits + add-on boosts centrally** (the no-scattered-
  plan-checks rule); renewal invoices carry one line per add-on. Changes apply to
  the running period's entitlements immediately and bill from the next renewal —
  stated in the UI rather than silently prorated.
- **Checkout (BILL-008)** — closes on consolidated end-to-end evidence: billing
  profile (billing + company + tax details), promo code discount, order summary
  math, per-seat quantity. Card entry remains provider-hosted by design (Stripe
  Elements when live; the manual driver records without card data) — stated.
- **Billing portal (BILL-018)** — the one missing surface was payment-method
  management, which is provider-hosted by design. `PaymentProvider` gains
  `paymentMethodPortalUrl()`: Stripe returns its hosted portal once credentials
  and a provider customer exist; the manual driver returns null and the portal
  says plainly that card management activates with live Stripe. Everything else
  (plan, usage, invoices, history, contact/tax, cancel/resume) was already tested.

## Tests (tests/Feature/Qa/BillingCloseoutTest.php)

1. Per-seat: Team checkout ×5 → invoice = 5 × seat price; quantity change
   prorations still hold.
2. Overage: usage pushed over the AI-credit cap → renewal invoice carries the
   overage line with exact quantity × unit price; under the cap → no line;
   unlimited plan → never.
3. Add-ons: attach boosts the central entitlement limit immediately, detach
   removes it, renewal invoice carries the add-on line, attach is idempotent,
   permission-gated.
4. Checkout end-to-end: billing profile + promo + order summary + discount line.
5. Portal: payment-method endpoint defers to the provider (manual → honest
   message, no redirect); trial-expiry and renewal sweeps re-cited.
