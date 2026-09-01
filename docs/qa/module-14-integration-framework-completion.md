# Phase 3 Completion Report — Integration Framework (Module 14)

**Date:** 2026-09-02 · **Spec:** docs/module-spec-integration-framework.md · Register: **772 → 774 Tested**, module 20% → 40% (every remaining row is a vendor account/app away, not code away)

## What shipped

- **Outbound webhooks (INTG-009, the generic integration + working Zapier path):**
  `webhook_endpoints` (tenant-scoped, encrypted secret, per-event subscription),
  `WebhookDispatcher` + queued `DeliverWebhook` (tries 3, backoff 10s/60s), payload
  `{event, occurred_at, data}` signed with `X-Piotrack-Signature` (HMAC-SHA256).
  Success resets failure bookkeeping; failures record count + error and never break the
  emitting flow. Emitters wired: `lead.captured` (form capture), `booking.created`
  (public + chat booking), `deal.won` (stage transition, once per crossing),
  `alert.fired` (sales alerts), `ping` (test button). Managed on Settings →
  Integrations: add (https-only, secret shown once), delete, test, delivery health.
  Inbound capture already exists via the public form endpoints — noted, not duplicated.
- **Generic OAuth2 flow (completes INTG-001):** provider apps are pure env config
  (`services.connectors.{key}`); redirect with state → verified callback → token
  exchange → encrypted vault → connected Integration. Forged state rejected;
  unconfigured providers stay "coming soon" and refuse to redirect.
- **Connector catalog** expanded to the full INTG-004..009 vendor set (24 entries);
  OAuth `connectable` is now config-driven — registering Google/Microsoft/LinkedIn/
  Meta/Slack/etc. becomes configuration plus a vendor app, zero code.
- **INTG-010 was stale:** the abstracted AI-provider framework shipped in Module 07
  (AiGateway/SeoProviderManager, encrypted keys, live/simulated provenance) — corrected
  to Tested with its existing test evidence.

## Gate

pint ✓ · phpstan 0 ✓ · prettier ✓ · eslint ✓ · tsc ✓ · Vitest 43 ✓ ·
**Pest 838 / 3,577 assertions** (+5: `tests/Feature/Qa/IntegrationFrameworkTest.php`).

## Remaining in this module (external only)

INTG-004..008 vendor connectors: each needs its OAuth app registered (Google Cloud,
Azure AD, LinkedIn, Meta, Slack…) or an API key from a live account; INTG-009's named
vendors likewise. The framework side is done.
