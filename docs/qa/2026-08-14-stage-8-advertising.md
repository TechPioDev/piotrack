# Module Completion Report — Advertising (Stage 8)

Date: 2026-08-14
Spec: [docs/specs/stage-8-advertising.md](../specs/stage-8-advertising.md)
ADR: [ADR-0006 — Ad platform abstraction](../architecture/adr/ADR-0006-ad-platform-abstraction.md)
Scope: PPC-001…025, LIAD-001…017, META-001…011, RETG-001…017 (70 features)

## Status summary

| Area                                                                                    | Result                                  |
| --------------------------------------------------------------------------------------- | --------------------------------------- |
| Campaign / ad-group / ad / keyword structure (+ negative keywords)                      | Tested (PPC/LIAD/META structure)        |
| Budgets + campaign status lifecycle (draft/active/paused/ended)                         | Tested                                  |
| KPI computation (CTR/CPC/CPA/CPL/ROAS/conv-rate, divide-by-zero guarded)                | Tested (PPC-019/021…024)                |
| Metrics pipeline (daily snapshots, idempotent, rollups)                                 | Tested on fixture driver                |
| B2B (LinkedIn) + Meta targeting via JSON                                                | Tested (LIAD-003…012, META-004/005)     |
| Retargeting audiences (list/funnel/behavior/all) + conversion exclusions + segmentation | Tested (RETG-008/011…017)               |
| Live platform delivery + real spend/metrics + campaign push                             | Implemented — untested (no credentials) |
| Custom-audience push, lead-gen form sync, AI bidding, video retargeting                 | Partial/Planned                         |

Per ADR-0006, campaign structure, KPI math, and retargeting-audience building are computed **in-house
and Tested**; the metrics pipeline is Tested on the **fixture** driver, while the real Google/
LinkedIn/Meta drivers are real code labelled _Implemented (untested — requires credentials)_ (§38).

## Architecture delivered

- **Data model** (6 tenant-scoped tables, money in minor units): `ad_campaigns`, `ad_groups`, `ads`,
  `ad_keywords` (incl. `is_negative`), `ad_metrics` (unique per campaign+date), `retargeting_audiences`.
- **`AdKpi`** — pure KPI value object (CTR/CPC/CPA/CPL/ROAS/conversion-rate, every divisor guarded).
- **`AdCampaignService`** — create/update/status (activation requires an ad group with an ad) + push
  via provider. **`AdMetricsService`** — pull daily metrics via `AdProvider` → idempotent upsert →
  per-campaign + org-wide KPI rollups. **`RetargetingService`** — resolve members from source
  (marketing list / funnel stage / behavior rules / all contacts), exclude converted customers,
  recount, and emit a hashed-email sync payload.
- **`AdProvider`** (ADR-0006): `FixtureAdProvider` (tested, deterministic budget-scaled metrics) +
  `GoogleAds`/`LinkedInAds`/`MetaAds` drivers (real REST calls, untested); `AdProviderManager`
  resolves fixture vs per-platform live driver via `config/advertising.php`.
- **`RefreshAdMetrics`** queued job + `ads:refresh-metrics` daily scheduler (per-tenant).
- **RBAC + entitlement**: 3 `ads.*` permissions (view / campaigns.manage / retargeting.manage) mapped
  across roles; the whole module gated by `entitlement:advertising` (new `Feature::Advertising`,
  granted Professional/Agency/Enterprise).
- **Controllers + 18 routes** (`/ads/*`, `can:`- + entitlement-gated, tenant-scoped) + audit events.
- **UI**: sidebar **Advertising** group; 4 Inertia pages (dashboard with KPI rollup, campaigns index,
  campaign detail with ad groups/ads/keywords + metrics + KPIs + status controls, retargeting).

## Automated test results

- **Pest: 273/273 PASS** (924 assertions) — +17 advertising tests across 4 suites:
    - KPI (2): CTR/CPC/CPA/ROAS/conv-rate math + `toArray()` formatting; divide-by-zero guard.
    - Campaign (6): create + audit; activation blocked without an ad-group-with-ad then allowed;
      ad group/ad/negative-keyword nesting; **idempotent metrics refresh** + KPI rollup; controller refresh.
    - Retargeting (5): list audience excluding converted; inclusion when off; funnel-stage audience;
      hashed-email payload (normalized); controller create + member count.
    - Access (5): viewer read-vs-manage; retargeting-manage gating; `advertising` feature gating
      (Growth blocked, Professional allowed); tenant isolation.
- PHPStan L6: 0 errors · Pint PASS · Prettier PASS · ESLint PASS · tsc PASS · `npm run build` PASS.

## Manual QA (browser, http://localhost:8734)

Logged in as an Owner on the **Professional** plan (`advertising` entitled):

- Campaigns page renders; `entitlement:advertising` gate confirmed (Professional passes).
- **Campaign detail** (the centerpiece) rendered the real fixture-metrics pipeline: KPI cards **Spend
  $2,531.05 · Impressions 77,812 · Clicks 2,141 · CTR 2.75% · Conversions 142 · CPA $17.82 · ROAS
  19.36x**; the ad group with its ad and keywords (the "free" keyword correctly badged **Negative**);
  and a 30-day metrics table with money-formatted spend/revenue.
- **Dashboard** rendered the org-level KPI rollup + a campaigns table. Retargeting page renders.

## Defects discovered & fixed

- **Metrics idempotency bug**: the `date` cast stored `Y-m-d 00:00:00` but `updateOrCreate` searched
  with the raw `Y-m-d` string, so the match failed and a re-run hit the unique constraint — normalized
  the lookup key to `Carbon::parse(...)->startOfDay()`. Caught by the idempotent-refresh test.
- PHPStan: nested Collection maps in `CampaignController::show` tripped generic invariance → typed the
  closures + `->all()`; redundant `??` on a guaranteed offset in `AdMetricsService`.

## Deferred (tracked in register, not dropped)

- Live Google/Microsoft/LinkedIn/Meta delivery + real spend metrics + campaign push (credentials +
  INTG OAuth); custom-audience push + lead-gen form sync; AI-assisted bidding; video/YouTube
  retargeting; SMS re-engagement wired from a retargeting audience (Stage 6 SMS engine); call tracking
  (Stage 11 CALL); landing pages (Marketing Stage 6).

## Completion

**APPROVED — Stage 8 (Advertising) gate passed.** piotrack now structures paid campaigns across
Google/Microsoft/LinkedIn/Meta with ad groups, ads, keywords (+ negatives) and budgets; computes CTR/
CPC/CPA/ROAS from a tested metrics pipeline; and builds cross-channel retargeting audiences from
first-party CRM data with conversion exclusions — all tenant-scoped, RBAC- and plan-gated. Next per
the phase plan: Stage 9 — Content & authority (CONT / SOC / VID / POD / REP / DPR / LINK).
