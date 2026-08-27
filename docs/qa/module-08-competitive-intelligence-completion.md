# Module Completion Report — Competitive Intelligence (Module 08)

Date: 2026-08-28 · Coverage tier, weakest register module (15%). The answer to "where am I
stronger or weaker than competitors?" now comes from our own recorded data, on schedule.

## What shipped

1. **Daily rank tracking, both sides** (`seo:track-rankings`, scheduled 04:00): our position per
   tracked keyword (via the keyword's mapped page host — an unmapped keyword's own side is
   honestly skipped, competitor side still tracked) and every tracked competitor's position.
   Idempotent per keyword+domain per day. Live positions need a rank-provider key (same
   fixture-vs-live discipline as everything else).
2. **Keyword head-to-head** on the competitors page: you vs every tracked competitor per keyword,
   with honest nulls (never an invented position) and a Leading/Behind status per row.
3. **Share of AI recommendations** (CINT-011): of every recorded AI check where we or a known
   competitor appeared, the share where WE were the active recommendation — plus per-competitor
   appearance counts in AI answers (CINT-008, riding Module 07's competitor-name detection).
4. **Competitor-outrank alert** (CINT-013): a new detector in the daily `alerts:sweep` fires when
   a tracked competitor's latest recorded position beats ours — deduped per keyword+competitor
   per day, silent where we lead (test-pinned both ways).

## Gate

- Pest **798 passed (3,271 assertions)** — `CompetitiveIntelligenceTest` (6 tests): tracker
  idempotency + honest skips, head-to-head nulls, recommendation-share math, outrank alert
  firing/dedupe/quiet-when-leading, page props, tenant sealing.
- Vitest 43, Pint, PHPStan, Prettier, ESLint, tsc clean; no migration needed; assets built.
- Live on seeded data: 8 head-to-head rows, AI recommendation share 66.7% of 15 contested answers.

## Register

CINT-001, -008, -011, -013 → **Tested**; the seven provider-gated rows (PPC/ads/backlinks/content/
maps/reviews/social monitoring) keep Planned with notes naming the missing provider class.
**Competitive Intelligence: 15% → 54%.** Totals: **740 Tested / 295 Partial / 147 Planned** of 1,191.

## Out of scope, unchanged

Provider-gated monitoring rows above; historical head-to-head trend charts; competitor content diffing.
