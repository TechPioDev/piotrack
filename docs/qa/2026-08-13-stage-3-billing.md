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

## Follow-up (2026-10-06): a refused page says why (ENTL-008)

Reported by the owner from a live workspace: `/chat/widgets`, `/seo/local` and `/settings/teams`
all answered "403 Not authorized - contact your administrator".

Their own screenshot ruled out the role: the settings menu lists Teams only for someone holding
`teams.view`, and it was listed. What refused the page was `entitlement:teams` - the plan. A
workspace with no active subscription (a trial that ran out, a subscription that ended or is on
hold) falls back to the free tier, which is the CRM alone, so every other module is refused at
once. The plan check and the permission check both produced the same sentence, so nothing on
the page said the plan was the cause or that Billing was the way out.

- `EnsureEntitled` now throws `FeatureNotInPlan` - still a 403, with the same message an API
  client always got.
- The error page is told which of the two refused it (`App\Support\AccessDenied`): for a plan,
  whether the trial ended, the subscription ended, it is on hold, there is no plan, or the named
  plan simply does not include the feature - with the date and the workspace name. Someone who can
  manage billing gets a "See plans" button; anyone else is sent to an owner. A role refusal names
  the role. Any other 403 keeps the old wording.

Reproduced as a test: a new workspace's trial runs its 14 days, the hourly `expire-trials` job
runs, and both pages from the report return 403 with `state: trial_ended`; choosing a plan
brings them back.

**Not changed, and worth a decision:** the menus still list modules the plan does not include.
(The other half of this note - nothing saying a trial has ended until a page refuses - is closed
by ENTL-009 below.)

**Automated testing:** `AccessDeniedPageTest` (6), `access-denied.test.ts` (5).

## Follow-up (2026-10-06, later): told before being refused (ENTL-009)

Asked for by the owner after ENTL-008: say that a plan has stopped running without waiting for a
page to refuse.

- Every signed-in page carries one line while the workspace has no plan running - what happened
  (trial ended, subscription ended, on hold, no plan yet), when, and that nothing was deleted. It
  sits in the app frame rather than on the dashboard alone, because the free tier still opens the
  CRM and an owner who lands there would otherwise never see it.
- Someone who can manage billing gets a button: "See plans", or "Open billing" for a subscription
  on hold, which is put right by paying rather than by picking a plan. Anyone else is told to ask
  an owner. On the page the button points at, the button is left out.
- A client using the portal is never told: the agency's billing is not their affair.
- It can be put away for the visit (browser session) and returns in a new one, or at once if the
  situation changes.
- `App\Billing\PlanStanding` is the one place that turns a subscription's status into those
  words; the 403 page now uses it too. While a plan is active the shared props run no extra
  query - the subscription already looked up for the plan's name is reused.

Deliberately not included: a warning for a payment that has failed but not yet suspended
anything (the warning *before* a trial ends followed the same day - ENTL-010 below).

**Automated testing:** `PlanNoticeTest` (6), `plan-notice.test.tsx` (6).

## Follow-up (2026-10-06, later still): warned before the trial ends (ENTL-010)

Asked for by the owner once ENTL-009 was live: say it before it happens.

- In the last three days of a trial, every signed-in page says the trial "ends in 3 days, on
  9 October 2026", "ends tomorrow" or "ends today", and that choosing a plan keeps everything
  switched on. It does not say anything is switched off, because nothing is yet.
- **Three days is one number** - `PlanCatalog::TRIAL_WARNING_DAYS` - now also the default of the
  `subscriptions:notify-trial-ending` email, so the app and the inbox cannot disagree. A test
  reads the command's default to hold them together.
- **Days are the reader's own.** The server sends the moment the trial runs out; the browser
  counts calendar days from where the person is, so "tomorrow" is their tomorrow.
- **Put away, it returns the next day**, not only in a new session: "three days left" dismissed
  must not become "ends today" never seen.
- **Not shown to a workspace that has already chosen**: a paid plan, or a trial with a hosted
  checkout behind it (it carries the provider's own id), which is charged and carries on by
  itself. Nor to a client using the portal.
- A page outside the trial's plan is still reported as "not included in your plan" during those
  days, not as a trial ending.

(The same line for a payment that has failed but not yet suspended anything followed - ENTL-011.)

**Automated testing:** `PlanNoticeTest` +6 (12), `plan-notice.test.tsx` +4 (10),
`access-denied.test.ts` +2 (7).

## Follow-up (2026-10-06, evening): a failed payment says how long is left (ENTL-011)

The last of the three: asked for by the owner once the trial warning was live.

A failed payment marks the subscription `past_due` and gives it `billing.grace_days` (7) before
`subscriptions:enforce-grace` suspends it. Everything keeps working in between, which is exactly
why nobody noticed: the first sign was the day most of the product switched off.

- Every signed-in page now says "A payment for PioManage did not go through. Most of Piotrack
  will be switched off in 5 days, on 13 October 2026, unless it is settled." - or "tomorrow", or
  "today". It does not say anything is off, because nothing is.
- An owner gets **Open billing** (a payment is put right there, not by choosing a plan); a
  teammate is told to ask an owner; a portal client is told nothing.
- Put away, it returns the next day. It goes quiet the moment the invoice is paid, and becomes the
  "on hold" notice if the grace runs out unpaid - at which point the plan's pages really are
  refused, for the reason given.
- A page the plan never included is still "not included in your plan" while a payment is owed.
- The shared props now consult the plan's standing for any subscription that is not simply
  `active`; an active one still costs nothing extra.

**Known limit, stated rather than hidden:** the billing page shows the plan as "past due" and its
invoices, and hands off to the payment provider's own portal for the card. With offline billing
there is no card to fix in the app, so "Open billing" leads to the invoice, not to a pay button.

**Automated testing:** `PlanNoticeTest` +5 (17), `plan-notice.test.tsx` +4 (14).
