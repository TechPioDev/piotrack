# Module Completion Report — Marketing Platform (Stage 6)

Date: 2026-08-14
Spec: [docs/specs/stage-6-marketing-platform.md](../specs/stage-6-marketing-platform.md)
ADR: [ADR-0004 — Messaging provider abstraction](../architecture/adr/ADR-0004-messaging-provider-abstraction.md)
Scope: LEAD-001…023, AUTO-001…028, EMAIL-001…020, SMS-001…008, FUNL-001…024 (102 features)

## Status summary

| Area                                                                         | Result                                                            |
| ---------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| Forms + public capture (dedupe, source, lifecycle, list add, trigger)        | Tested (LEAD-008/015/016/017/019/021)                             |
| Landing pages (public render + attached form)                                | Tested                                                            |
| Lists / segments (static + dynamic criteria)                                 | Tested (LEAD-017)                                                 |
| Workflow engine (triggers, ordered steps, delays, enrollment)                | Tested (AUTO-001/002/004/006/007, 009–017)                        |
| Workflow actions (11 action types)                                           | Tested (AUTO-018…026, 028)                                        |
| Email campaigns (send, HTML, personalization, analytics, tracking, unsub)    | Tested via log driver (EMAIL-001/002/004…008/010/011/016…018/020) |
| SMS campaigns + opt-in/opt-out                                               | Tested via log driver (SMS-001/004/005/007/008)                   |
| Funnels (config + stage counts + post-conversion notify/follow-up/pipeline)  | Tested (FUNL-012/021/023/024)                                     |
| Consent / suppression (central gate)                                         | Tested                                                            |
| Usage metering (email limit)                                                 | Tested                                                            |
| Real email/SMS delivery (SMTP / Twilio drivers)                              | Implemented — untested (no credentials)                           |
| Page-visit/content-download triggers, retargeting, A/B, buyer-intent         | Planned (later stages)                                            |
| Lead types by channel, booking/consultation/assessment, funnel content types | Partial/Planned — owned by SEO/Ads/Content/Booking/Sales stages   |

Per ADR-0004, the **log** mail/SMS drivers are the tested default; the real SMTP/Twilio drivers are
real code labelled _Implemented (untested — requires credentials)_, never "Tested" (§38).

## Architecture delivered

- **Data model** (15 tables, all tenant-scoped via `BelongsToTenant`): `marketing_lists`,
  `list_memberships`, `forms`, `form_submissions`, `landing_pages`, `email_templates`, `campaigns`,
  `campaign_recipients`, `outbound_messages`, `workflows`, `workflow_steps`, `workflow_enrollments`,
  `suppressions`, `funnels`, `funnel_stages`; plus `contacts.lifecycle_stage/lead_score/email_opt_in/
sms_opt_in` and `leads.lifecycle_stage/lead_score/segment`.
- **Messaging abstraction (ADR-0004)**: `MailProvider`/`SmsProvider` interfaces + `SentResult`,
  `Log*` drivers (tested; a sentinel address forces the failure path), `Smtp`/`Twilio` drivers
  (real, untested), a `MessagingProviderManager` + `config/marketing.php`, bound in the container.
- **Services**: `LeadCaptureService` (dedupe + list + lifecycle + trigger + audit + owner notify),
  `ListService` (static + dynamic), `CampaignService` (recipient resolution − suppression − opt-out,
  per-recipient tracking, usage limit, partial-failure), `MessageDispatcher` (single sends for
  automation), `EmailTrackingService` (open/click/unsubscribe → suppression), `WorkflowEngine` +
  `ActionExecutor` (11 actions), `MarketingTrigger` (trigger → enroll), `FunnelService`.
- **Jobs + scheduler**: `SendCampaignJob`, `RunWorkflowStep` (both re-establish tenant context,
  `tries=3`); `marketing:process-workflows` + `marketing:send-scheduled-campaigns` every 5 minutes.
- **RBAC + entitlement**: 7 `marketing.*` permissions (drafting separated from `campaigns.send`) in
  the role map; new `Feature::Marketing` granted on Growth/Professional/Agency/Enterprise; email
  `Limit::Emails` on plans, enforced centrally via `UsageMeter`. Workflows also require
  `Feature::Automation`.
- **Controllers + routes**: 35 admin routes (`/marketing/*`, `can:` + `entitlement:` gated) +
  public unauthenticated endpoints — `GET/POST /f/{slug}` (form, honeypot + throttle), `GET /p/{slug}`
  (landing), `GET /e/o|c/{token}` (open pixel / click redirect), `GET/POST /e/u/{token}`
  (unsubscribe). Public POSTs are CSRF-exempt; tenant is resolved from slug/token.
- **UI**: sidebar **Marketing** group; 10 Inertia/React pages (dashboard, lists index/show, forms,
  landing pages, campaigns index/show, automation index/show, funnels); public Blade pages (form,
  landing, unsubscribe, message) with their own minimal layout.

## Automated test results

- **Pest: 229/229 PASS** (797 assertions) — +26 marketing tests across 5 suites:
    - Lead capture (7): public submit → tenant-scoped contact + list + lifecycle + audit; dedupe;
      honeypot drop; required-field validation; unpublished-form 404; workflow enrollment on submit;
      tenant resolved by slug.
    - Campaigns (6): email send → recipients + stats (opted-out skipped); open/click/unsubscribe via
      public endpoints update rows + stats + create suppression; suppressed addresses skipped;
      provider partial-failure recorded without stopping; usage-limit block; SMS opt-in filtering.
    - Workflows (4): trigger enrolls + idempotent while active; paused workflow doesn't enroll; ordered
      steps with delay + completion; each action type executes.
    - Access (5): viewer read-only vs manage; drafting vs sending separated; plan-feature gating
      (marketing); tenant isolation.
    - Lists/Funnels (4): add/remove + member count; dynamic criteria resolution; tenant isolation;
      funnel stage counts by lifecycle.
- PHPStan L6: 0 errors · Pint PASS · Prettier PASS · ESLint PASS · tsc PASS · `npm run build` PASS.

## Manual QA (browser, http://localhost:8734)

Logged in as an Owner (Growth trial):

- **Marketing dashboard** renders KPI cards + lifecycle + recent-campaigns empty state; the
  `entitlement:marketing` gate returned **403 before** the plan sync and **200 after** — proving the
  feature gate.
- **Forms** admin lists the published form with its public URL + publish/delete actions.
- **Public capture end-to-end**: opened `/f/free-assessment` as an anonymous visitor, submitted
  (email + first name) → **"Thank you"** page → the **Newsletter list member count went 0 → 1** and
  the contact was created in CRM. This is the headline funnel proven through the real browser.
- **Campaign send**: sent a campaign to that list; the campaign **show page** renders status **sent**
  with **Recipients 1 / Sent 1** stat cards + the merge-tag hint.
- Lists, Campaigns, Automation pages render with correct empty states.

## Defects discovered & fixed

- PHPStan modeled `Model::find()` / a belongsTo access as non-null in two spots → replaced the
  redundant nullsafe with an explicit null-check / `findOrFail`, and removed an unused injected
  `AuditLogger` from `ListService`.
- The local plan rows predated the new `marketing` feature, so the dashboard 403'd until
  `billing:sync-plans` re-seeded the catalog (dev-data issue, not code) — verified the gate works
  before and after.

## Deferred (tracked in register, not dropped)

- Real email/SMS delivery (SMTP/Twilio — credentials); A/B testing + conditional content (EMAIL);
  page-visit/content-download/buyer-intent triggers + retargeting action (AUTO — need a tracking
  script / ad connectors / Stage 10); booking/consultation/assessment lead types + funnel content
  types (owned by SEO S7, Ads S8, Content S9, Booking/Sales S10); full lead scoring (Stage 10 LSCR).

## Completion

**APPROVED — Stage 6 (Marketing Platform) gate passed.** piotrack now captures leads (forms +
landing pages), organizes them (lists + lifecycle), engages them (email + SMS campaigns with real
tracking + consent), automates journeys (trigger→action workflows), and reports funnels — all
tenant-scoped, RBAC- and plan-gated, and proven end-to-end on the log drivers. Next per the phase
plan: Stage 7 — SEO & search intelligence (TSEO/KSEO/LSEO/AEO/GEO/LLMO), building on the CRM +
marketing acquisition surface.
