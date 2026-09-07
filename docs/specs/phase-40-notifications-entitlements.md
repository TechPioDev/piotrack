# Phase 40 — Notification System + Feature Entitlements closeout

**Register targets:** NOTIF-003, NOTIF-004, NOTIF-005 (Notification System 70% → 100%);
ENTL-002, ENTL-004 (Entitlements 71.4% → 100%).

## Position

- **NOTIF-003 SMS notifications** — the deferral note predates the SMS module (100% since
  P30: provider seam, consent, fixture-tested). Close: SMS becomes a notification channel.
  `users.phone`, an **opt-in** SMS preference per category (unlike email's opt-out —
  nobody gets surprise texts), a custom `SmsChannel` riding the existing provider seam.
  Live delivery stays Twilio-credential-gated exactly like all SMS.
- **NOTIF-004 Slack/Teams** — needs no OAuth connector: Slack and Teams both accept
  incoming-webhook URLs the tenant pastes. Org-level `notification_channels` rows
  (kind slack|teams), URL SSRF-guarded, posted the platform's `{text}` payload shape.
- **NOTIF-005 Webhook notifications** — same table, kind `webhook`: JSON event payload
  with an HMAC-SHA256 `X-Piotrack-Signature` when a secret is set. All three fire once
  per organization-level notification through the central `NotificationDispatcher`
  (never per recipient), and a channel failure never breaks the business flow.
- **ENTL-002 matrix administration** — the Stage 13 platform console exists; add the
  plan × entitlement matrix editor (features on/off, limits incl. unlimited) at
  `platform/plans`, `can:admin.platform`.
- **ENTL-004 limits registry** — "members enforced now, others as modules land"; the
  modules have landed. Live meters (counted from current state) for contacts, keywords,
  competitors, locations, automations, storage; counter meters stay for flows (emails —
  already enforced, sms, api_calls, workflow_executions, ai_credits — already enforced).
  New enforcement at the natural choke points: contact create (UI/API only — public
  capture endpoints NEVER drop a lead), keyword create, location create, workflow
  create, SMS dispatch, file upload (size-aware), API calls (429 over limit), workflow
  enrollment. `websites` and `reports` stay resolution-only with honest notes (single
  managed website per tenant; reports are unlimited by design).

## Build

1. Migration: `users.phone`; `notification_channels` (organization_id, kind, url,
   secret?, is_active).
2. `NotificationChannel` model; `User::smsOptedIn()` (explicit-enable only);
   `NotificationPreference::CHANNELS` += sms; `SmsChannel`; `PlatformNotification::via`.
3. `OrgChannelNotifier` (slack/teams/webhook payloads, UrlGuard, HMAC, swallow+audit
   failures); `NotificationDispatcher::toOrganizationOwners` fires it once per event.
4. Org channels CRUD on the organization settings page (`can:organization.update`);
   phone on the profile page; sms toggles appear in the existing preference matrix.
5. `UsageMeter` live meters + choke-point enforcement + `SetApiOrganization` API-call
   metering/429.
6. `platform/plans` matrix editor + save endpoint.
7. Tests `tests/Feature/Qa/NotificationEntitlementsCloseoutTest.php` (~6).

## Out of scope

Live Twilio/Slack-app/Teams-app credentials (seams tested with fakes); data-retention
enforcement (PRIV-004 territory, unchanged).
