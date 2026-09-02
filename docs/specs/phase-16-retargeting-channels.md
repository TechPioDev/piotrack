# Phase 16 — Retargeting Engine close-out: platform export files + SMS re-engagement

Register target: RETG-001..005, 009, 010 (7 rows). Stay Planned: RETG-006/007
(video/YouTube retargeting — need Google Ads video-campaign connectors; a customer list
alone does not build a video audience placement).

## Design

**Platform-ready audience export (RETG-001..005)** — every major ad platform's
customer-list upload accepts a CSV of SHA-256-hashed lowercase emails, uploaded manually
in their Ads UI. `RetargetingService::exportCsv(audience, platform)` produces exactly
that file per platform (google → `Email` header, meta/linkedin → `email`), from the
already-tested `syncPayload()` hashing. That makes retargeting on these platforms a
complete workflow today: build the audience here, download the file, upload it in Ads
Manager. The *automated* API push stays a noted enhancement (ADR-0006) — the notes say
so precisely — but the capability the rows name is now usable end to end.

**SMS re-engagement (RETG-009)** — the row's own note has always said what closes it:
"the Stage 6 SMS engine driven by a retargeting audience (wire later)". Wired now:
`smsReengage(audience, body)` sends through the existing `MessageDispatcher::sendSms`
(consent + suppression enforced per contact, OutboundMessage recorded, provider
abstraction — live Twilio delivery remains separately credential-gated in SMSA), skips
members without a phone, and reports honest counts: targeted / sent / suppressed /
no-phone.

**Cross-channel (RETG-010)** — one audience now drives email retargeting (Tested,
RETG-008), SMS re-engagement (new), and per-platform export files. That is cross-channel
orchestration from a single audience; only the automated ad-platform push remains
vendor-gated.

Surface: per-audience export buttons (Google/Meta/LinkedIn) and an SMS dialog on
/ads/retargeting. Both behind `ads.retargeting.manage` (exports carry hashed PII).

## Tests (tests/Feature/Qa/RetargetingChannelsTest.php)

1. Export CSV: platform headers, SHA-256 rows matching members, conversion exclusion
   respected, contacts without email skipped.
2. SMS re-engagement: opted-in members get sent OutboundMessages (log provider),
   suppressed/opted-out counted as suppressed, phoneless counted separately; audited.
3. Permission gating (viewer forbidden) + tenant isolation on both endpoints.
