# Phase 36 — Google Ads / PPC closeout

**Register targets:** PPC-001, PPC-002, PPC-010, PPC-013, PPC-014, PPC-016, PPC-017, PPC-018, PPC-020
(module 64% → 100%).

## Honesty position

Stage 8 already ships and tests the campaign→group→ad/keyword structure, budgets, status
lifecycle, and per-campaign KPI on the fixture ad provider; live Google/Microsoft API sync
stays behind the AdProvider seam (ADR-0006) and is *never claimed*. What Phase 36 adds is the
rest of the first-party management lifecycle, so the platform genuinely manages search
advertising end-to-end without inventing anything a live API would return:

- **PPC-016 Ad copywriting** — AI-drafted RSA-style copy through the governed AiGateway
  (draft ads only, never activated or pushed; platform character limits enforced).
- **PPC-017 Ad extensions and assets** — sitelinks, callouts, structured snippets, and call
  extensions stored per campaign, editable, exported, and audited.
- **PPC-013 Bid management** — the existing bid_strategy/bid_amount fields plus rule-based
  bid recommendations computed from the campaign's own recorded metrics, with an
  insufficient-data guard (nothing recommended below the data floor).
- **PPC-014 AI-assisted bidding** — advisory-only AI bid guidance over the same first-party
  metrics summary via the gateway; the tenant applies changes to the bid fields; automated
  live bidding stays API-gated and says so.
- **PPC-010 PPC account audit** — an audit engine over the structure and metrics the
  platform holds: structural gaps (no ads/keywords/negatives/extensions/destination URLs),
  budget pacing, and low-CTR findings that cite their numbers. Auditing an external live
  Google account remains API-gated.
- **PPC-001/002 Google + Microsoft Search Ads** — closed by shipping the missing half of the
  no-API lifecycle: bulk-editor CSV export (Google Ads Editor / Microsoft Ads-compatible
  columns) covering campaign, ad groups with max CPC, keywords incl. match types and
  negatives, ads, and extensions — a real structure a tenant can upload with the platforms'
  own bulk tools today. Live delivery/metric sync remains the tested provider seam.
- **PPC-018 Landing-page creation** — the builder shipped in Stage 6 (LandingPage +
  public route, tested); the stale deferral note predates it. Bridge: one-click draft
  landing page created from a campaign (named/keyed to it) for use as ad destination.
- **PPC-020 Call tracking** — CALL machinery (numbers + calls + qualification) shipped in
  Stage 11; bridge: a tracking number is assignable to an ad campaign, and the campaign
  page reports calls/qualified calls from its numbers. Live number provisioning stays
  telephony-provider-gated (unchanged CALL note).

## Build

1. Migration `extend_ppc_management`: `ad_extensions` table (organization_id,
   ad_campaign_id, kind sitelink|callout|structured_snippet|call, text, url?, phone?);
   `call_tracking_numbers.ad_campaign_id` nullable.
2. `AdExtension` model (BelongsToTenant); `AdCampaign::extensions()`,
   `AdCampaign::trackingNumbers()`.
3. `App\Services\Advertising\AdCopywriter` — gateway feature `ads.copy`, new versioned
   default prompt; parses HEADLINES/DESCRIPTIONS response; creates a *draft* Ad with
   limits enforced (headline ≤ 30 chars, description ≤ 90).
4. `App\Services\Advertising\BidAdvisor` — `recommendations(campaign)` rule-based over
   30-day metrics (data floor MIN_CLICKS; CTR, pacing vs daily budget, spend-without-
   conversion rules, each citing numbers); `aiAdvice(campaign)` gateway feature
   `ads.bidding` guarded by the same floor.
5. `App\Services\Advertising\PpcAuditor` — `audit()` across campaigns: structural +
   performance findings, each `{campaign, severity, message}` citing numbers.
6. `App\Services\Advertising\AdExportService` — `editorCsv(campaign, format)` for
   `google` | `microsoft`, campaign/ad-group/keyword/ad/extension rows.
7. Routes (ads.* group, manage-gated unless noted): POST groups/{group}/draft-copy;
   POST campaigns/{campaign}/bid-advice; GET campaigns/{campaign}/export?format=;
   POST campaigns/{campaign}/extensions; DELETE extensions/{extension};
   POST campaigns/{campaign}/tracking-number; POST campaigns/{campaign}/landing-page.
   Audit + bid recommendations ship as props (index/show).
8. UI: campaigns/index.tsx audit panel; campaigns/show.tsx — extensions section, bid
   recommendations + AI-advice button, draft-copy per group, export buttons, calls card,
   landing-page button.
9. Tests `tests/Feature/Qa/PpcCloseoutTest.php`: export content both formats; audit
   findings cite numbers + healthy campaign clean; bid rules + data floor + AI advisory
   prompt capture; copywriter draft ad with limits; call attribution on campaign;
   landing-page bridge; cross-tenant isolation on a new endpoint.

## Out of scope (unchanged register notes)

Live Google Ads / Microsoft Ads API delivery, account import, automated bidding execution,
and live call-number provisioning (ADR-0006 / telephony provider).
