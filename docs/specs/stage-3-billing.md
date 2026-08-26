# Module Specification — Commercial Foundation (BILL · ENTL)

Stage 3. Spec completed 2026-08-13 before implementation. Depends on Stage 2 (tenancy, RBAC, audit).
Architecture: [05-billing-and-entitlements](../architecture/05-billing-and-entitlements.md),
[ADR-0003](../architecture/adr/ADR-0003-payment-provider-abstraction.md).

## Purpose

Turn the tenant platform into a commercial product: configurable plans, checkout, the subscription
lifecycle, invoices, coupons, a customer billing portal, provider-agnostic webhooks, and a central
entitlement + usage-limit engine that gates features and enforces limits.

## Feature IDs

BILL-001…019, ENTL-001…007.

## Plan catalog (code source of truth → seeded to DB, configurable/admin-editable later)

| Plan                   | Monthly | Annual (per yr) | members | teams | features                        |
| ---------------------- | ------- | --------------- | ------- | ----- | ------------------------------- |
| Starter                | $49     | $470 (~20% off) | 3       | ✗     | crm                             |
| Growth (default trial) | $149    | $1,430          | 10      | ✓     | crm, seo, automation, audit_log |
| Professional           | $349    | $3,350          | 25      | ✓     | + ai_visibility, api, audit_log |
| Agency                 | $749    | $7,190          | 100     | ✓     | all + white_label               |
| Enterprise             | custom  | custom          | ∞       | ✓     | all                             |

Entitlement keys are two kinds: **features** (boolean, e.g. `feature.teams`, `feature.audit_log`,
`feature.api`) and **limits** (int, null = unlimited; e.g. `limit.members`, plus forward-looking
`limit.contacts`, `limit.emails`, `limit.api_calls`, … resolved now, metered as their modules land —
ENTL-004).

## Data model (tenant-scoped unless noted)

- `plans`, `plan_prices` (interval monthly/annual, currency, amount cents, per_seat) — not tenant-scoped
- `plan_entitlements` (plan_id, key, kind feature|limit, bool_value, int_value) — not tenant-scoped
- `subscriptions` (organization_id, plan_id, provider, provider_id, status, interval, quantity,
  trial_ends_at, current_period_start/end, cancel_at_period_end, canceled_at, ends_at)
- `invoices` (organization_id, subscription_id, provider_id, number, status, currency, subtotal,
  discount, tax, total, amount_paid, period_start/end, due_at, paid_at) + `invoice_line_items`
- `coupons` (code, type percent|amount, value, duration, max_redemptions, times_redeemed, expires_at,
  is_active) — not tenant-scoped
- `usage_counters` (organization_id, key, period_start/end, used)
- `billing_profiles` (organization_id unique, billing_email, company_name, tax_id, address…)
- `billing_events` (provider, provider_event_id unique, type, payload, status, processed_at, error) — platform-level

## Subscription lifecycle (BILL-011…017)

`trialing → active → past_due → (grace) → suspended → expired`; `active ⇄ upgrade/downgrade
(prorated) / quantity change`; `active → pending_cancellation → canceled → (reactivate) active`.
Every transition audited, invoiced where appropriate, entitlements recomputed live from the plan.
New organizations start on a **14-day Growth trial** (configurable default).

## Provider abstraction (BILL-010, ADR-0003)

`PaymentProvider` interface; `manual` driver (working default, in-DB, emits synthetic webhooks) and
`stripe` driver (real stripe-php, untested without keys). Business logic depends only on our tables.

## Entitlements & usage (ENTL-001…007)

- `Entitlements` service: `feature(org,key): bool`, `limit(org,key): ?int`, resolved from the active
  subscription's plan (free fallback when none: members 1, no premium). Cached per request.
- Feature gating API for backend (`entitlement:` middleware) and frontend (shared to Inertia).
- `UsageMeter`: counter-based meters + live resolvers (members = live membership count). Reports
  current / allowance / remaining / overage (ENTL-006).
- Enforcement (ENTL-007): member seat limit enforced on invite + acceptance; `feature.teams` gates
  the teams routes; `feature.audit_log` gates the audit route.

## Endpoints (tenant-scoped, verified; billing.\* permissions)

- New permissions `billing.view`, `billing.manage` (Owner + Billing Administrator get manage).
- `GET billing/plans` pricing; `POST billing/checkout` (plan, interval, coupon) → manual: activate;
  stripe: redirect to session. `GET billing` portal; `PATCH billing/subscription` change plan/interval;
  `POST billing/subscription/cancel` (+scheduled); `POST billing/subscription/resume`;
  `GET billing/invoices`, `GET billing/invoices/{invoice}`; `PATCH billing/profile`.
- `POST webhooks/{provider}` — public, signature-verified, idempotent, retry-safe (BILL-019).

## Business rules

- One active subscription per organization.
- Downgrade below current usage (e.g. members over new limit) is blocked with a clear message.
- Cancel defaults to end-of-period (scheduled); immediate cancel supported; resume before period end.
- Coupons validated (active, not expired, redemptions left) and applied to invoice totals.
- Manual provider marks invoices paid immediately; failed-payment path simulated for dunning tests.

## Error cases

Over-limit invite → 422; downgrade blocked → 422; feature not entitled → 403 (backend) / upgrade
prompt (frontend); invalid/expired coupon → 422; duplicate webhook → 200 no-op; unknown plan → 404.

## Audit events

`subscription.created/trial_started/activated/plan_changed/quantity_changed/canceled/
cancellation_scheduled/resumed/past_due/suspended/expired`, `invoice.created/paid/payment_failed`,
`coupon.applied`, `billing.profile_updated`. (Extends AUDIT; satisfies AUDIT-005.)

## Frontend (ENTL-006, BILL-018)

Inertia shares `entitlements` (features + limits + current usage summary). Pages: pricing table
(monthly/annual toggle), checkout (order summary + promo), portal (plan, usage bars, invoices,
change/cancel/resume, billing profile). Upgrade prompts where a feature isn't entitled.

## Tests

- Required §34 flows: **signup-with-plan**, **upgrade** (proration), **downgrade** (blocked when
  over-limit; allowed otherwise), **cancel/resume**, **failed-payment → past_due → grace → suspended**.
- Entitlement resolution per plan; feature gate 403 (teams on Starter); member seat limit (Starter=3).
- Usage display numbers; coupon apply; webhook idempotency (duplicate event no-ops); provider is
  swappable (manual driver exercised).
- Plans seeded in Feature `beforeEach`; Stage 1 & 2 suites stay green (default Growth trial permits
  their member/team usage).

## Acceptance criteria (gate)

Per [11-acceptance-criteria](../architecture/11-acceptance-criteria.md) BILL/ENTL: all five billing
workflows green; webhook replay idempotent; entitlement changes take effect without deploy; usage
meters visibly accurate; provider abstraction proven with the manual driver; Stripe driver present
and honestly marked untested. Full quality gate green; browser QA; Module Completion Report.
