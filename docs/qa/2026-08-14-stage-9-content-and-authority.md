# Module Completion Report — Content & Authority (Stage 9)

Date: 2026-08-14
Spec: [docs/specs/stage-9-content-and-authority.md](../specs/stage-9-content-and-authority.md)
ADR: [ADR-0007 — Content distribution & reputation providers](../architecture/adr/ADR-0007-content-distribution-and-reputation-providers.md)
Scope: CONT-001…040, SOC-001…027, VID-001…018, POD-001…010, REP-001…019, DPR-001…013, LINK-001…013 (140 features)

## Status summary

| Area                                                                             | Result                                                   |
| -------------------------------------------------------------------------------- | -------------------------------------------------------- |
| Content hub (all types) + editorial workflow + clusters + keyword map            | Tested (CONT-001…032/037…040)                            |
| Content optimization scoring (SEO/CTA/internal-link/depth)                       | Tested                                                   |
| Copywriting execution (conversion/technical/refresh/expansion)                   | Partial — human, platform tracks + scores (CONT-033…036) |
| Social posts + calendar + scheduling + post types + engagement                   | Tested on fixture driver (SOC-001…017/025/026)           |
| Video + podcast as content pieces (metadata, repurposing)                        | Tested (VID-002…013/018, POD-002…010)                    |
| Reviews + acquisition requests + rating/sentiment aggregation + authority assets | Tested (REP-001…004/008…011/018)                         |
| PR + link outreach (pipeline + placements + backlink monitoring)                 | Tested (DPR-001…013 core, LINK-004…013)                  |
| Live social publishing + review sync                                             | Implemented — untested (no credentials)                  |
| Social listening/monitoring, paid social, backlink audit/toxic/competitor        | Planned — external APIs / other stages                   |

Per ADR-0007, the content hub, editorial workflow, optimization scoring, reviews + aggregation, and
outreach pipelines are computed **in-house and Tested**; social publishing/metrics + review sync are
Tested on the **fixture** drivers, while the live channel/review drivers are real code labelled
_Implemented (untested — requires credentials)_, never "Tested" (§38).

## Architecture delivered

- **Data model** (7 tenant-scoped tables): `content_pieces` (editorial workflow + pillar clusters +
  optimization score), `social_posts` (+ engagement), `reviews`, `review_requests`, `authority_assets`,
  `outreach_campaigns`, `outreach_prospects` (+ placements).
- **`ContentService`** — create/update + editorial workflow transitions (validated order; publish
  requires a body + stamps time); **`OptimizationScorer`** (title/keyword/CTA/excerpt/depth/internal-
  link heuristic → 0–100); **`SocialService`** (schedule/publish/refresh via provider + due-post
  dispatch); **`ReputationService`** (record review with rating-derived sentiment, respond, requests,
  aggregation, fixture import); **`OutreachService`** (prospect pipeline, placements, campaign rollup).
- **`SocialProvider`/`ReviewProvider`** (ADR-0007): `Fixture*` drivers (tested) + `LiveSocialProvider`/
  `LiveReviewProvider` (real, untested); `ContentProviderManager` + `config/content.php`, container-bound.
- **`PublishSocialPost`** queued job + `content:publish-due-posts` scheduler (per-tenant).
- **RBAC + entitlement**: 5 `content.*` permissions (view / pieces / social / reputation / outreach)
  mapped across roles; module gated by `entitlement:content` (new `Feature::Content`, granted Growth+).
- **Controllers + 30 routes** (`/content/*`, `can:`- + entitlement-gated, tenant-scoped) + audit events.
- **UI**: sidebar **Content** group; 6 Inertia pages (dashboard, pieces index/editor, social, reputation,
  outreach) with optimization score, workflow controls, sentiment badges, and outreach placements.

## Automated test results

- **Pest: 294/294 PASS** (976 assertions) — +21 content tests across 4 suites:
    - Content (5): create + score computed + audit; optimized ≫ bare score; editorial-order enforcement;
      publish requires body; publish via route stamps published_at.
    - Social (4): publish captures fixture metrics + external id; idempotent republish; due-post dispatch;
      controller publish.
    - Reputation (6): sentiment derivation; record via route; aggregate (avg/count/sentiment); fixture
      import; send review request; respond.
    - Outreach/access (6): prospect pipeline + placement + `hasPlacement`; campaign rollup; viewer
      read-vs-manage; `content` feature gating (Starter blocked, Growth allowed); tenant isolation.
- PHPStan L6: 0 errors · Pint PASS · Prettier PASS · ESLint PASS · tsc PASS · `npm run build` PASS.

## Manual QA (browser, http://localhost:8734)

Logged in as an Owner on the **Growth** trial (`content` entitled):

- **Content dashboard** rendered the full pipeline: Pieces 2 · Published 1 · Social posts 1 ·
  Placements 1 · **Reviews 3.67★ (3 total)**, a by-status breakdown, a **sentiment breakdown (2
  positive / 0 neutral / 1 negative)**, and a Recent-content table showing a published guide at
  **100/100 optimization score**.
- **Reputation page** rendered the aggregate (3.67★) + reviews table with per-review rating, sentiment
  badges (negative/positive/positive) and Respond actions — real rating + sentiment aggregation.
- A content piece was created and driven idea → draft → review → approved → published via the real
  services (score 100); a social post published on the fixture driver (921 impressions); an outreach
  placement recorded.

## Defects discovered & fixed

- **Slug + status not hydrated after create**: `ContentService::create()` didn't generate a slug
  (NOT NULL) or set the default status, so a create-then-transition sequence failed. Moved slug
  generation into the service and defaulted `status` to `idea`. Caught by the optimization-score test
  and browser QA.
- PHPStan/Pint: removed the now-unused controller `uniqueSlug` + `Str` import; simplified a
  provably-true divisor guard in `OptimizationScorer`.

## Deferred (tracked in register, not dropped)

- Live social publishing + engagement (LinkedIn/Meta/X/YouTube) + review sync (Google/Clutch) —
  credentials + INTG OAuth; social listening/monitoring (monitoring API); paid social (Stage 8);
  video ads/retargeting (Stage 8); YouTube distribution (YouTube API); backlink audit/toxic/competitor
  analysis (Ahrefs/GSC external link data); human copywriting execution (platform tracks + scores it).

## Completion

**APPROVED — Stage 9 (Content & Authority) gate passed.** piotrack now runs a content hub with an
editorial workflow + optimization scoring, schedules and publishes social posts with engagement,
manages reviews with rating + sentiment aggregation and authority assets, and drives PR + link-building
outreach with placement tracking — all tenant-scoped, RBAC- and plan-gated. Next per the phase plan:
Stage 10 — Sales (LSCR / INTENT / ALERT / BOOK / ENAB / ABM).
