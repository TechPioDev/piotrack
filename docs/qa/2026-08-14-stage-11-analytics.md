# Module Completion Report — Analytics (Stage 11)

Date: 2026-08-14
Spec: [docs/specs/stage-11-analytics.md](../specs/stage-11-analytics.md)
ADR: none new (call-tracking provider follows the ADR-0003…0007 abstraction pattern)
Scope: ANLY-001…036, ATTR-001…017, CALL-001…011, CRO-001…017, BENCH-001…018, CINT-001…013,
GSCORE-001…013, OMNI-001…018 (143 features — the largest stage to date)

## Status summary

| Area                                                                        | Result                                          |
| --------------------------------------------------------------------------- | ----------------------------------------------- |
| Acquisition funnel (leads/MQL/SQL/meetings/opportunities/pipeline/won/lost) | Tested (ANLY-024…032)                           |
| Paid-media KPIs (impressions/clicks/CTR/CPC/spend/conversions/CPA/ROAS)     | Tested (ANLY-016…023)                           |
| SEO visibility + keyword position summary                                   | Tested (ANLY-009…011/015)                       |
| Revenue (MRR/ARR/contract value/LTV from won deals)                         | Tested (ANLY-033…036)                           |
| First/last/linear multi-touch attribution + channel/campaign/sales rollups  | Tested (ATTR-001…005/012)                       |
| CAC + marketing ROI + revenue attribution                                   | Tested (ATTR-013…017)                           |
| Call tracking: dynamic numbers, source attribution, scoring, conversion     | Tested on fixture driver (CALL-001/002/006…011) |
| CRO experiments: 9 test types, conversion rate, lift, winner                | Tested (CRO-001…009/017)                        |
| Anonymized peer benchmarks + percentile, k-anonymity suppression            | Tested (BENCH-001/002/006/007/010…012/017/018)  |
| MSP Growth Score: 10 sub-scores, weighted composite, recommendations, trend | Tested (GSCORE-001…013)                         |
| Omnichannel per-channel rollup + unified prospect journey                   | Tested (OMNI-001/003/009/010/012…014/016…018)   |
| Share of voice / market share of search                                     | Tested (CINT-010/012)                           |
| Traffic-source splits, organic clicks, keyword/landing-page attribution     | Partial — needs GA4/GSC + tracking pixel        |
| Call recordings/transcription/AI summaries, heatmaps, competitor monitoring | Planned — external providers                    |

Per §38, everything computed in-house from real tenant rows is **Tested**; call tracking is Tested on
the **fixture** driver with the live CallRail driver present but untested (no credentials); anything
depending on a web-analytics pixel, SERP/backlink vendor or behaviour-analytics provider is honestly
**Partial** or **Planned**. No metric is fabricated anywhere: a module with no data returns null/zero
and the UI renders an empty state.

## Architecture delivered

- **Data model** (6 new tenant-scoped tables): `call_tracking_numbers`, `calls`, `experiments`,
  `experiment_variants`, `competitors`, `growth_scores` (unique per organization + day). Every other
  analytic reads existing Stage 3–10 tables — no duplicated storage.
- **`AnalyticsService`** — funnel, advertising (reusing the divisor-guarded `AdKpi`), SEO, revenue and
  lead-source breakdown, all under the tenant scope.
- **`AttributionService`** — touchpoint chain per contact (acquisition source + activities), first/last/
  linear multi-touch, won-revenue rollups by channel/campaign/owner, CAC, marketing ROI.
- **`CallTrackingService`** + **`CallProvider`** abstraction — `FixtureCallProvider` (deterministic,
  tested) + `CallRailProvider` (real, untested); numbers carry source/campaign so calls inherit
  attribution; duration-based 0–100 scoring with a qualification threshold and conversion bonus.
- **`ExperimentService`** — A/B engine across 9 CRO test types; per-variant conversion rate, lift vs
  control, leader selection, conclude-with-winner; rejects conversions > impressions.
- **`BenchmarkService`** — the proprietary data layer: deliberately cross-tenant aggregates
  (`withoutGlobalScope('tenant')`) returning **only** peer median/average/top-quartile + the requesting
  tenant's own value and percentile, gated by a configurable **k-anonymity minimum-cohort floor**
  (`analytics.benchmark_min_cohort`, default 3); sub-cohort metrics are suppressed entirely.
- **`CompetitiveService`** — position-weighted share of voice / market share of search from our own
  ranking data vs captured competitor positions.
- **`GrowthScoreService`** — the flagship 0–100 composite over ten weighted sub-scores, each computed
  from its module's real data; **null (not zero) when a module has no data**, with weights renormalized
  across present sub-scores; prioritized recommendations; daily snapshots for trend.
- **`OmnichannelService`** — eight-channel performance rollup + the unified prospect journey (shares the
  attribution touchpoint model, so the revenue trail is one dataset).
- **`analytics:snapshot-growth-scores`** scheduler (daily 02:00, per-tenant, idempotent per org+day).
- **RBAC + entitlement**: 4 `analytics.*` permissions (view / calls / experiments / competitors) mapped
  across roles; module gated by `entitlement:analytics` (new `Feature::Analytics`, Professional+).
- **Controllers + 21 routes** (`/analytics/*`, `can:`- + entitlement-gated, tenant-scoped) + audit events.
- **UI**: sidebar **Analytics** group; 8 Inertia pages (dashboard, attribution, growth-score, benchmarks,
  competitors, calls, experiments, omnichannel) with KPI tiles, a growth-score gauge + sub-score bars,
  benchmark percentile bars with explicit suppression notices, and experiment variant tables.

## Automated test results

- **Pest: 352/352 PASS** (1122 assertions) — +40 analytics tests across 5 suites:
    - Dashboard (6): funnel from real pipeline rows; ad KPI math; zero-spend guard; SEO summary; won-only
      revenue; entitled render.
    - Attribution (6): first/last touch; multi-touch credit totals 1.0; unsourced → direct; channel +
      campaign rollups exclude open deals; CAC + ROI; no-spend/no-customer guards.
    - Calls + experiments (9): fixture provisioning; source/campaign inheritance + duration scoring;
      missed-call zero + conversion bonus; controller paths; conversion rate/lift/winner; conclude stamps
      winner; conversions > impressions rejected; zero-impression lift guard.
    - Benchmarks (6): **suppressed below the k-anonymity floor**; aggregate once the cohort is large
      enough; percentile without exposing peer values (asserted on the exact returned key set); all
      metrics suppressed at a higher floor; empty orgs excluded from the cohort; avg-MRR benchmark.
    - Growth score / competitive / omnichannel / access (13): null sub-scores for unmeasured modules;
      zero overall with no data; renormalized weighting; recommendations rank weakest + flag unmeasured;
      idempotent daily snapshot; per-tenant scheduled command; share of voice + no-data case; channel
      active flags; unified journey; viewer read-vs-manage; `analytics` gating (Growth 403 / Professional
      200); tenant isolation (cross-tenant destroy 404s).
- PHPStan L6: 0 errors · Pint PASS · Prettier PASS · ESLint PASS · tsc PASS · `npm run build` PASS.

## Manual QA

A Professional organization was seeded with real cross-module data (4 contacts across lifecycle stages,
1 won + 1 open deal, 3 tracked keywords + 1 competitor ranking, an ad campaign with a day of metrics, a
tracked call, and a running headline experiment), then every analytic was executed against it and the
output checked by hand:

- **Funnel** `leads 4 · mqls 1 · sqls 2 · opportunities 1 · qualified_pipeline $8,000 · closed_won 1` —
  matches the seed exactly; the open deal alone forms the qualified pipeline.
- **Advertising** `CTR 7.5%` (1,800/24,000), `CPC $1.33` (240,000/1,800 minor units), `ROAS 5.0×`
  (1,200,000/240,000) — hand-recomputed and correct.
- **SEO** `3 tracked · 1 top-three · 2 page-one · visibility 86` — the visibility index is the mean of
  (101−2), (101−7), (101−35) = 86.33 → 86.
- **Revenue** `MRR $1,000 · ARR $12,000 · LTV $36,000` from the won deal only (the open deal excluded).
- **Attribution** `organic $12,000 · CAC $2,400 · ROI 5.0×` — CAC = spend ÷ 1 customer.
- **Growth Score `66/100`** with breakdown `seo 67 · paid 100 · conversion 50 · sales_velocity 50` and
  **six sub-scores null** (local, website, authority, ai_visibility, content, automation) because those
  modules have no data — rendered as "No data", never as zero. Recommendations correctly ranked the
  weakest measured areas first (conversion 50, sales velocity 50, then SEO 67).
- **Share of voice** `72.75% us / 27.25% rival-msp.com` — our 259 visibility points vs the competitor's
  97 (position 4 → 101−4).
- **Omnichannel** marked only SEO and Paid Ads active; the six channels with no data were reported
  inactive with a zero metric rather than fabricated.
- **Benchmarks (k-anonymity)** with 3 organizations in the database and a floor of 3: exactly 3 of the 7
  metrics were emitted (`cpl`, `conversion_rate`, `lead_to_sql`); `avg_mrr`, `cac`, `sql_to_meeting` and
  `time_to_close` were **suppressed** because fewer than 3 organizations contribute those values. Only
  aggregates plus the requesting tenant's own value/percentile were returned.

Browser rendering of the authenticated pages is covered by the feature tests (Inertia 200 for a
Professional org, 403 for Growth, 403 on the viewer's snapshot attempt) plus clean tsc/eslint on all 8
page components. Browser login was not performed because entering a password to authenticate is outside
the assistant's allowed actions.

## Defects discovered & fixed

- **Growth-score snapshot violated its own uniqueness constraint**: `computed_on` is a `date`-cast
  column (stored as midnight), but `updateOrCreate` looked it up with a raw `Y-m-d` string, so the match
  never hit and the second snapshot of the same day attempted an insert →
  `UniqueConstraintViolationException`. Normalized the lookup key to `now()->startOfDay()`. Caught by
  the idempotency test. (Same class of bug as the Stage 8 `ad_metrics` date-cast defect — the fix is now
  applied consistently.)
- **Ungated write endpoint**: the growth-score snapshot POST initially sat inside the `can:analytics.view`
  group, so a Viewer could write tenant rows. Added `analytics.growth-score.manage` (granted to
  Owner/Admin/managers/Analyst), moved the route behind it, gated the UI button, and added a test
  asserting a viewer gets 403 while an owner succeeds. Caught during UI review.
- Pint: import ordering / fully-qualified types in `Experiment`.

## Deferred (tracked in register, not dropped)

- Sessions/users/traffic-source splits, organic clicks, map rankings (ANLY) — GA4 / Google Search
  Console / GBP connectors.
- Keyword, landing-page, content and ad-level revenue attribution (ATTR) — tracking pixel + click-id
  join with the ad platforms.
- Call recordings, transcription, AI summaries (CALL) — CallRail/Twilio credentials + INTG webhook.
- Heatmaps, session behaviour analysis, bounce-rate optimization (CRO) — behaviour-analytics provider.
- CPC by service/region, SEO conversion, best-performing keyword/offer/vertical/ad benchmarks (BENCH) —
  richer taxonomy + a larger contributing cohort than the k-anonymity floor permits today.
- Competitor PPC/ad/backlink/content/maps/review/AI/social monitoring, competitive alerts (CINT) —
  SERP/Ahrefs/Semrush/social providers.
- Google Maps, Bing Ads, X, YouTube, PR as distinct measured channels (OMNI) — platform connectors.

## Completion

**APPROVED — Stage 11 (Analytics) gate passed.** piotrack now closes the loop: a unified metrics
dashboard over the whole funnel, first/last/multi-touch revenue attribution with CAC and ROI, call
tracking with source attribution and scoring, a CRO experiment engine, an anonymized peer-benchmark data
layer with k-anonymity protection, competitive share of voice, an omnichannel view with a unified
prospect journey, and the flagship MSP Growth Score with prioritized recommendations and trend — all
tenant-scoped, RBAC- and plan-gated (Professional+). Next per the phase plan: Stage 12 — AI (AISA /
AIVIS / AIPF).
