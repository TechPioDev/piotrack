# Module Completion Report — Retargeting Engine, Phase 16

**Date:** 3 September 2026 · **Module:** Retargeting Engine · **Register:** 15/17 Tested (88%), was 8/17 (47%)

## What shipped

**Platform-ready audience exports (RETG-001..005)** — every major ad platform's
customer-list upload accepts a CSV of SHA-256-hashed lowercase emails through its own
Ads UI. `RetargetingService::exportCsv()` produces exactly that file per platform
(Google `Email` header, Meta/LinkedIn `email`), from the already-tested audience
hashing, with conversion exclusions honoured and mailless contacts skipped. Download
buttons per audience on /ads/retargeting; audited; behind `ads.retargeting.manage`
(the file carries hashed PII). The retargeting workflow is complete today — build the
audience here, upload the file there; the *automated* API push remains an ADR-0006
connector enhancement and the notes say so precisely.

**SMS re-engagement (RETG-009)** — the row's own note always named the close: "the
Stage 6 SMS engine driven by a retargeting audience". `smsReengage()` sends through the
existing consent-enforcing `MessageDispatcher` (per-contact opt-in + suppression,
OutboundMessage records, provider abstraction — live delivery separately Twilio-gated
in SMSA) and reports honest counts: targeted / sent / suppressed / without a phone.
An explicit suppression beats an opt-in, and the test pins it.

**Cross-channel (RETG-010)** — one audience now drives email retargeting (RETG-008,
Tested earlier), SMS re-engagement, and per-platform export files.

## Honest scoping — 2 rows stay Planned

**RETG-006/007 (video/YouTube retargeting)** — a customer list alone does not build a
video placement; these need Google Ads video-campaign connectors (blocked outbound 443).

## Gate evidence

- Pest: **893 passed / 4,012 assertions** (+4:
  [RetargetingChannelsTest](../../tests/Feature/Qa/RetargetingChannelsTest.php) —
  per-platform CSV headers + hash rows + exclusions, SMS counts through the log
  provider, suppression-beats-opt-in, permission gating + tenant isolation on both
  endpoints).
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
