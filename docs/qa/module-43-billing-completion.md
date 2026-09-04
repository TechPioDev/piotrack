# Module Completion Report — Billing & Subscriptions, Phase 32

**Date:** 5 September 2026 · **Module:** Billing & Subscriptions · **Register:** 19/19 Tested (**100%**), was 12/19 (63%)

## What shipped

**Per-user pricing (BILL-003)** — the per-seat mechanism was tested but no per-seat
plan existed. The catalog gained per-seat support and a **Team** plan
($49/user/month); checkout × quantity produces the real per-seat invoice.

**Usage-based pricing (BILL-004)** — metering existed; the charge run didn't.
`plans.overage_prices` (cents per unit past a metered limit, seeded from the
catalog) + renewal settles the *ending* period's overage as itemised invoice lines.
Under the cap: no line. Unlimited limits: never billed.

**Add-ons (BILL-005)** — catalog add-ons (AI credit pack, extra locations, extra
keywords) attach per subscription; grants resolve through the **central
Entitlements resolver** — the no-scattered-plan-checks rule, with the subtle case
handled: a plan's explicitly-unlimited (null) limit is never capped back down by a
boost. Renewals bill one line per add-on; entitlements apply immediately, billing
starts next renewal — stated in the UI, not silently prorated.

**Checkout (BILL-008)** — closed on consolidated end-to-end evidence: billing
profile (email/company/tax), promo discount with its invoice line, order summary
math, per-seat quantity. Card entry stays provider-hosted by design.

**Trial expiry + renewal (BILL-011/012)** — the notes were stale: both scheduler
sweeps exist, run hourly, and were already covered by BillingSchedulerTest;
renewal is exercised again here carrying the new line items.

**Billing portal (BILL-018)** — the missing surface was payment-method management,
which is provider-hosted by design: `PaymentProvider::paymentMethodPortalUrl()`
redirects to Stripe's hosted portal once credentials and a provider customer
exist; the manual driver states plainly that card management activates with live
Stripe. Card data never touches the application.

## Honest scoping

Nothing in this module is deferred any more. What remains external is live Stripe
itself (keys, webhook verification against real events, the hosted portal URL) —
the provider abstraction that every path here runs through is the tested seam it
plugs into.

## Gate evidence

- Pest: **975 passed / 4,740 assertions** (+5:
  [BillingCloseoutTest](../../tests/Feature/Qa/BillingCloseoutTest.php));
  BillingSchedulerTest and the wider billing suite still green.
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
