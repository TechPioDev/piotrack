# Module Completion Report — Stage 3: Commercial Foundation (BILL · ENTL)

Date: 2026-08-13
Spec: [docs/specs/stage-3-billing.md](../specs/stage-3-billing.md) · ADR: [ADR-0003](../architecture/adr/ADR-0003-payment-provider-abstraction.md)
Scope: BILL-001…019, ENTL-001…007 (+ AUDIT-005)

## Status summary

| Area                                                                                                           | Result                                                                                                                      |
| -------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| Plans, pricing, catalog (BILL-001/002/006)                                                                     | Tested                                                                                                                      |
| Coupons (BILL-007)                                                                                             | Tested                                                                                                                      |
| Invoicing (BILL-009)                                                                                           | Tested                                                                                                                      |
| Provider abstraction + manual driver (BILL-010)                                                                | Tested; **Stripe driver implemented but untested — requires credentials**                                                   |
| Lifecycle: upgrade/downgrade/proration, quantity, cancel/resume, past-due/grace, suspend/expire (BILL-013…017) | Tested                                                                                                                      |
| Webhooks (BILL-019)                                                                                            | Tested (verified, idempotent, retry-safe)                                                                                   |
| Billing portal (BILL-018)                                                                                      | Portal core + billing profile Tested; payment-method management is provider-hosted (pending Stripe) → Partially Implemented |
| Checkout details capture (BILL-008)                                                                            | Order summary + promo Tested; company/tax via billing profile; card entry provider-hosted → Partially Implemented           |
| Per-seat pricing (BILL-003)                                                                                    | Schema + quantity + proration Tested; no per-seat-priced plan seeded → Partially                                            |
| Usage-based pricing (BILL-004)                                                                                 | Metering done; overage billing pending → Partially                                                                          |
| Trial expiry / auto-renewal (BILL-011/012)                                                                     | State machine + methods Tested; automatic sweep/renewal needs the scheduler → Partially                                     |
| Add-ons (BILL-005)                                                                                             | **Not implemented — Planned** (no add-on schema/UI)                                                                         |
| Entitlements engine (ENTL-001/003/005/006/007)                                                                 | Tested                                                                                                                      |
| Entitlement matrix admin (ENTL-002)                                                                            | Seeded from catalog; admin editing UI in Stage 13 → Partially                                                               |
| Usage-limit registry (ENTL-004)                                                                                | `members` enforced; others resolve, metered as modules land → Partially                                                     |
| Billing audit events (AUDIT-005)                                                                               | Tested                                                                                                                      |

Honest §38 distinction: the **manual provider path and the entire commercial engine are tested**;
the **Stripe driver is real code but not exercised** (no keys here) and is never marked "Tested".

## Architecture delivered

- **Provider abstraction (ADR-0003)**: `PaymentProvider` interface, `PaymentProviderManager`
  (config-selected), `ManualPaymentProvider` (working default, in-DB, synthetic webhooks),
  `StripePaymentProvider` (stripe-php, lazy client, untested). Business logic depends only on our tables.
- **Catalog**: `PlanCatalog` (code) → `plans`/`plan_prices`/`plan_entitlements` via `PlanSeeder` /
  `billing:sync-plans`. Five tiers (Starter→Enterprise) with feature + limit entitlements.
- **Lifecycle**: `SubscriptionService` (trial, checkout/activate, change plan w/ proration, quantity,
  cancel/scheduled/resume, past-due/grace, suspend, expire) + invoice generation with line items.
- **Entitlements/usage**: `Entitlements` (feature/limit resolution, free fallback, cached),
  `UsageMeter` (live + counter meters), `EnsureEntitled` middleware. New orgs start on a 14-day
  Growth trial; member seat limit enforced on invite; `feature.teams`/`feature.audit_log` gate their routes.
- **Webhooks**: public `POST /webhooks/{provider}` → driver verification → idempotent
  `BillingWebhookProcessor` keyed on `(provider, provider_event_id)`.

## Automated test results

- **Pest: 140/140 PASS** (461 assertions). New Stage 3 suites (38 tests): subscription lifecycle,
  entitlements, usage limits, webhooks (idempotency + dunning), coupons, provider resolution,
  billing profile. Plans seeded per Feature test via `beforeEach`.
- Pint PASS · PHPStan L6: 0 errors · Prettier PASS · ESLint PASS · tsc PASS.
- Stage 1 & 2 suites stay green (default Growth trial permits their member/team usage; one Stage 2
  audit-viewer assertion made null-actor-safe for the new subscription events).

## Required §34 billing workflows

- **Signup with plan**: checkout → active subscription + paid invoice. ✔
- **Upgrade**: plan change with prorated charge. ✔
- **Downgrade**: blocked when over the new plan's member limit; allowed otherwise. ✔
- **Cancel/resume**: scheduled cancel + resume, and immediate cancel. ✔
- **Failed payment**: webhook → `past_due` (grace) → `suspended`. ✔

## Manual QA (browser, http://localhost:8734)

- Billing portal for a Growth-trial org: plan + trial-end date, usage bars (Members 1/10,
  Contacts 0/10000), empty invoices.
- Pricing page: all five plans, monthly/annual toggle, "Current" badge, "Contact sales" for Enterprise.
- Checkout Professional → portal shows **Professional / active**, renewal date, usage limits updated
  to 25/50000, and **invoice INV-000001 paid $349**.
- Invoice detail: line items, subtotal, total, paid badge.

## Defects discovered & fixed

1. Stripe driver constructed its client eagerly → selecting the driver without keys threw. Made the
   client lazy so the driver is selectable; only operations require credentials.
2. A Stage 2 audit-viewer test accessed `actor.email` on a null actor once org creation began
   emitting `subscription.trial_started` (no actor in non-HTTP context). Made the assertion null-safe.
3. Several PHPStan strictness fixes (Carbon `@property` on `current_period_start`, coupon non-null
   flow after a positive discount).

## Deferred (tracked in register, not dropped)

- Add-ons (BILL-005) — Planned. Per-seat-priced plans (BILL-003) and usage/overage billing (BILL-004).
- Automatic trial-expiry sweep and recurring renewal charges (BILL-011/012) — need the scheduler +
  queue (Stage 4). Manual driver has no auto-renew; Stripe drives these via its own webhooks.
- Provider-hosted payment-method management + full Stripe verification (BILL-018) — needs a Stripe
  account/keys and a sandbox exercise.
- Entitlement matrix admin UI (ENTL-002) — Stage 13 platform admin.

## Completion

**APPROVED — Stage 3 gate passed.** Foundation stages (0–3) complete. Next: Stage 4 — Core Platform
(navigation, dashboard framework, notifications, global search, settings, files, integrations
framework, background jobs/queues, observability).
