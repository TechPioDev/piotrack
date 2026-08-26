# Module Specification — Advertising (PPC / LIAD / META / RETG)

> Stage 8. Complete before coding (Master Prompt §58–59). Depends on CRM+Marketing (Stages 5–6),
> INTG + entitlements (Stage 4/3), RBAC/audit (Stage 2). ADR-0006 governs ad-platform data sources.

## Purpose

The paid-acquisition layer: plan, structure, budget and analyze ad campaigns across Google/Microsoft
search, LinkedIn, and Meta, and drive cross-channel **retargeting** from first-party CRM data. Half of
this is computed in-house and fully tested (campaign/ad-group/ad/keyword structure, budgets, KPI math,
retargeting audiences); live spend, platform push and real metrics are real code behind an
`AdProvider` abstraction, untested here for lack of ad-account credentials (ADR-0006, §38).

## Users & roles

Marketing Manager (full), Marketing User (create/edit), Analyst/Sales/Viewer (view). Permissions:

- `ads.view` — view ad surfaces + analytics.
- `ads.campaigns.manage` — create/edit campaigns, ad groups, ads, keywords, budgets; pause/activate; sync.
- `ads.retargeting.manage` — create/edit retargeting audiences.

## Feature IDs (register rows in scope)

- **PPC-001…025** — search advertising + PPC management. **Built + tested**: campaign types
  (keyword/brand/competitor/service/location/vertical/high-intent via `type`), keyword selection +
  negative keywords + match types, budget allocation, conversion tracking fields, KPI-based
  optimization signals (CPL/ROAS/quality proxy), search-term/negative management. Live Google/Microsoft
  push + real metrics + AI-assisted bidding → **Partial/Planned** (needs Ads API; call tracking →
  Stage 11 CALL; landing pages → Marketing Stage 6).
- **LIAD-001…017** — LinkedIn ads: campaigns + B2B targeting (job title/company size/industry/
  seniority/account/role), retargeting, ABM, lead-gen forms, content promotion. **Built + tested** as
  campaign structure + targeting (JSON) + KPI; live delivery + lead-gen form sync → Partial/Planned.
- **META-001…011** — Meta/Facebook ads: campaigns, retargeting, awareness/BOFU/proof/video/lead-gen,
  location/vertical targeting, multi-platform retargeting. **Built + tested** as structure + targeting
    - KPI; live delivery → Partial/Planned.
- **RETG-001…017** — retargeting engine: audiences from website/search/display/social/video/email/SMS
  behavior, cross-channel, behavior/funnel-stage/BOFU/geo/account-level, **conversion exclusions**,
  audience segmentation. **Built + tested** for audience building from CRM lists/behavior/funnel +
  exclusions + segmentation + per-platform sync payloads; the actual platform push is Partial/Planned.

Full per-ID status lands in the register at gate time (honest §38).

## Subscription requirements

- New `Feature::Advertising` gates the whole module (`entitlement:advertising`), granted on
  Professional/Agency/Enterprise (paid-ads is a premium managed capability). Starter/Growth → blocked.
- Usage: none new; spend is tracked but not billed by us.

## Database entities (all tenant-scoped via `BelongsToTenant`; money in minor units)

- `ad_campaigns` (id, org, platform[google_search|microsoft|linkedin|meta|youtube], name, type,
  objective[leads|awareness|traffic|conversions], status[draft|active|paused|ended], daily_budget,
  total_budget nullable, start_date, end_date nullable, targeting json, external_id nullable).
- `ad_groups` (id, org, ad_campaign_id, name, status, bid_strategy, bid_amount, targeting json).
- `ads` (id, org, ad_group_id, name, headline, body, cta, destination_url, status).
- `ad_keywords` (id, org, ad_group_id, phrase, match_type[broad|phrase|exact], is_negative).
- `ad_metrics` (id, org, ad_campaign_id, date, impressions, clicks, spend, conversions, revenue) —
  daily snapshots; KPIs computed on read.
- `retargeting_audiences` (id, org, name, source[list|behavior|funnel_stage|all_contacts],
  marketing_list_id nullable, rules json, platforms json, exclude_converted, member_count).

Indexes on (org, …); `ad_metrics` on (org, ad_campaign_id, date).

## Services (in-house, tested)

- `AdKpi::from(impressions, clicks, spend, conversions, revenue)` → CTR/CPC/CPA/CPL/ROAS/conv-rate
  (pure). `AdCampaignService` — create/update/activate/pause/end; budget; sync (via provider).
  `AdMetricsService` — pull via `AdProvider` (fixture tested) → persist `ad_metrics`; rollup KPIs over
  a window. `RetargetingService` — resolve audience members from source (list = marketing list
  members; funnel_stage = contacts in a lifecycle stage; behavior = rules; all_contacts), apply
  `exclude_converted` (drop customers), recount; build a per-platform sync payload.
- `AdProvider` (ADR-0006): `FixtureAdProvider` (tested) + `GoogleAds`/`LinkedInAds`/`MetaAds` (real,
  untested) resolved by platform; `AdProviderManager` + `config/advertising.php`, container-bound.

## API / endpoints (authenticated, `/ads/*`, web, `organization` + `entitlement:advertising` + `can:`)

Dashboard (KPI rollup); campaigns CRUD + `POST /ads/campaigns/{c}/status` (activate/pause) +
`/refresh-metrics`; ad groups + ads + keywords CRUD nested; retargeting audiences CRUD +
`POST /ads/retargeting/{a}/rebuild`.

## UI pages & components

Sidebar **Advertising** group: Dashboard, Campaigns, Retargeting. Reuse design-system primitives;
a KPI dashboard (spend/impressions/clicks/conversions/CTR/CPC/CPA/ROAS cards), a campaigns table +
detail (ad groups → ads + keywords, metrics table + KPIs, budget, status toggle, refresh-metrics), a
retargeting audiences list with source + member count + platforms + exclusions.

## Integrations

Google/Microsoft/LinkedIn/Meta ad accounts via INTG OAuth connectors (Planned; coming-soon). Live
delivery + metrics via ADR-0006 drivers (config + keys). Retargeting audience push → those connectors.

## Background jobs

`RefreshAdMetrics` (queued, per campaign) + scheduler `ads:refresh-metrics` (daily) — re-establish
tenant context; idempotent per (campaign, date). Fixture driver makes this runnable now.

## Business rules & validation

Budgets ≥ 0 (minor units). A campaign can't activate without at least one ad group + ad (soft rule).
Metrics upsert is idempotent per (campaign, date). Retargeting `exclude_converted` removes contacts
with lifecycle `customer`. KPI math guards divide-by-zero (0 when denominator 0).

## Audit requirements

`ads.campaign.created|updated|status_changed`, `ads.metrics.refreshed`, `ads.retargeting.created|rebuilt`.

## Analytics events

Per-campaign daily metrics + rollups; per-audience member counts. Surfaced on the ads dashboard.

## Automated tests

Campaign lifecycle (create/activate/pause/end) + validation + audit; ad group/ad/keyword nesting +
negative keywords; `AdMetricsService` fixture pull persists snapshots + idempotent; `AdKpi` math
(CTR/CPC/CPA/CPL/ROAS + divide-by-zero); retargeting build from list/funnel + `exclude_converted` +
recount + segmentation; RBAC matrix (view vs campaigns.manage vs retargeting.manage); entitlement
(`advertising`) gating; tenant isolation on every entity.

## Manual QA checklist

Create a campaign → add an ad group + ad + a negative keyword; refresh metrics → KPI cards populate;
build a retargeting audience from a marketing list with conversion exclusion → member count; pause a
campaign. Responsive + empty states.

## Acceptance criteria

- [ ] Campaign/ad-group/ad/keyword structure + budgets tenant-scoped; every route `can:`- + entitlement-gated.
- [ ] KPI math + retargeting audience building computed + tested.
- [ ] Metrics pipeline tested on the fixture driver; real platform drivers present + labelled untested.
- [ ] Full gate green; honest §38 register; Module Completion Report filed.
