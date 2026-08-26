# Module Specification — Marketing Platform (LEAD / AUTO / EMAIL / SMS / FUNL)

> Stage 6. Complete before coding (Master Prompt §58–59). Depends on Stage 5 CRM
> (Contact/Lead/Deal), Stage 4 (JOBS/NOTIF/INTG/API), Stage 3 (ENTL), Stage 2 (RBAC/audit).

## Purpose

The marketing execution engine that turns anonymous traffic into tracked, nurtured, sales-ready
contacts: **capture** (forms + landing pages), **organize** (lists/segments + lifecycle), **engage**
(email + SMS campaigns), **automate** (trigger→action workflows / nurture tracks), and **measure**
(engagement tracking + funnel reporting). It sits on top of CRM records and feeds the sales modules.

Per ADR-0004, all sending goes through a `MailProvider`/`SmsProvider` abstraction; the **log** drivers
are fully working and tested, real SMTP/Twilio drivers are Implemented-untested (no credentials here).

## Users & roles

Marketing Manager (full), Marketing User (create/edit, send limited), Sales Manager/Rep (view + leads),
Analyst/Viewer (view). New permissions (`domain.action`):

- `marketing.view` — view marketing surfaces + analytics.
- `marketing.lists.manage` — create/edit lists + membership.
- `marketing.forms.manage` — create/edit/publish forms + landing pages.
- `marketing.campaigns.manage` — create/edit campaigns + templates.
- `marketing.campaigns.send` — schedule/send a campaign (separated so juniors can draft, not blast).
- `marketing.automation.manage` — create/edit/activate workflows.
- `marketing.funnels.view` — view funnel config + reporting.

Public capture, tracking and unsubscribe endpoints are **unauthenticated** (tenant resolved by the
form/page slug or a signed recipient token).

## Feature IDs (register rows in scope)

- **LEAD-001…023** — lead generation & lifecycle. Capture/source/segmentation/routing/qualification/
  lifecycle/conversion/attribution are **built**; "types of leads" (inbound/MQL/SQL/organic/paid/
  social/content/phone) are represented as **source + lifecycle_stage values** on captured leads
  (Tested for the mechanism). Buyer-intent detection (LEAD-018) → **Planned** (Stage 10 INTENT).
- **AUTO-001…028** — workflow engine (triggers, nurture tracks, drip, actions). Trigger types
  form_submission/lead_stage/deal_stage/email_engagement/list-membership and actions send email/SMS,
  assign, create task, update CRM, change score, change lifecycle, notify, add/remove list, schedule
  follow-up are **built + tested**. page-visit (AUTO-003) and retargeting-audience (AUTO-027) →
  **Partial/Planned** (need tracking script / ad connectors). Lead-score change (AUTO-023) writes a
  `lead_score` field now; full scoring is Stage 10 (LSCR).
- **EMAIL-001…020** — campaigns, HTML templates, sequences, personalization, segmentation,
  behavioral triggers, analytics, open/click tracking, unsubscribe. **Built + tested** via the log
  driver. A/B testing (EMAIL-015) → **Partial** (schema-ready; UI later). Dynamic content
  (EMAIL-013) → merge-tags implemented; conditional blocks **Partial**.
- **SMS-001…008** — SMS nurture/reminders/alerts/follow-up + opt-in/opt-out. Engine **built + tested**
  via the log SMS driver; real send is Twilio (untested). Opt-in/opt-out **built + tested**.
- **FUNL-001…024** — funnel model + stage tracking + post-conversion (scoring/notification/booking/
  follow-up/pipeline). Funnel **config + stage counts + lifecycle tracking built + tested**.
  FUNL-001…019 name **content/channel types** owned by later stages (SEO/Content/Ads/Booking); they
  are represented as funnel-stage categories and **cross-referenced**, not re-built here.

Full per-ID status lands in the register at gate time (honest §38).

## Subscription requirements

- New `Feature::Marketing` gates forms/landing/lists/campaigns/funnels (`entitlement:marketing`).
- Existing `Feature::Automation` gates the workflow engine (`entitlement:automation`).
- PlanCatalog: grant `marketing` to Growth (trial), Professional, Agency, Enterprise; `automation`
  already on Growth+. Starter → neither.
- Usage meter `emails_sent` (monthly) with a plan limit; sending checks the meter (soft-warn at 80%,
  block over limit) via the central UsageMeter — no scattered checks.

## Database entities (all tenant-scoped via `BelongsToTenant`)

- `marketing_lists` (id, org, name, description, type[static|dynamic], criteria json, member_count).
- `list_memberships` (id, org, marketing_list_id, contact_id, added_at) — unique(list, contact).
- `forms` (id, org, name, slug unique, fields json[{name,label,type,required}], settings json
  {redirect_url, success_message, double_optin}, target_list_id, lifecycle_stage, status[draft|
  published], submission_count).
- `form_submissions` (id, org, form_id, contact_id, payload json, ip, user_agent).
- `landing_pages` (id, org, name, slug unique, headline, subheadline, body_html, form_id,
  status[draft|published], view_count).
- `email_templates` (id, org, name, subject, html, text).
- `campaigns` (id, org, name, channel[email|sms], type, subject, from*name, from_email, preheader,
  content_html/text OR body, marketing_list_id, status[draft|scheduled|sending|sent|failed],
  scheduled_at, sent_at, stat*\* counters: recipients, sent, opened, clicked, bounced, unsubscribed).
- `campaign_recipients` (id, org, campaign_id, contact_id, email/phone snapshot, token unique,
  status[pending|sent|failed|bounced], sent_at, opened_at, clicked_at, unsubscribed_at, error).
- `outbound_messages` (id, org, channel, contact_id, subject, body, status, token, sent_at,
  opened_at, clicked_at, provider_message_id, error, source[automation|manual], workflow_id null) —
  single sends from automation/manual, tracked like campaign recipients.
- `workflows` (id, org, name, description, trigger_type, trigger_config json, status[active|paused],
  enrolled_count, completed_count).
- `workflow_steps` (id, org, workflow_id, position, action_type, action_config json, delay_minutes).
- `workflow_enrollments` (id, org, workflow_id, contact_id, current_position, status[active|
  completed|exited], next_run_at, enrolled_at, completed_at) — unique(workflow, contact) while active.
- `suppressions` (id, org, channel[email|sms], address, reason[unsubscribe|optout|bounce|complaint],
  contact_id null) — unique(org, channel, address). Central consent gate.
- `funnels` (id, org, name, description) + `funnel_stages` (id, org, funnel_id, name, position,
  category[tof|mof|bof|post], lifecycle_stage) — a captured contact's `lifecycle_stage` positions it.
- **Contact additions** (migration): `lifecycle_stage` (subscriber|lead|mql|sql|opportunity|customer),
  `lead_score` (int, default 0), `email_opt_in` (bool), `sms_opt_in` (bool). Lead gets `lead_score`,
  `lifecycle_stage`, `segment`.

Indexes on all (org, status), (org, slug unique), tracking `token` unique. Migration timestamps
after Stage 5.

## API / endpoints

**Authenticated admin** (`/marketing/*`, web, `organization` + `entitlement` + `can:`):
lists CRUD + membership; forms CRUD + publish; landing pages CRUD + publish; templates CRUD;
campaigns CRUD + `POST /campaigns/{c}/schedule` + `/send` + `/test`; workflows CRUD + steps +
activate/pause; funnels CRUD; marketing dashboard.

**Public** (no auth, CSRF-exempt, tenant by slug/token):

- `GET /f/{slug}` render form · `POST /f/{slug}` submit (honeypot + rate-limited).
- `GET /p/{slug}` render landing page.
- `GET /e/o/{token}.gif` open pixel · `GET /e/c/{token}` click redirect (`?u=` validated) ·
  `GET /e/u/{token}` + `POST` unsubscribe/opt-out.

Public API v1 additions deferred (kept behind the same envelope) — not in this stage.

## UI pages & components

Sidebar **Marketing** group: Dashboard, Lists, Forms, Landing Pages, Campaigns, Automation, Funnels.
Reuse design-system primitives (card, badge, dialog, input, select, table). New: simple form-field
editor, campaign composer (subject + body + audience + merge-tag helper), workflow builder
(trigger + ordered steps), funnel board. Public: minimal branded form + landing + unsubscribe pages
(own layout, not the app shell).

## Integrations

Uses the INTG framework conceptually (email/SMS providers are messaging drivers, not INTG connectors).
When no real provider configured → log driver (dev/test). Retargeting-audience action requires ad
connectors (Planned).

## Notifications

Emits in-app/email notifications via the existing NotificationDispatcher for: new lead captured
(to owners), campaign send complete, workflow action `send_notification`. Honors user preferences.

## Background jobs

- `SendCampaignJob` (queued) — resolves recipients (list minus suppressions), dispatches per-recipient
  via provider, records rows, updates stats; idempotent per recipient (skip already-sent); `tries=3`.
- `RunWorkflowStep` (queued) — advances one enrollment, executes the step action, schedules the next
  with its delay; idempotent on `current_position`.
- Scheduler: `workflows:tick` (dispatch due enrollments where `next_run_at <= now`), `campaigns:tick`
  (send due scheduled campaigns). Both re-establish tenant context per row.

## Business rules & validation

- Capture dedupes Contact by email within the org (reuse CRM dedupe); missing email → create by
  phone or anonymous with a generated key.
- A recipient is skipped if suppressed for that channel; unsubscribing adds a suppression and blocks
  all future sends.
- Sending requires `marketing.campaigns.send` + within `emails_sent` usage limit + a published
  audience; can't send an already-sent campaign.
- Workflow enrollment is unique per active (workflow, contact); re-trigger while active is a no-op.
- Merge tags `{{first_name}}` etc. render from the contact; unknown tags render empty, never leak raw.

## Error cases

Validation (422 + field errors); permission (403); not-found tenant-scoped (404); provider send
failure → recipient/message `failed` + error captured, campaign continues (partial-failure), stats
reflect it; public form invalid → inline errors, honeypot/ratelimit → silent drop.

## Audit requirements

`marketing.list.*`, `form.*`, `landing_page.*`, `campaign.created|scheduled|sent`, `workflow.*`,
`lead.captured`, `contact.unsubscribed` with before/after where relevant.

## Analytics events

Per-campaign: recipients/sent/opened/clicked/bounced/unsubscribed. Per-form: submission_count.
Per-workflow: enrolled/completed. Funnel: contacts per stage. Surfaced on the marketing dashboard.

## Automated tests

- Lead capture: public form submit → creates/dedupes contact, adds to list, sets lifecycle, fires
  workflow, audits; honeypot/rate-limit; tenant resolby slug.
- Lists: create, add/remove, dynamic criteria eval, tenant isolation.
- Campaign: create → send (log driver) → recipients recorded, stats updated, suppressed skipped;
  open pixel + click redirect + unsubscribe update rows and create suppression; usage-limit block.
- Workflow: trigger enrolls, `RunWorkflowStep` executes each action type, delays schedule next,
  completion; re-trigger no-op; action executor for each action.
- SMS: opt-out suppresses; send via log driver records message.
- RBAC matrix (view vs manage vs send vs automation) + entitlement (marketing/automation) gating +
  tenant isolation on every entity.
- Funnel stage counts.

## Manual QA checklist

Create a list; build + publish a form; submit it on the public URL → contact appears in the list and
CRM; compose + send an email campaign to the list → recipients + stats; open the tracking pixel /
click link → stats increment; unsubscribe → suppression blocks re-send; build a form-submission
workflow that sends a welcome email → submitting fires it. Responsive + empty states.

## Acceptance criteria

- [ ] All entities tenant-scoped; every admin route `can:`-gated + entitlement-gated.
- [ ] Public capture/tracking/unsubscribe work unauthenticated and tenant-safely.
- [ ] Campaign + workflow sending runs end-to-end on the log driver with real tracking + suppression.
- [ ] Central consent (suppressions) enforced by the dispatch pipeline; usage limit enforced centrally.
- [ ] Full quality gate green; honest §38 register statuses; Module Completion Report filed.
