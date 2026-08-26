# Phase 1 — Baseline completion (supplement to Module 00)

Date: 2026-08-27. This closes out the Phase 1 discovery/audit gate. The bulk of the evidence lives in
`docs/qa/module-00-baseline-audit.md` (2026-08-26): route inventory (393 → `docs/audit/route-register.csv`),
the Acme journey test (`tests/Feature/Qa/AcmeJourneyTest.php`, 15/15 rungs), 17-page DOM/style UI audit,
live Jumpfactor research, competitive matrix, security/performance baselines. This supplement adds what the
Phase 1 gate requires beyond Module 00: state audits, the rubric module scoreboard, the ten most damaging
screens, and the single next-module recommendation.

## State audits (Rules 24–26)

- **Empty states: PASS (pattern-wide).** Sampled live on the empty Acme tenant: Contacts ("No contacts yet —
  add your first contact or import a CSV" + New contact), Campaigns, Keywords (+ content-gap explainer),
  Chat widgets — every sample explains the feature and offers the primary action. Dashboard shows a 5-step
  onboarding checklist instead of blank tiles. 76 page files carry explicit empty-state copy.
- **Loading states: MINIMAL BY DESIGN.** Inertia top progress bar configured; full page props are
  server-rendered so per-widget skeletons largely don't apply. `Skeleton` component exists but is used only
  by the sidebar. No blank-white-while-waiting cases observed. Adequate today; revisit if pages gain async
  widgets.
- **Error states: PASS (validation layer).** Live check: invalid campaign POST → 422 with named field errors
  ("The name field is required", "The selected channel is invalid"); every form renders these via
  `InputError` under the field. Integration disconnect/reconnect states exist and are test-covered. Failed
  external APIs surface as flash errors (AI console Test button verified earlier in session).
- **Actionability (Rule 19): PASS on all sampled list pages** — New campaign / New contact + Import + Export /
  Add keyword / New widget primary actions present.

## Module scoreboard — Rule 9 rubric

Feature 25 · UX/UI 20 · Analytics 15 · Automation 15 · Integration 10 · Business value 10 · Innovation 5.
Feature points from register completeness; UX from measured page scores; the rest from stage evidence.
Jumpfactor module scores: **Insufficient Public Evidence** (agency — no client-facing software to score;
capability claims are compared in the Module 00 matrix instead).

| Module | Feat/25 | UX/20 | Anl/15 | Auto/15 | Intg/10 | Val/10 | Inn/5 | **Score** | Band | Target |
|---|--:|--:|--:|--:|--:|--:|--:|--:|---|--:|
| Website Chat | 24 | 12 | 9 | 13 | 9 | 9 | 4 | **80** | Excellent | 90+ |
| CRM | 20 | 10 | 7 | 7 | 9 | 9 | 3 | **65** | Needs improvement | 90+ |
| Analytics & Attribution | 15 | 11 | 11 | 6 | 9 | 9 | 4 | **65** | Needs improvement | 95+ |
| SaaS platform (billing/tenancy/RBAC) | 21 | 9 | 5 | 11 | 8 | 8 | 2 | **62** | Needs improvement | 90+ |
| Marketing (campaigns/auto/funnels) | 15 | 9 | 6 | 11 | 7 | 8 | 1 | **57** | Weak | 90+ |
| AI Visibility (AEO/GEO/LLMO) | 14 | 9 | 7 | 8 | 5 | 8 | 3 | **54** | Weak | 95+ |
| Content & authority | 17 | 10 | 6 | 8 | 5 | 6 | 1 | **53** | Weak | 85+ |
| Website builder | 14 | 11 | 7 | 5 | 8 | 6 | 1 | **52** | Weak | 85+ |
| Dashboard | 12 | 13 | 6 | 4 | 7 | 7 | 1 | **50** | Weak | 90+ |
| Advertising | 14 | 10 | 7 | 6 | 3 | 6 | 1 | **47** | Weak | 85+ |
| Sales tools (scoring/booking/intent) | 10 | 9 | 5 | 7 | 7 | 7 | 1 | **46** | Weak | 90+ |
| SEO | 9 | 9 | 5 | 5 | 3 | 6 | 1 | **38** | Major redesign required | 90+ |

## Ten screens that most damage perceived quality

All scored ≤5/10 in the DOM audit; every one inherits the same three shell defects (white-on-white ground,
chartless tiles, weak header hierarchy), so the shell fix is the common prerequisite.

| # | Screen | Problem (measured) | Becomes | Visualization | Interaction | Target |
|--:|---|---|---|---|---|--:|
| 1 | /marketing | 6 KPI tiles, 0 charts, 299 chars | Marketing performance dashboard | Lead/campaign trend line + channel bars + funnel | Period compare, drill to campaign | 8.5 |
| 2 | /seo | 7 KPI tiles, 0 charts | SEO health dashboard | Visibility trend, ranking-distribution bars, health score card | Drill to keyword table | 8.5 |
| 3 | /sales | 6 KPI tiles, 0 charts, **document overflow** | Sales command view | Pipeline-by-stage bars, alerts feed | Hot-leads list → CRM | 8.5 |
| 4 | /billing | No h1, no usage viz, 632 chars | Plan & usage panel | Entitlement usage meters, invoice table | Upgrade/downgrade CTAs | 8 |
| 5 | /ads | 10 KPIs, 1 thin table | Ad performance dashboard | Spend/ROAS trend + campaign bars | Budget drill-down | 8 |
| 6 | /content | 8 KPIs, 0 charts | Editorial pipeline board | Status pipeline + cadence calendar strip | Piece drill-in | 8 |
| 7 | /crm/deals | Board stretches document (sw 2076/vw 1052) | Contained kanban + pipeline header | Stage-value bar above board | Drag between stages (exists), filters | 8.5 |
| 8 | /ai | 6 tiles, 0 charts | AI workspace | Credit-usage meter, action-approval queue | Run agent tasks inline | 8 |
| 9 | /projects | No h1, 4 near-empty tables | Delivery board | Progress bars per project, sprint burndown-lite | Task quick-add | 8 |
| 10 | /settings/members | No h1, plain list | Team management panel | Seat-usage meter (entitlements) | Role change inline (exists) | 8 |

## Phase 1 status

Routes discovered **393** · features registered **1,191** · working (Tested) **700** · partial **307** ·
broken **0 feature-level** (3 UI defects recorded: shell overflow, 6 missing h1, 1 stale link) ·
missing (Planned) **172** · not tested (Implemented-untested) **11** (+1 N/A) ·
pages requiring UI redesign **10 of 17 scored** · average page UI score **52/100** (product UI 56/100).
Highest competitive gap: **productized lead generation** — funnels 17% + buyer intent/visitor ID 31% vs
Jumpfactor's core public story (E4, guarantee, real-time visitor ID). Best module: **Website Chat (80)**.
Weakest: **SEO (38)**.

## Recommended next module — exactly one

**Design System & Application Shell** ("Module 01"): page-background/surface tokens, `min-w-0` overflow fix,
PageHeader/h1 standard everywhere, a chart kit, and first charts on the five chartless dashboards from
existing real aggregates. Chosen by the gate's logic: it is the foundation dependency for all ten damaged
screens, repairs the user-facing quality perception fastest, requires no external integrations, and every
later module inherits it. Scope excludes new features. **Not started — awaiting approval.**
