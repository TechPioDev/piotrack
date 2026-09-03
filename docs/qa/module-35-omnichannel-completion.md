# Module Completion Report — Omnichannel Marketing, Phase 24

**Date:** 4 September 2026 · **Module:** Omnichannel Marketing · **Register:** 18/18 Tested (**100%**), was 10/18 (56%)

## What shipped

The module's claim is *every channel coordinated in one platform around one prospect
record*. The unified record, identity and revenue trail (OMNI-016/017/018) and the
platform-run channels were already Tested; the eight remaining rows had been deferred
as "distinct measured channels need their platform connectors" — but the survey showed
the platform ALREADY coordinates each of them through existing tested modules. The gap
was only that the omnichannel rollup never surfaced them as distinct channels.

**[OmnichannelService](../../app/Services/Analytics/OmnichannelService.php)::channels()
is now the register's channel list** — 15 rows, one per OMNI-001..015, each computed
from the tenant's own records with a stable key, active flag, primary metric, secondary
detail, and a link into the module that manages the channel (the module pages are the
drill-downs — that is the coordination):

- **Google Maps (002)** — the GBP surface Local SEO runs: locations with
  `gbp_place_id`, consistent citations → /seo/local.
- **Microsoft/Bing Ads (004)** — the advertising module already manages
  `platform=microsoft` campaigns; clicks come from recorded AdMetric rows, the same
  fixture-provider discipline Google Ads closed under.
- **LinkedIn (005) / Facebook (006) / X (007) / YouTube (008)** — published posts per
  network from the Stage 8 pipeline as the primary metric; the network's ad clicks
  (linkedin/meta/youtube platforms) ride the detail line rather than posing as posts.
- **Video (011)** — published video-format content pieces (video/webinar).
- **Public relations (015)** — the Phase 17 earned-media pipeline: placements actually
  **won** (never pitched-only), authority assets on the detail line.

The aggregate `ads`/`social` rows were replaced by the per-network rows; the pinned
expectations in GrowthScoreAccessTest moved with them (8 → 15 channels). The honesty
rule stays load-bearing and is now test-pinned in both directions: a channel with no
data is inactive with a zero metric, and per-network numbers are what the *platform*
did on that channel — the UI states explicitly that live network-side insights (Maps
views, network impressions) appear only once the platform connector is linked.

## Honest scoping

Nothing in this module is deferred any more. The vendor connectors themselves remain
register rows in **Integration Framework** and **Meta Advertising**, which stay
API/credential-gated — this module never claimed to be them.

## Gate evidence

- Pest: **933 passed / 4,361 assertions** (+5:
  [OmnichannelCoverageTest](../../tests/Feature/Qa/OmnichannelCoverageTest.php)).
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
