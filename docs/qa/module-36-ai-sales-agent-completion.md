# Module Completion Report — AI Sales Agent, Phase 25

**Date:** 4 September 2026 · **Module:** AI Sales Agent · **Register:** 15/16 Tested (94%), was 9/16 (56%)

## What shipped

**Website AI chatbot (AISA-002) — evidence, not code.** The survey showed the public
chatbot shipped with the chat module and the register note was stale: the widget's `ai`
flow node answers typed visitor questions through AiGateway (grounded on org name +
service lines), turn-caps at 5, degrades to human capture on any AI failure, spends
nothing from builder previews, and ships in DefaultChatFlow. ChatBookingAndAiTest
covers it end-to-end over the public `/wc` endpoints. Closed on that evidence.

**Research grounded in the prospect's own website (AISA-005/007)** —
[ProspectSiteReader](../../app/Services/Ai/ProspectSiteReader.php) fetches the
company's site through the SSRF guard (in-house crawler discipline — no vendor):
title, meta description, headings, a short excerpt, scripts stripped. The research
profile carries a provenance-labeled "Observed on their public website (url, fetched
date)" block — genuine external evidence the model may cite. Honesty in both
directions: an unreadable or absent site produces an explicit "CRM records only"
statement, never silence, and the never-assert-unverified-facts prompt discipline is
unchanged.

**Advisory scores: persistence + outcome calibration (AISA-012/013)** — the Partial
reason was "predictive quality cannot be claimed without validation," so quality is now
*measured, never claimed*: every advisory score persists to `ai_scores` (morph to
contact/deal; the deterministic lead_score is still never written — asserted), and
[ScoreCalibration](../../app/Services/Ai/ScoreCalibration.php) back-tests deals that
were AI-scored and have since closed — won vs lost buckets, average advisory score per
bucket, separation — guarded `insufficient_data` under 3 closed deals per bucket. The
AI dashboard shows the tenant's own calibration next to the fixture/live driver notice.

**Real call transcripts (AISA-014)** — `calls.transcript` existed but only the
credential-gated provider could ever fill it. Now a transcript pasted from any source
attaches to the call (PATCH, manage-gated, 20k-char cap, oversize refused) and the
summarize endpoint runs the AI summary on what was actually said; without a transcript
it summarizes metadata and says so. Calls-page UI: transcript dialog (showing the AI
summary) + Summarize action.

## Honest scoping — 1 row stays

**AISA-006 (AI contact enrichment)** — verified emails, phones and firmographics can
only come from an enrichment data provider; a model must not fabricate them. Reading
the prospect's own site (new in this phase) is observation, not verified enrichment.
Stays Planned pending a provider.

## Gate evidence

- Pest: **938 passed / 4,406 assertions** (+5:
  [AiSalesAgentCloseoutTest](../../tests/Feature/Qa/AiSalesAgentCloseoutTest.php)).
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
