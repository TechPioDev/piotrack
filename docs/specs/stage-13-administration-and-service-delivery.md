# Module Specification — Administration & Service Delivery (ADMIN / PORTAL / PROJ / SUPP / TRAIN / STRAT / BRAND / METH / PERF)

> Stage 13. Completed against the register at the module gate (Master Prompt §58–59).

## Purpose

Everything needed to _run_ the product and _deliver the service_: platform administration for our own
staff, a client-facing portal, project/sprint delivery, support infrastructure, and the strategy,
brand, training and performance-guarantee workspaces that structure the consulting work an MSP growth
engagement actually involves.

## The scope line for this stage (read first)

This stage differs from Stages 5–12: a large share of its register rows describe **work a human
performs**, not a computation software can do. Three honest categories, applied throughout:

1. **Computed / operational software** — platform admin, impersonation, feature flags, tickets, help
   center, projects, sprints, tasks, deliverables + approvals, the portal, KPI targets and attainment,
   the methodology readiness dashboard. Built and **Tested**.
2. **Workspaces that structure human work** — strategy assessments/audits/research/roadmaps, brand
   positioning and messaging, consulting/training engagements. The platform stores, versions, assigns,
   schedules and reports on these; the _analysis and judgment_ remain human. Recorded as **Partially
   Implemented**, with the note naming what the platform does versus what the consultant does. Marking
   "Competitor brand analysis" as Tested because a text field exists would be dishonest.
3. **Creative production** — logo creation, graphic style, imagery, typography selection. The platform
   holds the artefacts and guidelines; it does not produce them. **Planned** or **Partial** with the
   dependency named (design tooling / human designer).

Several STRAT rows are already satisfied computationally by earlier stages (SEO audit → Stage 7,
keyword opportunity → Stage 7, competitor analysis → Stage 11 CINT, website conversion audit → Stage 11
CRO, revenue opportunity modeling → Stage 11 ATTR). Those are cross-referenced rather than rebuilt.

## Users & roles

New permissions:

- `admin.platform` — platform staff console (tenants, subscriptions, usage/health, flags,
  announcements). Held by platform roles only, via the existing `users.platform_role`.
- `admin.impersonate` — start a support impersonation session.
- `support.view` / `support.manage` — tickets and help-center articles.
- `projects.view` / `projects.manage` — projects, sprints, tasks, deliverables.
- `projects.approve` — approve/reject a deliverable.
- `strategy.view` / `strategy.manage` — strategy, brand, training, performance workspaces.
- `portal.access` — the client-facing portal (granted to a new **Client** organization role).

The new `Role::Client` is deliberately minimal: portal access plus reading its own org's client-visible
delivery records. It holds no CRM, marketing, sales or analytics permissions.

## Feature IDs

ADMIN-001…006, PORTAL-001…018, PROJ-001…016, SUPP-001…003, TRAIN-001…013, STRAT-001…032,
BRAND-001…037, METH-001…005, PERF-001…011 (141 features).

## Subscription requirements

No new plan feature: administration is platform-internal, and delivery/strategy surfaces belong to the
service engagement rather than a plan tier. The portal is gated by `portal.access`, not by entitlement.
(`Feature::WhiteLabel` already exists for Agency+ and remains the hook for portal branding later.)

## Database entities

**Platform-scoped (deliberately NOT tenant-scoped)**

- `feature_flags` — key, description, is_enabled, rollout (json: organization ids / percentage),
  is_kill_switch.
- `announcements` — title, body, audience, published_at.
- `impersonation_sessions` — impersonator_id, user_id, organization_id, reason, started_at, ended_at.
- `kb_articles` — title, slug, body, category, is_published (the help center is product-wide).

**Tenant-scoped**

- `tickets` — subject, body, status, priority, requester_id, assignee_id, resolved_at;
  `ticket_messages` — ticket_id, user_id, body, is_internal.
- `projects` — name, description, status, health, starts_on, ends_on;
  `project_members` — project_id, user_id, role (strategist / project_manager / seo / ppc / developer /
  designer / copywriter / automation);
  `sprints` — project_id, name, goal, starts_on, ends_on, status;
  `project_tasks` — project_id, sprint_id?, title, status, priority, assignee_id, due_on;
  `deliverables` — project_id, title, type, status, due_on, approval_status, approved_by, approved_at,
  client_visible, notes.
- `strategy_plans` — name, period_start, period_end, status, summary;
  `strategy_items` — plan_id?, type (assessment / audit / research / roadmap / initiative), title,
  findings, recommendation, priority, status, due_on, source_module (cross-reference to the module that
  computes it, where one does);
  `kpi_targets` — metric (leads / sqls / meetings / cpl / mrr / revenue / roi), target_value,
  period_start, period_end.
- `brand_profiles` — one per org: positioning_statement, usp, value_proposition, differentiators,
  narrative, story, tone_of_voice, messaging_hierarchy, elevator_pitch, tagline, palette, typography,
  imagery_direction, guidelines_url;
  `brand_assets` — type, title, url, file_id?, notes.
- `engagements` — type (consulting / training / masterclass / workshop / qbr / strategy_review /
  competitive_review / growth_planning), topic (marketing / seo / sales / executive), title,
  scheduled_at, status, attendees, notes.
- `performance_agreements` — name, model (guarantee / performance_pricing / pay_per_lead), lead_target,
  sql_target, meeting_target, quality_criteria (json), sla_days, period_start, period_end, status;
  `lead_replacements` — contact_id, reason, replaced_at, replacement_contact_id.

## Services

- **`ImpersonationService`** (ADMIN-006) — start/stop with a mandatory reason; refuses to impersonate a
  platform user (privilege-escalation guard); records the session; every request during it is visibly
  flagged in the UI and audited with both the impersonator and the impersonated user.
- **`FeatureFlagService`** (ADMIN-004) — resolve a flag for an organization: kill switch wins, then
  explicit org targeting, then percentage rollout (deterministic per org), then the default.
- **`PlatformAdminService`** (ADMIN-001/002/003) — tenant/subscription/usage/health rollups across all
  organizations.
- **`TicketService`** / help-center CRUD (SUPP).
- **`ProjectService`** (PROJ) — projects, member roles, sprints, tasks, deliverables and the approval
  workflow (submit → approved/rejected, approver recorded).
- **`PortalService`** (PORTAL) — the client's read model: only `client_visible` deliverables, their
  org's projects/tasks/tickets/files, plus KPI + lead + revenue dashboards reusing Stage 11 analytics.
- **`StrategyService`** (STRAT) / **`BrandService`** (BRAND) / **`EngagementService`** (TRAIN) —
  workspace CRUD with status/priority workflow.
- **`KpiTargetService`** — targets vs actuals from the Stage 11 analytics funnel (serves STRAT-027…032
  and PERF-001…003).
- **`PerformanceService`** (PERF) — agreement attainment (lead/SQL/meeting targets vs actuals), lead
  quality criteria evaluation, lead replacement tracking, SLA status, ROI review.
- **`MethodologyService`** (METH) — the five P's (Position / Presence / Pipeline / Pursuit / Profit)
  scored from real signals in the existing modules, each with the evidence behind it.

## Background jobs

None new. (Sprint/deadline reminders are a natural follow-on and are recorded as deferred.)

## Business rules & validation

Impersonation requires a reason, cannot target platform staff, and ends on stop or logout. Deliverables
are client-visible only when explicitly marked. Approval records the approver and time; a rejected
deliverable returns to in-progress rather than being deleted. KPI attainment divides by zero safely.
Percentage rollout is deterministic for a given org so a tenant does not flip between requests.

## Audit requirements

`admin.impersonation.started` / `.stopped` (with reason, both user ids), `admin.feature_flag.updated`,
`support.ticket.created` / `.resolved`, `projects.deliverable.approved` / `.rejected`,
`strategy.plan.created`, `performance.agreement.created`.

## Automated tests

Impersonation: requires the permission; **cannot impersonate platform staff**; records + audits the
session with reason; stop ends it; the session is flagged to the UI. Feature flags: kill switch
overrides everything; org targeting; deterministic percentage rollout; default. Platform console:
non-platform users are refused. Tickets: create/reply/resolve, internal notes hidden from the portal.
Projects: member roles, sprint/task lifecycle, deliverable approval + rejection paths, approver
recorded. Portal: a Client sees only client-visible deliverables and its own org's data, cannot reach
CRM/admin, can approve and raise tickets. KPI/performance: attainment maths incl. zero-division; lead
replacement. Methodology: five P scores computed from real module data, each with evidence. Plus RBAC
and tenant isolation throughout.

## Acceptance criteria

- Impersonation is permissioned, refuses platform targets, is visibly indicated and fully audited.
- The portal exposes only client-visible data and no administrative surface.
- Deliverable approvals record who approved and when; rejection is recoverable.
- Strategy/brand/training rows are recorded honestly as workspaces for human work, not as automated
  analysis. Full gate green; Module Completion Report + register update; §65 cycle report.
