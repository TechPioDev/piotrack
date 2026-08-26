# Module Completion Report — Administration & Service Delivery (Stage 13)

Date: 2026-08-14
Spec: [docs/specs/stage-13-administration-and-service-delivery.md](../specs/stage-13-administration-and-service-delivery.md)
ADR: none new
Scope: ADMIN-001…006, PORTAL-001…018, PROJ-001…016, SUPP-001…003, TRAIN-001…013, STRAT-001…032,
BRAND-001…037, METH-001…005, PERF-001…011 (141 features — the largest stage in the project)

## The scope line applied

This stage is different from Stages 5–12: a large share of its rows describe **work a human performs**,
not a computation. Three categories were applied and are visible in every register note:

1. **Computed / operational software** — platform admin, impersonation, feature flags, tickets, help
   centre, projects/sprints/tasks/deliverables + approvals, the client portal, KPI targets and
   attainment, performance agreements, and the five-P methodology. **Tested.**
2. **Workspaces that structure human work** — strategy assessments/audits/research, brand positioning
   and messaging, consulting and training engagements. The platform records, assigns, prioritises,
   schedules and reports; the analysis is a consultant's. **Partially Implemented**, each note naming
   what the software does versus what the human does.
3. **Creative production** — logo, graphic style, iconography, visual identity. The platform stores the
   artefacts and guidelines; it does not produce them. **Planned**, dependency named.

Where an earlier stage already computes part of a STRAT row (SEO audit → Stage 7, keyword opportunity →
Stage 7, competitor/share-of-voice → Stage 11, conversion/funnel → Stage 11, PPC → Stage 8), the
strategy item carries a `source_module` cross-reference instead of the work being rebuilt or claimed twice.

## Status summary

| Area                                                                                                 | Result                                                            |
| ---------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| Platform console: tenants, subscriptions, usage, MRR, AI spend                                       | Tested (ADMIN-001/003)                                            |
| Feature flags: kill switch, org targeting, deterministic % rollout                                   | Tested (ADMIN-004)                                                |
| Announcements + release notes, support tooling                                                       | Tested (ADMIN-005)                                                |
| **Support impersonation — permissioned, visible, audited**                                           | Tested (ADMIN-006)                                                |
| Plan/entitlement/coupon/payment administration                                                       | Partial — code-defined catalog, no admin editor (ADMIN-002)       |
| Help centre, tickets (internal notes hidden), announcements                                          | Tested (SUPP-001…003)                                             |
| Delivery team roles, sprints, tasks, deliverables, approvals                                         | Tested (PROJ-001…014)                                             |
| Monthly / quarterly reviews                                                                          | Partial — scheduled + data-backed, human-delivered (PROJ-015/016) |
| Client portal: login, dashboard, projects, tasks, deliverables, approvals, tickets, KPI/lead/revenue | Tested (PORTAL-001…011/016…018)                                   |
| Portal files, reports, campaign status, roadmap views                                                | Partial (PORTAL-003/012/013/015)                                  |
| Portal meeting notes                                                                                 | Planned (PORTAL-014)                                              |
| Five-P methodology computed from real module signals + evidence                                      | Tested (METH-001…005)                                             |
| Performance agreements, targets, quality criteria, lead replacement                                  | Tested (PERF-001…003/005…009)                                     |
| SLA breach + ROI review, guaranteed-deliverable reconciliation                                       | Partial (PERF-004/010/011)                                        |
| KPI definition + targets vs real actuals                                                             | Tested (STRAT-027…032)                                            |
| Roadmap, quarterly strategy, prioritisation                                                          | Tested (STRAT-024…026)                                            |
| Assessments, audits, research, positioning analysis                                                  | Partial — workspace tested, analysis human (21 STRAT rows)        |
| Brand positioning, messaging, tagline, asset library                                                 | Tested (20 BRAND rows)                                            |
| Palette/typography/imagery direction, discovery, positioning analysis                                | Partial (11 BRAND rows)                                           |
| Logo, graphic style, iconography, visual identity production                                         | Planned — creative work (BRAND-019/023…027)                       |
| Masterclasses, workshops, QBRs, reviews, growth planning                                             | Tested (TRAIN-008…013)                                            |
| Consulting + training delivery                                                                       | Partial — booked/tracked, human-delivered (TRAIN-001…007)         |

## Architecture delivered

- **Data model** — 4 **platform-scoped** tables (deliberately not tenant-scoped): `feature_flags`,
  `announcements`, `impersonation_sessions`, `kb_articles`; and 11 tenant-scoped: `tickets`,
  `ticket_messages`, `projects`, `project_members`, `sprints`, `project_tasks`, `deliverables`,
  `strategy_plans`, `strategy_items`, `kpi_targets`, `brand_profiles`, `brand_assets`, `engagements`,
  `performance_agreements`, `lead_replacements`.
- **`ImpersonationService`** — the rules live in the service, not the controller, so no future caller
  can skip them: platform-only, mandatory reason, **platform staff can never be impersonated**, no
  self-impersonation, session id in the request session so the UI always shows a banner, start and stop
  both audited with both user ids, stop restores the operator.
- **`FeatureFlagService`** — kill switch → org targeting → deterministic percentage bucket → default.
- **`PlatformAdminService`** — cross-tenant rollups including normalized MRR (annual ÷ 12).
- **`ProjectService`** — delivery-role staffing, sprints, tasks, progress (done/overdue/completion) and
  the approval workflow, with rejection returning work to in-progress rather than destroying it.
- **`TicketService`** — threads with internal notes that never reach a client view; replying reopens a
  resolved ticket.
- **`PortalService`** — the client read model: only `client_visible` deliverables leave it.
- **`KpiTargetService`** / **`PerformanceService`** — targets and guarantees measured against the real
  Stage 11 funnel, net of replaced leads, with lower-is-better metrics handled correctly.
- **`MethodologyService`** — Position/Presence/Pipeline/Pursuit/Profit scored from real signals in the
  earlier modules, each score carrying the evidence that produced it.
- **RBAC** — 10 new permissions plus a new minimal **`Role::Client`** (portal access + approving its own
  deliverables, nothing else). Platform administration is deliberately in **no** organization role; it
  is held through `users.platform_role`.
- **Routes** — `/platform/*` (outside tenant middleware), `/projects`, `/support`, `/strategy`,
  `/portal`. Stopping impersonation is intentionally ungated so nobody can be trapped in a session.

## Automated test results

- **Pest: 439/439 PASS** (1445 assertions) · PHPStan L6: 0 errors · Pint PASS · Prettier PASS ·
  ESLint PASS · tsc PASS · `npm run build` PASS.
- +40 tests across 5 suites:
    - **Impersonation (9)**: audited session with reason; **platform staff can never be impersonated**;
      non-platform users refused; reason required; stop ends the session and restores the operator;
      the banner state reaches the UI; the route requires the permission; stopping needs none; the
      platform console refuses tenant users.
    - Feature flags (6): unknown flag off; default; org targeting; **kill switch overrides targeting and
      100% rollout**; deterministic percentage; save through the console.
    - Projects/support (8): role staffing incl. idempotency + unknown role; sprint/task progress with
      overdue; submit→approve records approver and makes visible; **rejection is recoverable**; viewer
      cannot approve; internal notes stay out of the client thread; replying reopens a resolved ticket;
      cross-tenant project edit 404s.
    - **Portal (8)**: only client-visible deliverables listed; a hidden deliverable **404s rather than
      403s** so its existence is never revealed; client approve and reject-with-feedback; client raises a
      ticket; internal notes stripped; **the Client role is refused CRM, projects, support, strategy and
      the platform console**; non-client roles refused the portal.
    - Strategy/performance (9): KPI attainment vs real actuals; lower-is-better metric; attainment net of
      replaced leads; expired unmet agreement reported **breached**; quality-criteria evaluation; five-P
      scores + evidence from real data; strategy item cross-references its source module; viewer cannot
      edit; cross-tenant agreement delete 404s.

## Defects discovered & fixed

- **The impersonation dialog required typing a raw user ID.** `PlatformAdminService::tenants()` shipped
  only organization-level fields, so the operator had to type the target's numeric id — and mistyping
  one means impersonating the _wrong customer_, which the audit log would faithfully record as
  intentional. Fixed by shipping each tenant's impersonatable members (id, name, email) with platform
  staff filtered out server-side, and replacing the numeric input with a named select. Covered by a
  test asserting members are listed and platform staff excluded.
- **`PerformanceService::create()` returned a model with null `status`/targets**, so reading attainment
  in the same request threw a `TypeError`. Column defaults are not hydrated onto the in-memory model
  after `create()` — the same class of bug as the Stage 9 `ContentService` slug/status defect. Defaulted
  the fields in the service. Caught by the attainment tests.
- **`bookings.booking_page_id` non-nullable** (carried over from Stage 12's AI booking action) — already
  handled, revalidated here.
- PHPStan (50 → 0): missing `@property Carbon` annotations on every new date-cast column (the root cause
  of most errors), plus nested-Collection invariance in two controller payloads.

## Manual QA

Verified through the test suite rather than a browser session (browser login requires entering a
password, outside the assistant's allowed actions). What that covers is the substance of the stage:
every impersonation guard rail, the portal's narrowing to client-visible records, the approval
workflow's recoverable rejection, feature-flag resolution order, and attainment computed from the real
funnel. All 11 pages type-check and lint clean and render under test via their Inertia routes; the
impersonation banner state is asserted to reach the client (`impersonation.active` / `impersonation.user`).

## Deferred (tracked in register, not dropped)

- Plan/entitlement/coupon admin editor and manual payment actions (needs live Stripe).
- Portal meeting notes; per-client file permissions; downloadable/scheduled PDF reports; dedicated
  campaign-status and roadmap portal views.
- Automated SLA-breach notification; promised-vs-delivered reconciliation.
- Creative production (logo, graphic style, iconography, visual identity) — human/design tooling.
- An LMS surface (materials, completion tracking) for training.
- Sprint/deadline reminder jobs.
- **Known rough edges carried forward** (raised during UI review, not defects): lead replacement still
  takes numeric contact ids because `PerformanceController::index` ships no contacts list; KPI money
  targets (`mrr`, `revenue`) are entered and displayed in the same raw units as the analytics actuals so
  the two stay comparable — a currency-aware input would be clearer; announcement `audience` is a free
  string server-side while the UI offers a fixed set.

## Completion

**APPROVED — Stage 13 (Administration & Service Delivery) gate passed.** piotrack can now be _operated_
and the service _delivered_: platform staff have a cross-tenant console with feature flags and an
impersonation capability that cannot be used invisibly or to escalate privilege; delivery teams run
projects, sprints and approvals; clients get a portal narrowed to exactly what was shared with them; and
the strategy, brand, training and performance-guarantee workspaces structure the consulting engagement
with its results measured against real data. Next per the phase plan: Stage 14 — Production hardening
(SEC / BCK / PRIV + regression).
