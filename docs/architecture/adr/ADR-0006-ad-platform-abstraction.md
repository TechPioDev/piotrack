# ADR-0006 — Ad platform abstraction with a working fixture driver

**Status:** Accepted (2026-08-14) · Enables Stage 8 (Advertising: PPC, LIAD, META, RETG) and Master Prompt §16, §38.

## Context

The advertising modules manage paid campaigns and read back performance across **Google Ads / Microsoft
Ads (search)**, **LinkedIn Ads**, and **Meta/Facebook Ads**, plus cross-channel **retargeting**. Real
data requires each vendor's Ads API — accounts, OAuth apps, developer tokens, ad-account IDs. This
environment has **none of those credentials**, and Master Prompt §38 forbids presenting fabricated
spend/impressions/ROAS as real; we must separate what is Tested from what needs credentials.

By contrast, **campaign structure, budgets, targeting, keywords, KPI math, and retargeting-audience
building from our own CRM data** need no third party — they are computed in-house and built for real.

## Decision

**Campaign structure, budgets, KPI computation, and retargeting audiences live in our own tables and
are fully tested.** `AdKpi` derives CTR/CPC/CPA/CPL/ROAS/conversion-rate from raw counts (pure).
`RetargetingService` builds an audience from our CRM contacts / marketing lists / funnel stages and
applies conversion exclusions — all in-house.

**Platform data + pushing campaigns go through one narrow interface — never a vendor SDK directly:**

- `AdProvider` → `push(AdCampaign)` (create/update on the platform) and `metrics(AdCampaign, days)`
  (`AdMetrics` snapshots: impressions, clicks, spend, conversions, revenue).

Drivers are selected by config (`ADVERTISING_DRIVER`):

1. **`fixture` (default, fully working & tested)** — deterministic metrics derived from a hash of the
   campaign + day. The whole pipeline — daily metric snapshots, KPI rollups, budget pacing, campaign
   status lifecycle, retargeting audiences — runs for real against it. It is the driver used in
   development and tests, and a legitimate "manual / bring-your-own-numbers" mode.

2. **`google_ads` / `linkedin_ads` / `meta_ads` / `microsoft_ads`** — real drivers implementing the
   same interface over the vendor Ads APIs, resolved per campaign `platform`. Real code, but with no
   credentials here they are **not run in tests**; register status _Implemented (untested — requires
   credentials)_, never "Tested." Activated by config + keys, and connected through the **INTG**
   connector framework (the ad accounts are OAuth connectors surfaced as "coming soon" until then).

An `AdProviderManager` resolves the active driver (mirrors PaymentProvider / Messaging / SEO managers).

## Consequences

- The computable half of advertising — campaign/ad-group/ad/keyword structure, budgets, KPI analytics,
  and retargeting-audience building from real CRM data — is real and tested with no ad account.
- Turning on live campaigns + spend data is configuration + credentials + INTG OAuth, not a rewrite (§16).
- We stay honest per §38: structure, KPI math and fixture-driven metrics are Tested; live spend/push is
  labelled untested until credentials and a sandbox exercise it.
