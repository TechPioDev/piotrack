# Module Specification — Analytics (ANLY / ATTR / CALL / CRO / BENCH / CINT / GSCORE / OMNI)

> Stage 11. Completed against the register at the module gate (Master Prompt §58–59).

## Purpose

The analytics stage turns everything the platform already captures (CRM pipeline, marketing/SEO/ads/
content/sales activity, billing) into decision-grade intelligence for MSP operators: a unified metrics
dashboard, revenue attribution, an anonymized peer-benchmark data layer, competitive share metrics, and
the flagship **MSP Growth Score** (0–100 composite with prioritized recommendations and trend). It is
predominantly a **read/aggregation layer** over Stages 3–10, plus four small writable sub-modules
(call tracking, CRO experiments, competitors, growth-score snapshots).

## Users & roles

Marketing/Sales managers and analysts read dashboards; managers configure call numbers, experiments and
competitors. Permissions (`analytics.*`):

- `analytics.view` — read every analytics surface (dashboard, attribution, benchmarks, growth score,
  omnichannel, competitors, calls, experiments).
- `analytics.calls.manage` — provision tracking numbers, log/score calls.
- `analytics.experiments.manage` — create CRO experiments/variants, record results.
- `analytics.competitors.manage` — manage tracked competitors.

Owner/Admin/MarketingManager/SalesManager get all; Analyst gets view + experiments; Marketing/Sales
users + Viewer get view.

## Feature IDs

ANLY-001…036, ATTR-001…017, CALL-001…011, CRO-001…017, BENCH-001…018, CINT-001…013, GSCORE-001…013,
OMNI-001…018 (143 features).

## Subscription requirements

New `Feature::Analytics`, granted **Professional, Agency, Enterprise** (full-funnel intelligence tier;
Growth's marketing/SEO/content stops short of cross-channel ads+sales analytics). The whole `/analytics`
area is gated by `entitlement:analytics`. No new usage meters (read-heavy).

## Database entities (new, all tenant-scoped via BelongsToTenant)

- `call_tracking_numbers` — phone_number, label, source, campaign, is_active.
- `calls` — call_tracking_number_id?, contact_id?, from_number, to_number, direction, duration_seconds,
  status, source, campaign, owner_id?, score, is_qualified, converted, recording_url?, transcript?,
  summary?, occurred_at.
- `experiments` — name, type (landing_page/cta/form/copy/headline/offer/layout/…), hypothesis, status
  (draft/running/completed), primary_metric, winning_variant_id?, started_at?, ended_at?.
- `experiment_variants` — experiment_id, name, is_control, impressions, conversions.
- `competitors` — name, domain, notes, is_tracked.
- `growth_scores` — overall, breakdown (json sub-scores), recommendations (json), computed_at (snapshot
  per computation for trend).

All other modules (ANLY/ATTR/OMNI/BENCH/CINT-share) read existing tables — no new storage.

## Services

- **AnalyticsService (ANLY)** — dashboard rollups from real tenant data: acquisition funnel (leads/MQL/
  SQL from `contacts.lifecycle_stage`, meetings from `bookings`, opportunities/proposals/won/lost from
  `deals` × `pipeline_stages.is_won/is_lost`, qualified pipeline value), ad KPIs (`AdKpi` over
  `ad_metrics`), SEO summary (top-three/page-one/map from `keywords`/`keyword_rankings`, visibility),
  revenue (MRR/ARR/contract value/LTV from won `deals`), and a lead-source channel breakdown.
- **AttributionService (ATTR)** — first-touch (contact.lead_source / earliest activity), last-touch
  (latest pre-conversion activity), linear multi-touch across a contact's `activities`; channel/campaign
  rollups of won-deal revenue; CAC (ad spend ÷ customers) and marketing ROI (revenue ÷ spend).
- **CallTrackingService (CALL)** + **CallProvider** abstraction — `FixtureCallProvider` (tested,
  deterministic number provisioning) + live CallRail/Twilio driver (real, untested, no creds). Log +
  score + attribute (source/campaign/owner) + conversion. Recording/transcription/AI-summary are
  provider-only (Planned).
- **ExperimentService (CRO)** — create experiment + variants; record impressions/conversions; compute
  per-variant conversion rate + lift vs control + pick the leader.
- **BenchmarkService (BENCH)** — the proprietary data layer: cross-tenant **anonymized** aggregates with
  a **k-anonymity minimum cohort** (never emit a benchmark computed from fewer than the threshold of
  orgs), for CPL/conversion/lead-to-SQL/SQL-to-meeting/meeting-to-proposal/proposal-to-win/avg MRR/CAC/
  time-to-close, plus the requesting tenant's percentile/quartile.
- **CompetitiveService (CINT)** — competitors CRUD + share-of-voice across tracked keywords from our own
  ranking data. External competitor monitoring (PPC/ads/backlinks/content/maps/reviews/social) is Planned
  (needs SERP/Ahrefs/SEMrush providers).
- **GrowthScoreService (GSCORE)** — composite 0–100 from sub-scores computed off each module (SEO,
  local, website, authority, AI-visibility, paid, content, conversion, automation, sales-velocity), a
  weighted overall, prioritized recommendations (surface the weakest levers), and a persisted snapshot
  for trend.
- **OmnichannelService (OMNI)** — per-channel performance rollup (SEO/Ads/Email/SMS/Content/Retargeting/
  AI-search where data exists) + the unified prospect journey (a contact's cross-channel activities).

## API endpoints

`/analytics/*` (Inertia), all `can:analytics.view` + `entitlement:analytics`, tenant-scoped: dashboard,
attribution, growth-score, benchmarks, competitors (+ store/update/destroy `can:analytics.competitors.
manage`), calls (+ number provision, log/score `can:analytics.calls.manage`), experiments (+ store/
variant/record/conclude `can:analytics.experiments.manage`), omnichannel.

## UI pages & components

Sidebar **Analytics** group; Inertia pages: dashboard, attribution, growth-score, benchmarks,
competitors, calls, experiments, omnichannel. Reuse cards/tables/badges/dialogs; KPI tiles, a growth-
score gauge with sub-score bars + recommendations, benchmark percentile bars, experiment variant tables.

## Integrations

Call-tracking provider (CallRail/Twilio), web analytics (sessions/users/traffic — GA/GSC), and
competitor-data providers (SERP/Ahrefs/SEMrush) are consumed where connected; when disconnected the
dependent features degrade to Planned/Partial and the UI shows an empty state (never fabricated numbers).

## Background jobs

`analytics:snapshot-growth-scores` scheduler persists a daily `growth_scores` snapshot per tenant for
trend. Idempotent (one snapshot per org per day).

## Business rules & validation

Money in minor units; every KPI divisor guarded (reuse `AdKpi`). Benchmarks enforce the k-anonymity
cohort floor before returning any aggregate. Growth-score sub-scores degrade gracefully to a neutral
baseline with a note when a module has no data (never invented). Experiment lift is relative to the
control variant.

## Audit requirements

`analytics.call.logged`, `analytics.experiment.created/concluded`, `analytics.competitor.created/
deleted`, `analytics.growth_score.computed` via AuditLogger.

## Automated tests

AnalyticsService funnel/ad/revenue rollups on seeded pipeline; AttributionService first/last/multi-touch

- CAC + ROI; CallTrackingService fixture provision + attribution + scoring + conversion; ExperimentService
  rate/lift/winner; BenchmarkService cross-tenant aggregate **suppressed below the cohort floor** + percentile;
  CompetitiveService share-of-voice; GrowthScoreService composite + recommendations + snapshot/trend;
  OmnichannelService rollup + journey; RBAC (view vs manage), `analytics` entitlement gating (Growth blocked /
  Professional allowed), tenant isolation.

## Acceptance criteria

- Dashboard, attribution, growth score and omnichannel compute from real seeded data — no fabricated
  metrics; empty states where a source is absent.
- Benchmark data layer never leaks a raw tenant value and suppresses sub-cohort aggregates.
- Every route `can:`- + `entitlement:analytics`-gated; tenant-isolated. Full gate green; honest register
  (computed = Tested; provider/external = Implemented-untested or Planned; partial-source = Partial);
  Module Completion Report + register update; §65 cycle report.
