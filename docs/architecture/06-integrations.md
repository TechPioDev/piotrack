# 06 — Integration Architecture

Integrations are a reusable platform capability (Master Prompt §16), not per-feature one-offs.

## Connector framework

Every connector implements a common contract:

- **Auth**: OAuth2 (preferred) or API-key; token refresh; encrypted credential storage
  (`integration_credentials`, encrypted at rest, never exposed to frontend).
- **Scopes**: requested permission scopes recorded and displayed.
- **Sync engine**: scheduled + on-demand syncs as queue jobs; `sync_runs` records status, duration,
  volumes, errors; incremental cursors where the provider supports them.
- **Health model**: connected / syncing / degraded / disconnected / error — surfaced in UI, with
  reconnect flow and NOTIF alert on disconnection.
- **Error logs**: per-connector, per-run, tenant-visible summary + admin-visible detail.
- **Graceful degradation**: dependent features show explicit "integration disconnected" states,
  never silent empty charts (Master Prompt §25, §63).

## Webhook ingestion

Shared inbound webhook endpoint per provider: signature verification, raw payload persistence,
idempotent queue processing, replay tooling for platform admins.

## Connector roster (build order follows module phases)

| Wave                   | Connectors                                                                 | Consumed by                     |
| ---------------------- | -------------------------------------------------------------------------- | ------------------------------- |
| 1 (Core data)          | Google Analytics 4, Google Search Console, Google Business Profile         | ANLY, TSEO/KSEO/LSEO dashboards |
| 2 (Ads)                | Google Ads, Microsoft Ads, LinkedIn Ads, Meta Ads                          | PPC, LIAD, META, RETG           |
| 3 (Comms)              | SendGrid/Mailgun (email), Twilio (SMS + STOP handling), Gmail/Outlook sync | EMAIL, SMS, CRM activities      |
| 4 (Scheduling & calls) | Calendly-class booking, Zoom, Teams, CallRail                              | BOOK, CALL                      |
| 5 (Business)           | Slack, Teams (notifications), Stripe (billing), QuickBooks                 | NOTIF, BILL                     |
| 6 (CRM interop)        | HubSpot, Salesforce, Microsoft Dynamics (import/sync)                      | CRM migration paths             |
| 7 (Extensibility)      | Zapier, generic outbound webhooks, public API                              | API platform                    |
| Continuous             | AI providers via AIPF abstraction (Anthropic first)                        | AISA, AIVIS, GEO, scoring       |

Native capability with provider fallback: email/SMS sending, booking pages and call tracking are
platform features backed by pluggable providers, so tenants aren't forced into third-party accounts
for core flows.

## Testing

Each connector ships with: contract tests against recorded fixtures, failure-mode tests (expired
token, rate limit, provider 5xx), reconnection test, and tenant-isolation test on stored credentials
and synced data.
