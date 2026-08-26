# ADR-0003 — Payment provider abstraction with a working manual driver

**Status:** Accepted (2026-08-13) · Implements [05-billing-and-entitlements.md](../05-billing-and-entitlements.md) and Master Prompt §7, §38.

## Context

Billing is a first-class system that must be architected before the product modules (Master Prompt
§7). The platform must support commercial monetization but "core business logic should not become
unnecessarily locked to one payment provider." Stripe "may be used when appropriate."

This environment has **no Stripe account or API keys**, and Master Prompt §38 forbids presenting
fake implementations as complete while requiring an honest distinction between Mocked / Partially
Implemented / Implemented / Tested / Production ready.

## Decision

**Depend on our own billing tables and a `PaymentProvider` interface — never on a provider's SDK
directly.** Provider objects are synced into our `subscriptions`/`invoices` tables, which are the
source of truth.

Two drivers, selected by `BILLING_PROVIDER`:

1. **`manual` (default, fully working & tested)** — an in-database provider that runs the entire
   subscription lifecycle (checkout → trial → activation → renewal → upgrade/downgrade → cancel →
   past-due/grace → suspended/expired), generates invoices, and emits synthetic webhook events so
   the webhook pipeline is exercised end to end. This is not a stub: manual/offline billing (PO,
   invoice-me, wire) is a legitimate B2B/MSP billing mode and is the driver used in development,
   tests, and any tenant billed offline.

2. **`stripe` (real driver, requires credentials — not exercised here)** — implements the same
   interface with `stripe/stripe-php`. It is real code, but because no keys exist in this
   environment it is **not run in tests**; its register status is _Implemented (untested — requires
   credentials)_, never "Tested." Activated by setting `BILLING_PROVIDER=stripe` + keys.

The webhook endpoint, idempotency (`billing_events`), signature verification, and event handlers are
provider-agnostic; the manual driver's synthetic events prove the pipeline, and the Stripe driver
plugs into the same handlers.

## Consequences

- The full commercial engine (plans, subscriptions, entitlements, usage, invoices, coupons, portal)
  is real and tested without a payment account.
- Switching to Stripe is configuration + credentials, not a rewrite — satisfying §7.
- We are honest per §38: what is tested is tested; the Stripe path is labelled untested until keys
  and a sandbox exercise it.
- Plans/prices/entitlements live in DB (configurable, admin-editable later in Stage 13) with a code
  `PlanCatalog` as the seeded source of truth — mirroring the RBAC registry approach (ADR-0002).
