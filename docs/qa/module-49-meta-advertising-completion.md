# Module Completion Report — Facebook / Meta Advertising

**Phase 38 · 2026-09-06 · Register: META-002, META-006, META-008, META-009, META-010, META-011 → Tested (module 11/11, 100%)**

## Scope

The six open rows mirror the LinkedIn set closed in Phase 37 and land on the same honest
no-API workflow, reusing machinery already tested: the meta customer-match CSV (P16), the
retargeting engine, the content/reputation records, and the P37 promotion/import patterns.
Live Meta Marketing API delivery, custom-audience push and lead-form sync stay behind
ADR-0006 and are claimed nowhere.

## What shipped — `MetaAdsService`, `MetaAdsController`, shared `AdLeadImporter`

### Content amplification & video advertising (META-006/009)

`promoteContent(piece)` — draft Meta campaign with creative from the piece (title →
headline at the 40-char feed limit, excerpt → primary text at 125), idempotent per piece.
Video-typed pieces (video/webinar/podcast/interview) draft as `video_ad` campaigns whose
creative states plainly that the video file is attached in Ads Manager — the platform
never pretends to hold the media. Piece-page buttons: "Amplify on Facebook" / "Run as
Meta video ad".

### Proof & testimonial campaigns (META-008)

`proofCampaign()` — drafted from evidence on file only: 4-star-plus reviews with text
(top 3 become ads quoting author and body — a 2-star review never becomes an ad) and
published case studies. **Refuses** when no proof is recorded, the same evidence floor
as the P17 proof page. Conversions objective, idempotent.

### Retargeting & multi-platform retargeting (META-002/011)

The audience-attach route is now platform-dispatched: Meta campaigns go through
`MetaAdsService::attachAudience` (meta-only, tenant-checked), LinkedIn through the P37
path — one audience attaches across **both** platforms, and the same member list exports
per-platform customer-match CSVs (google/meta/linkedin, P16). Search campaigns still
refuse the attach; Google runs via Customer Match upload of the same export. The
campaign page's audience panel now serves both platforms with platform-correct copy.

### Lead generation (META-010)

The lead importer was extracted into a shared `AdLeadImporter` (the P37 LinkedIn importer
delegates to it with behavior unchanged — its tests still pass untouched). The Meta path
maps Ads Manager's export headers, splits `full_name` into first/last, captures phone,
and keeps the set-once rule: `lead_source = facebook` only for contacts with no earlier
first-touch source. Import dialog on the campaigns page.

## Gate results

| Check | Result |
| --- | --- |
| `vendor/bin/pint --test` | pass |
| `phpstan analyse` (1G) | 0 errors |
| `npm run format:check` / `npx eslint .` / `npm run types` | pass |
| `npm run build` | pass |
| `npm run test` (Vitest) | 55 passed |
| `php vendor/bin/pest` | **1,004 passed, 4,977 assertions** |

New: `tests/Feature/Qa/MetaAdsCloseoutTest.php` (4 tests) — amplification creative +
idempotency + video-ad honesty; proof campaign from real reviews with the no-evidence
refusal and the sub-4-star exclusion; one audience attached across Meta and LinkedIn
with the search-platform refusal; Meta leads import with full-name splitting, phone
capture and set-once sources. The P37 LinkedIn suite passes unchanged after the
importer extraction.

## Register effect

6 rows → Tested. Facebook / Meta Advertising **11/11 (100%)** — 33rd complete module.
Global: **1,049/1,190 buildable Tested (88.2%)** — the register crossed 88%.
