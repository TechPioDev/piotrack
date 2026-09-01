# Module Specification — Integration Framework close-out (Phase 3 / Module 14)

> Road-to-100 phase for the Integration Framework register module (2026-09-02).
> Vendor connectors (INTG-004..009 named services) need registered OAuth apps and
> live accounts — externally blocked. This phase closes what a framework can
> honestly own without them.

## Scope

**Outbound webhooks (the generic integration + the real Zapier path, INTG-009):**
`webhook_endpoints` (tenant-scoped: https url, encrypted secret, subscribed events,
active flag, failure/last-delivery bookkeeping). `WebhookDispatcher` + queued
`DeliverWebhook` job (tries 3, backoff): JSON body `{event, occurred_at, data}`,
`X-Piotrack-Event` + `X-Piotrack-Signature` (HMAC-SHA256 of body with the secret).
Success resets failure count; failures record count + error and never break the
emitting flow. Events wired: `lead.captured`, `booking.created`, `deal.won`,
`alert.fired`, plus `ping` from the test button. Managed on Settings → Integrations
(add/delete/test, secret revealed to manage-permission only). Inbound capture is
already served by the public form endpoints (`POST /f/{slug}`) — noted, not duplicated.

**Generic OAuth2 flow (completes INTG-001):** per-connector app config from
`config/services.php` (`services.connectors.{key}`: client_id/secret/authorize_url/
token_url/scopes — env only). `GET settings/integrations/oauth/{key}` redirects with
a state token; `GET …/callback` verifies state, exchanges the code (Http), stores
access/refresh tokens in the existing encrypted vault, marks the Integration
connected. Registry `connectable` for OAuth connectors becomes config-driven.
Tested against faked token endpoints; no live provider required for the framework.

**Register truth:** INTG-010 (abstracted AI providers) is already shipped —
AiGateway/SeoProviderManager with encrypted platform keys, fixture/live provenance
(Module 07, ProviderProvenanceTest) — status corrected with evidence.

## Testing

Pest `IntegrationFrameworkTest`: endpoint CRUD + permission + tenant scope; signed
delivery with event filtering; failure bookkeeping; event emission from booking/
deal-won/alert flows; OAuth redirect/state/callback/token storage; unconfigured
OAuth connector stays non-connectable.
