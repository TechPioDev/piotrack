# Phase 43 — Local SEO closeout

**Register targets:** LSEO-001, LSEO-009, LSEO-010, LSEO-013, LSEO-015, LSEO-016
(module 72.7% → 100%).

## Position

- **LSEO-001/009/010 (GBP / Maps / Map Pack optimization)** — the API can push a
  profile; the *optimization work* is knowing what to fix. A per-location **GBP
  readiness report** computed from what the platform holds first-party: profile
  completeness (NAP fields, website, place id), citation coverage and consistency
  (Citation rows + the NAP checker's mismatches), the published local page, local
  keyword tracking, and review strength (REP) — the actual local ranking factors —
  scored per check with recommendations citing numbers. Live GBP push/pull stays
  API-gated, stated on the page.
- **LSEO-013 Review optimization** — the deferral note is stale: the Reputation
  module closed at 100% in P17 (review recording, acquisition requests, response
  workflow, sentiment, rating trends — all tested). Closes on that evidence, with
  review strength wired into the local readiness report above.
- **LSEO-015 Local backlinks** — the P39 hard-binding pattern:
  `outreach_prospects.seo_location_id` binds a prospect/placement to a branch;
  local backlinks = placements bound to the location, listed with domain authority.
- **LSEO-016 Local authority building** — the per-location rollup of everything
  above: citations built/consistent, local placements + average DA, local page,
  reviews — with guarded recommendations. Authority *building* actions are the
  live outreach + citation machinery; the rollup shows where each branch stands.

## Build

1. Migration: `outreach_prospects.seo_location_id` (nullable FK, short name safe).
2. `App\Services\Seo\LocalAuthorityService`: `gbpReadiness(location)`,
   `authority(location)` (includes local backlinks list).
3. Outreach prospect create/binding: validation + location select in the outreach UI.
4. LocalController index props += per-location `gbp` + `authority`; seo/local page
   panels.
5. Tests `tests/Feature/Qa/LocalSeoCloseoutTest.php` (~4).

## Out of scope (unchanged)

Live GBP API (profile push, Q&A, posts), live review-platform pulls (REP note),
external link-index data (DA is rep-recorded, as everywhere in outreach).
