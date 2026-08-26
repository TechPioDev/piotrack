# Module Completion Report — Core Platform (Stage 4)

Date: 2026-08-13
Spec: [docs/specs/stage-4-core-platform.md](../specs/stage-4-core-platform.md)
Scope of this delivery: JOBS, NOTIF, FILE, OBS, SRCH, ONBD (checklist). Sequenced: INTG, API, DSGN.

## Status summary

| Area                                                         | Result                                                                                                                                                              |
| ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| JOBS — queue infra + scheduler (closes BILL-011/012/016/017) | JOBS-001 Tested; 002/003/004 Partial (retry tuning/idempotency conventions/Horizon dashboard)                                                                       |
| NOTIF — in-app center, email, preferences, wired events      | 001/002/008/010 Tested; 007 Partial; 003/004/005/006/009 Planned (SMS/Slack/Teams/webhook channels + marketing/sales alert types need integrations & their modules) |
| FILE — tenant file storage                                   | FILE-001 Tested; FILE-002 Partial (attachable ready)                                                                                                                |
| OBS — health + logging                                       | OBS-001/003 Tested; 002/004 Partial (metrics backend, external alerting)                                                                                            |
| SRCH — global search                                         | SRCH-003 Tested; 001/002 Partial (over current entities, expands)                                                                                                   |
| ONBD — setup checklist                                       | ONBD-001/002/003/004/005/013/014 Tested; 006–012 Planned (business profile/ICP/competitor/integration-wizard/audit — later modules)                                 |
| Sequenced                                                    | INTG (connector framework), API (public v1), DSGN (formal docs) — remain Planned/Partial, tracked                                                                   |

## Highlight: Stage 3 billing debt closed

The scheduler now runs `subscriptions:process-renewals` (renew active subs past period end + invoice,
or end scheduled cancellations), `subscriptions:expire-trials` (lapsed trials → expired),
`subscriptions:enforce-grace` (past-due past grace → suspended), and `subscriptions:notify-trial-ending`.
This closes BILL-011/012 (previously Partial) and completes the past-due → grace → suspended flow.

## Architecture delivered

- **JOBS**: database queue + `jobs`/`failed_jobs`; queued notifications proven end-to-end via a worker;
  four scheduled commands (`routes/console.php`), each idempotent and `->withoutOverlapping()`.
  New `SubscriptionService::renew()`.
- **NOTIF**: `PlatformNotification` base resolves channels from per-user `notification_preferences`
  (in-app always on, email opt-out, security ignores opt-out); email queued. Notification classes:
  payment failed, subscription suspended, trial ending, member joined — wired into the billing and
  membership lifecycle. `NotificationDispatcher` centralizes fan-out to organization owners. In-app
  center (bell + badge + page + mark-read/all) and a preference matrix.
- **FILE**: `files` (tenant-scoped via `BelongsToTenant`, polymorphic `attachable`); upload with a
  mime allowlist + 10 MB cap, tenant-prefixed storage, download (isolation-checked), delete, audit.
- **OBS**: `/health` extended with `queue` and `storage` checks plus queue pending/failed metrics.
- **SRCH**: `GlobalSearch` over organizations/members/teams/invoices/files — grouped, tenant-scoped,
  permission-filtered — behind a ⌘K command palette with client-side recent searches.
- **ONBD**: `OnboardingChecklist` (state-derived, resumable) shown on the dashboard.

## Automated test results

- **Pest: 166/166 PASS** (544 assertions). New Stage 4 suites (28 tests): billing scheduler
  (renewal/scheduled-cancel/trial-expiry/grace/trial-ending), notifications (delivery, preference
  resolution, wired events, read/mark-all, security-locked), files (upload validation, tenant
  isolation on download, delete, permission), search (grouping, isolation, permission filtering,
  blank query), health (queue+storage), onboarding checklist.
- Pint PASS · PHPStan L6: 0 errors · Prettier PASS · ESLint PASS · tsc PASS.
- One Stage 2 RBAC assertion updated (Viewer now also has `files.view`).

## Manual QA (browser, http://localhost:8734)

- Dashboard: ⌘K search trigger, notification bell showing **1** unread, onboarding checklist
  (**1 of 5** steps, with per-step links).
- Notification center: the queued "Payment failed" billing notification, mark-read actions, and the
  preference matrix (categories × channels; security row locked).
- Files page: upload control + empty state.
- Command palette: searching "Nora" returned grouped **Organizations** (Nora MSP) and **Members**
  (Nora Ops) — tenant-scoped, permission-filtered.
- Queue worker delivered the notification (`queue:work` processed the `PaymentFailedNotification`
  job), demonstrating JOBS + NOTIF together.

## Defects discovered & fixed

- Health queue check used always-true comparisons (PHPStan) → rewritten to actually probe the
  jobs/failed_jobs tables inside the failure-catching wrapper.
- Notifications are queued, so a worker is required to deliver them locally (documented); tests run
  the queue synchronously.

## Deferred (tracked in register, not dropped)

- **INTG** connector framework + connectors; **API** public versioned REST (Sanctum tokens already
  exist); **DSGN** formal design-system documentation (the component library already exists and is
  in use) — the Stage 4 continuation.
- NOTIF SMS/Slack/Teams/webhook channels (need integrations); marketing/sales alert _types_ (need
  their modules). JOBS Horizon dashboard (Linux worker + platform admin, Stage 13). OBS metrics
  backend + external alerting.

## Completion

**APPROVED — Stage 4 (core-platform backbone) gate passed.** The Stage 3 recurring-billing debt is
closed. Next: Stage 4 continuation (INTG framework, public API, design-system docs), then Stage 5 — CRM.
