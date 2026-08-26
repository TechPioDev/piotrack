# Module Specification — Core Platform (Stage 4)

Stage 4. Spec completed 2026-08-13 before implementation. Depends on Stages 0–3.

## Scope of this delivery

Stage 4 in the phase plan spans nine modules (61 features). Per Master Prompt §64 (break large
work into sequential, tested modules — never skip), this delivery covers the **operational and
notification backbone plus files, search, and onboarding**, and closes the Stage 3 recurring-billing
debt. The remaining Core-Platform modules are sequenced to a Stage 4 continuation and tracked (not
dropped) in the register.

**This delivery (complete + tested):**

- **JOBS** — queue infrastructure, reliability conventions, scheduler, and scheduled commands that
  close **BILL-011/012** (trial expiry, subscription renewal, grace→suspend).
- **NOTIF** — in-app notification center, email channel, per-user preferences, and wired
  billing/operational events. (SMS/Slack/Teams/webhook channels and marketing/sales alert _types_
  are Partial/Planned — they need integrations and their originating modules.)
- **FILE** — tenant-scoped file storage with upload validation and a management UI.
- **OBS** — health checks extended to queue + storage; builds on Stage 0 structured logging.
- **SRCH** — global search across current tenant entities, grouped, with recent searches.
- **ONBD** — setup checklist (progress + resume) on the dashboard.

**Sequenced to Stage 4 continuation (register stays Planned/Partial with notes):**

- **INTG** — reusable connector framework + connectors.
- **API** — public versioned REST API (Sanctum tokens already exist from Stage 1).
- **DSGN** — formal design-system documentation (the component library already exists and is in use).

## JOBS (JOBS-001…004)

- Queue driver `database` (Stage 0); `jobs` + `failed_jobs` tables. Conventions: `$tries`,
  `backoff()`, `ShouldBeUnique` for idempotency, failed-job retention.
- Scheduler (`routes/console.php`): `subscriptions:expire-trials` (trialing past `trial_ends_at`
  → expire), `subscriptions:process-renewals` (active past `current_period_end` → renew + invoice,
  or apply scheduled cancellation), `subscriptions:enforce-grace` (past_due past `ends_at`
  → suspend). New `SubscriptionService::renew()`.
- Monitoring: health reports queue depth + failed count; a full jobs dashboard (Horizon) needs a
  Linux worker and lands with platform admin (Stage 13) → JOBS-004 Partial.

## NOTIF (NOTIF-001/002/008/010 → tested; 003/004/005/006/007/009 → partial/planned)

- Laravel database notifications on `User`; `notification_preferences` (user, category, channel,
  enabled). `NotificationDispatcher` sends via enabled channels only; email is queued.
- Notification classes: payment failed, trial ending, subscription suspended, member joined.
- In-app center: unread count shared to Inertia; notifications page; mark-read / mark-all-read.
- Preference center UI (categories × channels).
- Events wired: subscription past_due/suspended, trial ending (scheduler), member invitation accepted.

## FILE (FILE-001 tested; FILE-002 partial)

- `files` (organization_id tenant-scoped, uploaded_by, disk, path, name, mime, size, polymorphic
  `attachable_type/id` nullable). Upload validation (size + mime allowlist), tenant-prefixed paths.
- `FileController`: list, upload, download (ownership-checked), delete. Files settings page.
- Polymorphic attachment columns ready; CRM/ticket/project targets arrive with those modules.

## OBS (OBS-001/003 tested; 002/004 partial)

- `/health` extended: database, cache, queue (jobs reachable + failed count), storage writable.
- Structured logging + request IDs already in place (Stage 0). Metrics dashboard + external
  alerting are Partial (need a metrics backend / platform admin).

## SRCH (SRCH-001/002/003 → partial: works over current entities, expands per module)

- `GlobalSearch` over the current tenant's organizations, members, teams, invoices, files — grouped
  by type, permission-aware. Endpoint `GET /search?q=`. Command-palette UI (⌘K) + recent searches
  (client-side). Broadens as CRM/marketing entities land.

## ONBD (ONBD-013/014 tested; reflects 001–005 done earlier; 006–012 planned)

- Setup checklist derived from state (org created, on a plan/trial, team invited, billing details,
  brand file uploaded, dashboard visited). Dismissible; inherently resumable. Business profile / ICP
  / competitor / integration-wizard / initial-audit steps arrive with their modules.

## Business rules

- Renewal advances the period and issues an invoice via the active provider; a scheduled
  cancellation ends the subscription at period end instead of renewing.
- Notifications respect per-user preferences per channel; security-critical notices ignore opt-out.
- File uploads enforce a mime allowlist and size cap; files are tenant-isolated.
- Search never returns another tenant's records and respects the viewer's permissions.

## Tests

- Scheduler: trial past end → expired; active past period end → renewed + new invoice; scheduled
  cancel at period end → canceled (no renewal); past_due past grace → suspended.
- Notifications: dispatch writes a DB notification + queues mail; disabled preference suppresses a
  channel; mark-read; unread count; security notice ignores opt-out.
- Files: upload validation (reject bad mime/oversize), tenant isolation (can't download another
  tenant's file), delete, audit.
- Search: grouped results, tenant isolation, permission filtering.
- Health: reports queue + storage status.
- Stages 1–3 stay green.

## Acceptance criteria (gate)

Scheduler closes BILL-011/012 (verified by tests); notification center + preferences work; files
upload/list/download/delete with isolation; search is tenant-scoped and grouped; health reports new
checks; onboarding checklist reflects real state. Full quality gate green; browser QA; Module
Completion Report with honest status for sequenced modules.
