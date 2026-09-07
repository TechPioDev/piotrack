# Phase 38 — Facebook / Meta Advertising closeout

**Register targets:** META-002, META-006, META-008, META-009, META-010, META-011
(module 45.5% → 100%).

## Honesty position

Stage 8 closed campaign structure/targeting rows (META-001/003/004/005/007). The six open
rows mirror the LinkedIn set and close with the same no-API workflow halves, on machinery
already tested: the meta customer-match email CSV (P16), the retargeting audience engine,
the content/reputation records, and the P37 import/promotion patterns. Live Meta Marketing
API delivery, custom-audience push and lead-form sync stay behind ADR-0006, never claimed.

- **META-006 Content amplification** — content piece → draft Meta campaign, creative from
  the piece; idempotent (`targeting.content_piece_id` on the meta platform).
- **META-009 Video advertising** — the same bridge is video-aware: video-typed pieces
  (video/webinar/podcast/interview) produce a `video_ad` campaign whose creative notes the
  video is attached in Ads Manager (the platform never pretends to hold the media file).
- **META-008 Proof & testimonial campaigns** — draft Meta campaign built from real proof
  only: 4-star-plus reviews with text (top 3 as ads quoting author + body) and published
  case studies; **refuses** when no proof is on file (same evidence floor as the P17
  proof page).
- **META-002 Retargeting** — matched-audience attachment for Meta campaigns; the meta
  customer-match CSV (P16, tested) is the Ads Manager upload.
- **META-011 Multi-platform retargeting** — one audience attaches to Meta AND LinkedIn
  campaigns, with per-platform customer-match exports (google/meta/linkedin) from the
  same audience — first-party multi-platform coverage; Google runs via Customer Match
  upload of the same export.
- **META-010 Lead generation** — Meta lead ads CSV import (Ads Manager's export headers:
  email, full_name or first/last, phone_number, campaign_name), `lead_source = facebook`
  set-once, full-name splitting, malformed rows skipped. Live form sync stays API-gated.

## Build

1. Extract `App\Services\Advertising\AdLeadImporter` (shared CSV lead importer:
   alias map + source + set-once semantics + full-name splitting);
   `LinkedInAdsService::importLeads` delegates to it unchanged in behavior.
2. `App\Services\Advertising\MetaAdsService`: `promoteContent` (video-aware),
   `proofCampaign` (guarded), `attachAudience` (meta-only), `importLeads` (facebook).
3. `LinkedInAdsController::attachAudience` becomes platform-dispatching
   (linkedin → LinkedInAdsService, meta → MetaAdsService, else refused) — the
   existing route and LIAD tests keep their behavior.
4. Routes: POST ads/meta/promote-content · POST ads/meta/proof · POST ads/meta/leads.
5. UI: content piece page "Amplify on Facebook" (video pieces: "Run as video ad");
   campaigns/show matched-audience panel extended to meta; campaigns/index Meta row
   (proof campaign button + leads import dialog, dialog generalized).
6. Tests `tests/Feature/Qa/MetaAdsCloseoutTest.php` (5): amplification + video-aware
   idempotent promotion; proof campaign creative from real reviews + refusal with no
   proof; meta audience attach + multi-platform (same audience on meta + linkedin);
   leads import with full-name split + set-once source; platform dispatch guard.

## Out of scope (unchanged)

Meta Marketing API delivery, live custom-audience push, live lead-form sync (ADR-0006).
