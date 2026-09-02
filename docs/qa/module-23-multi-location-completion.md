# Module Completion Report — Multi-Location MSP Support, Phase 12

**Date:** 2 September 2026 · **Module:** Multi-Location MSP Support · **Register:** 11/12 Tested (92%), was 5/12 (42%)

## What shipped

**Per-branch marketing scoping** — `ad_campaigns` and `content_pieces` gained a nullable
`seo_location_id` (TenantExists-validated in both controllers); unscoped stays central by
design. The branch report on `/website/taxonomy` now rolls up each branch's local SEO
footprint (citations total/consistent, geo keywords matching the branch city), scoped
campaigns and content, next to the existing attributed leads/SQLs/won value (MLOC-005/006).

**Regional marketing calendar** (MLOC-008/011) — campaigns and content grouped by the
branch they target, with unscoped work under **Central**: centralized and regional
marketing are one screen, not two claims.

**Brand consistency by branch** (MLOC-010) — deterministic checks on first-party data:
published location page, complete NAP, the market named in the page title, meta
description present, and the brand tagline appearing in page sections when the profile
defines one. Every failing check names its fix; nothing guesses at tone or design.

**Franchise support** (MLOC-009) — a real parent/child hierarchy:
`organizations.parent_organization_id`, linking that demands **one person owning both
organizations** (an admin of the parent alone cannot pull a foreign org under it), no
chains (a franchisee cannot take franchisees), per-child roll-up (contacts, SQLs, won
value, published pages) whose cross-tenant reads are pinned to explicitly linked child
ids, and one-click brand push-down of the franchisor profile. Surface:
`settings/franchise`; unlink restores independence; a franchisee sees who its
franchisor is.

## Honest scoping

**MLOC-002 stays Partially Implemented** — the per-branch GBP place-id mapping, NAP
records and citation checking exist, but *managing* multiple live Google Business
Profiles requires the GBP API through the blocked outbound 443.

## Gate evidence

- Pest: **878 passed / 3,907 assertions** (+5 tests over Phase 11), including
  [MultiLocationFranchiseTest](../../tests/Feature/Qa/MultiLocationFranchiseTest.php):
  branch footprint rollup, Central-vs-branch calendar, compliant vs deviating branch,
  full franchise lifecycle (link → rollup → brand push → unlink), and the refusals —
  foreign-owned org rejected, viewer forbidden, non-franchisee 404, chain link refused.
- PHPStan **0** (including a real bug it caught: a null array key silently becomes `''`
  in PHP — the Central bucket now handles that explicitly) · Pint clean ·
  Prettier/ESLint/tsc clean · Vite build ok · Vitest 43.
- Register: MLOC 11 Tested / 1 Partial with per-row evidence notes.
