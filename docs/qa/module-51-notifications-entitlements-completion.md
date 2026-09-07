# Module Completion Report — Notification System + Feature Entitlements & Usage Limits

**Phase 40 · 2026-09-06 · Register: NOTIF-003, NOTIF-004, NOTIF-005, ENTL-002, ENTL-004 → Tested (both modules 100%: Notification System 10/10, Entitlements 7/7)**

## Scope

Two small modules whose open rows were deferrals that no longer hold: the SMS module the
NOTIF-003 note waited for shipped in P30; Slack/Teams need no OAuth connector (incoming
webhooks are pasted URLs); the platform console ENTL-002 waited for shipped in Stage 13;
and the modules the ENTL-004 limits waited for have all landed.

## What shipped

### SMS notification channel (NOTIF-003)

`users.phone` (profile page field) + `sms` in the preference matrix — **opt-in** per
category, deliberately unlike email's opt-out: only an explicit enabled preference plus a
phone on file adds the `SmsChannel` to a notification's `via()`. The channel rides the
same provider seam as every SMS (fixture in tests, Twilio behind credentials) and a
provider failure never breaks the flow — the in-app and email copies already landed.
The old "only offer channels we can deliver" guard test was updated to the new truth.

### Slack / Teams / webhook org channels (NOTIF-004/005)

`notification_channels` per organization (managed on organization settings,
`can:organization.update`, https enforced): `slack` and `teams` kinds post the platforms'
`{text}` payload to the pasted incoming-webhook URL; `webhook` posts a JSON event
(category/title/body/url/sent_at) with an HMAC-SHA256 `X-Piotrack-Signature` when a
secret is set. All fire **once per event** through `NotificationDispatcher::
toOrganizationOwners` — never per recipient — URLs pass the SSRF guard, inactive
channels are skipped, and a failing channel is audited, never thrown.

### Plan × entitlement matrix editor (ENTL-002)

`platform/plans` (`can:admin.platform`): feature checkboxes and limit inputs (blank =
unlimited) per plan, upserting `plan_entitlements`. Tenants pick changes up on their
next request since entitlements resolve per request.

### Limits registry, live-metered and enforced (ENTL-004)

`UsageMeter` now meters stock resources **live from current state** — contacts,
keywords, competitors, locations, automations, and storage MB summed from real file
sizes — because the row count is the truth, not an accumulator that can drift. Flow
resources stay on period counters (emails and ai_credits were already enforced; sms,
api_calls, workflow_executions join them). New `assertWithin` choke-point guard wired
at: contact creation (UI + API only — **public capture endpoints are deliberately never
blocked: a lead is never dropped over a plan limit**), keyword/location/workflow
creation, SMS dispatch (`failed`/`limit_reached`, before the provider), file upload
(size-aware MB), API requests (429 over limit, and over-limit requests are not
counted), and workflow enrollment. `websites`/`reports` resolve through the registry but
stay unenforced by design (one managed website per tenant; unlimited reports), stated
in the register note.

## Gate results

| Check | Result |
| --- | --- |
| `vendor/bin/pint --test` | pass (after auto-fix) |
| `phpstan analyse` (1G) | 0 errors |
| `npm run format:check` / `npx eslint .` / `npm run types` | pass |
| `npm run build` | pass |
| `npm run test` (Vitest) | 55 passed |
| `php vendor/bin/pest` | **1,011 passed, 5,047 assertions** |

New: `tests/Feature/Qa/NotificationEntitlementsCloseoutTest.php` (4 tests) — SMS opt-in
semantics (preference alone insufficient, phone alone insufficient, disable wins);
one-post-per-event to Slack + signed webhook with the HMAC verified, inactive skipped,
failure audited, https + permission on CRUD; the matrix editor round-trip visible to a
tenant with the staff/tenant permission split; and live meters + every choke point
(contact/keyword/automation blocks, SMS `limit_reached`, API 429 with uncounted
overage, storage MB from file rows).

## Register effect

5 rows → Tested. Notification System **10/10** and Feature Entitlements & Usage Limits
**7/7** — the 35th and 36th complete modules. Global: **1,060/1,190 buildable Tested
(89.1%)**.
