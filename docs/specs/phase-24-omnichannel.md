# Phase 24 — Omnichannel Marketing close-out

Register target (8 rows): OMNI-002 (Google Maps), OMNI-004 (Bing/Microsoft Ads),
OMNI-005 (LinkedIn), OMNI-006 (Facebook), OMNI-007 (X/Twitter), OMNI-008 (YouTube),
OMNI-011 (Video), OMNI-015 (Public relations).

## The gap, honestly stated

The module's claim is *every channel coordinated in one platform around one prospect
record*. OMNI-016/017/018 (the unified record, identity and revenue trail) are Tested;
so are the channels the platform runs end-to-end (SEO, Google Ads, Email, SMS, Content,
Retargeting, AI Search). The remaining eight rows were deferred as "distinct measured
channels need their platform connectors" — but a survey shows the platform ALREADY
coordinates each of them through existing tested modules; what is missing is only that
the omnichannel rollup never surfaced them as distinct channels:

- **LinkedIn / Facebook / X / YouTube** — the social pipeline schedules and publishes
  per network (`social_posts.channel` ∈ linkedin/facebook/x/youtube, Stage 8).
- **Microsoft/Bing Ads** — the advertising module manages campaigns per platform
  (`ad_campaigns.platform` ∈ google_search/microsoft/linkedin/meta/youtube) with
  per-campaign AdMetric rows.
- **Google Maps** — Local SEO coordinates the GBP surface: `seo_locations.gbp_place_id`,
  per-location citations (`citations.status = consistent`), geo keywords.
- **Video** — the content module has video formats (video/webinar) as first-class
  content types.
- **Public relations** — Phase 17's earned-media pipeline: outreach placements
  (`outreach_prospects.status = won` + placement_url) and authority assets.

## Design

**`OmnichannelService::channels()` becomes the register's channel list** — 15 rows,
one per OMNI-001..015, each computed from the tenant's own records with a stable key,
an active flag, one primary metric, an optional `detail` line, and an `href` into the
module that manages the channel (the module pages ARE the drill-downs — that is the
coordination):

| key | metric (value) | detail | href |
|---|---|---|---|
| seo | tracked keywords | — | /seo/keywords |
| google_maps | locations on Maps (gbp_place_id set) | N consistent citations | /seo/local |
| google_ads | ad clicks (platform google_search) | N campaigns | /ads/campaigns |
| microsoft_ads | ad clicks (platform microsoft) | N campaigns | /ads/campaigns |
| linkedin | posts published | N ad clicks (platform linkedin) | /content/social |
| facebook | posts published | N ad clicks (platform meta) | /content/social |
| x | posts published | — | /content/social |
| youtube | posts published | N ad clicks (platform youtube) | /content/social |
| email | sent | — | /marketing/campaigns |
| sms | sent | — | /marketing/campaigns |
| video | video pieces published (video/webinar) | — | /content/pieces |
| content | published pieces | — | /content/pieces |
| retargeting | audience members | — | /ads/retargeting |
| ai_search | AI mentions | — | /ai/visibility |
| pr | placements earned | N authority assets | /content/outreach |

The aggregate `ads`/`social` rows are replaced by the per-network rows (Google Ads was
already its own register row; social splits into its four networks). The honesty rule
stays load-bearing: a channel with no data is inactive with a zero metric — never faked
— and per-network ad clicks come from the tenant's AdMetric rows, not invented network
insights.

**UI** — analytics/omnichannel.tsx renders the detail line, links each active card to
its module page, and carries an explicit note that these numbers are measured from
platform records; live network-side insights (GBP views, network impressions) still
need the INTG connectors.

**Stays honest** — nothing else moves: the vendor connectors themselves (INTG rows),
live per-network insight pulls, and the Meta Ads module's API-bound rows are untouched.

## Tests (tests/Feature/Qa/OmnichannelCoverageTest.php)

1. channels() returns exactly the 15 register channels, stably keyed, all inactive
   with zero values on an empty org (never faked).
2. Social networks measure their own published posts (linkedin/x seeded → their rows
   active with correct counts; facebook/youtube stay inactive) and another tenant's
   posts never leak in.
3. Ad platforms split: google_search vs microsoft campaigns + metrics land on their own
   rows (clicks + campaign detail).
4. Maps, PR and Video channels compute from GBP locations + consistent citations, won
   placements + authority assets, and published video pieces.
5. The omnichannel page renders the full rollup with hrefs, permission-checked.

Also updated: the pinned expectations in GrowthScoreAccessTest (count 8 → 15,
`ads` → `google_ads`).
