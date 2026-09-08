# Phase 46 — Social Media Management closeout

**Register targets:** SOC-009, SOC-020, SOC-021, SOC-022, SOC-023, SOC-024
(module 77.8% → 100%).

## Position

The P31 notes parked these six as "stays Planned" — but patterns proven since then
close each honestly:

- **SOC-009 Graphic creation** — template-based social graphics generated from REAL
  brand assets: an SVG card (1200×630) built from the post's own text, the brand
  palette and name — downloadable, attachable as the post's media. The P22 style-guide
  PDF precedent: generated collateral from recorded brand facts. Bespoke creative
  stays designer work (BRAND-019, unchanged).
- **SOC-020/021 Community engagement / comment management** — the same log-and-track
  workflow as pasted transcripts and recording URLs: a `social_interactions` queue
  (comments/DMs/mentions logged with network, author, link, body), triaged
  open → replied/dismissed with response-time metrics, unified in an engagement inbox
  beside the platform's own real queues (chat conversations waiting, reviews awaiting
  response). Live comment/DM ingestion stays channel-API-gated, stated in the UI.
- **SOC-022/023/024 Brand monitoring / social listening / reputation monitoring** —
  the P45 provider-seam pattern: a `SocialListeningProvider` contract joins
  ContentProviderManager (`content.listening_provider`, fixture default, UI-labeled
  simulated). Tracked listening terms + the brand name feed it; mentions come back
  with per-network volume and keyword-heuristic sentiment; negative mentions are one
  click from the engagement queue (reputation monitoring = the negative slice, beside
  the already-tested review + AI-answer monitoring). A live listening driver is
  credentials + one class.

## Build

1. Migration: `social_interactions` + `listening_terms`.
2. `SocialListeningProvider` + fixture + manager/config/binding.
3. `SocialEngagementService` (inbox, metrics) + `BrandListeningService`
   (monitor, sentiment heuristics).
4. `SocialGraphicService` (SVG card from post + brand palette).
5. SocialController: engagement/interaction/term endpoints + graphic download;
   social page sections.
6. Tests `tests/Feature/Qa/SocialManagementCloseoutTest.php` (~5).

## Out of scope (unchanged)

Live channel APIs for comment/DM ingestion; live listening providers (Brandwatch,
Mention, …); bespoke creative production (BRAND-019).
