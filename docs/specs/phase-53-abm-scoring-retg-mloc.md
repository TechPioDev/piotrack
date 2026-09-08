# Phase 53 — ABM + Lead Scoring + Retargeting + Multi-Location close-out
(ABM-004/017, LSCR-014/015, RETG-006/007, MLOC-002)

**Goal: four modules to 100% — ABM 19/19, Lead Scoring 19/19, Retargeting Engine 17/17,
Multi-Location 12/12.**

## Insights

- **ABM-004 company enrichment**: the "Clearbit-class provider" the note waited for is the
  P51 `EnrichmentProvider` seam (already closed CRM-027, AISA-006, INTENT-002). It grows
  `enrichDomain()`; target accounts enrich their company with set-once fills and audited
  provenance.
- **ABM-017 video outreach**: the "video itself is external" stays true — and P42 made an
  externally-hosted video first-class (any host URL, click-tracked send). A third ABM play,
  `video_outreach`, queues a per-decision-maker task pointing the rep at the tested
  video-message flow on the contact page.
- **LSCR-015 AI scoring**: "rides the AiGateway once 443 opens" is the stale form of the
  standard key-gating every AI row already handles. The P25-tested advisory scorer
  (persisted, calibrated, never overwriting the deterministic score) surfaces on the
  scoring page.
- **LSCR-014 predictive scoring**: honest empirics, not statistical dressing — win rates
  from the tenant's OWN closed deals bucketed by lead source / industry / score band,
  each bucket behind its own sample floor, combined only when total closed history meets
  the model floor (30). Below the floor the model REFUSES with the counts. Advisory only.
- **RETG-006/007 video/YouTube retargeting**: P42 already ships draft YouTube video
  campaigns and the youtube attach path ("YouTube retargeting rides Google Ads Customer
  Match" is in the code). The close is the missing glue: one action on the retargeting
  page that builds the draft video campaign from a chosen video piece AND attaches the
  audience — live delivery stays connector-gated exactly like every 100% ads module.
- **MLOC-002 live GBP management**: a WRITE seam (the SMS-provider precedent): `GbpProvider`
  pushes a branch's real NAP+hours payload; the fixture records/acks the push and is
  labeled simulated; live = Google Business Profile API OAuth + one class. Requires the
  branch's `gbp_place_id` — no place id, no push.

## Build

- `EnrichmentProvider::enrichDomain` + fixture; `AccountController::enrich` + button.
- `AbmPlayRunner` += `video_outreach` play (tasks reference the tested video-message flow).
- `PredictiveScoringService` + scoring-page panel; `ScoringController::aiScore` (advisory
  flash via the tested agent).
- `RetargetingController::videoCampaign` (+ video-piece select on the page) calling
  `VideoAdsService::youtubeCampaign` + `attachAudience`.
- `app/Seo/Contracts/GbpProvider` + fixture + `seo.gbp_provider` + manager + binding;
  `LocalController::pushGbp` + per-branch button.
- `tests/Feature/Qa/AbmScoringRetgMlocCloseoutTest.php` (~6 tests).

## Honesty lines

- Enrichment fills only empty fields; provider name audited; free-form domains with no
  match yield nothing.
- The predictive model refuses below its floor and labels every factor with its sample.
- The GBP fixture push is labeled simulated in the UI and audit trail; a real push is
  credentials + one class.
- Still blocked, unchanged: LLMO-015 (tenant authoring), POD-008 (YouTube upload),
  SEC-007 (infrastructure provisioning).
