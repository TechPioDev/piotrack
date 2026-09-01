# Phase 8 Completion Report — Buyer Intent Intelligence (Module 19)

**Date:** 2026-09-02 · Register: **824 → 828 Tested** · Buyer Intent 69% → **94%**
(15/16 — the second Jumpfactor battleground effectively closed; the one open row is
reverse-IP company identification, which needs an external enrichment provider and
will not be faked).

## What shipped

Cross-module intent ingestion became first-party and real:
- **Campaign engagement (INTENT-010):** first campaign click → `campaign_click`
  signal (weight 10) on the contact via the public tracking route, org resolved from
  the recipient, deduped per token.
- **Ad engagement (INTENT-011):** a known contact opening a new session on a paid
  first touch (ChannelClassifier) → `ad_engagement`, once per session.
- **CRM activity (INTENT-012):** calls/emails/meetings logged on a contact →
  `crm_activity`; notes and tasks deliberately excluded.
- **Buying window (INTENT-013):** ≥3 signals worth ≥20 points inside 14 days —
  sustained evaluation, not one hot page. Single spikes and aged activity refused
  (pinned); badged on Sales → Intent.

## Gate

pint ✓ · phpstan 0 ✓ · prettier ✓ · eslint ✓ · tsc ✓ · Vitest 43 ✓ ·
**Pest 861 / 3,726 assertions** (+4: tests/Feature/Qa/BuyerIntentSignalsTest.php).
