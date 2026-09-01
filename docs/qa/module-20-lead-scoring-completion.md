# Phase 9 Completion Report — Lead Scoring (Module 20)

**Date:** 2026-09-02 · Register: **828 → 837 Tested** · Lead Scoring 42% → **89%**
(17/19; the two open rows — predictive and AI scoring — need a trained model /
outbound 443 and stay honestly deferred).

## What shipped

- **Behavioural capture closed the loop (LSCR-005..010):** every §20 signal now
  records automatically — pricing/service/content pageviews (tracker), repeat visits
  (new session → repeat_visit 10), form submissions (form_submission 10, covering
  lead-magnet downloads), campaign clicks (Phase 8). One end-to-end pin: anonymous
  browse → capture → identify → repeat visit → scoring rule → SQL promotion.
- **Firmographic scoring (LSCR-011/012):** company_size / company_city /
  company_region / company_industry rule attributes from first-party CRM company
  data. The old "firmographics unavailable" executable pin fired exactly as designed
  and was inverted: §20's "+15 for company size 100–250" is now expressible.
- **Automatic routing (LSCR-019):** unowned captured leads assign round-robin to the
  least-loaded active member at capture time; owned contacts keep their owner.

## Gate

pint ✓ · phpstan 0 ✓ · prettier ✓ · tsc/eslint n/a (no front-end change) ·
Vitest 43 ✓ · **Pest 863 / 3,730 assertions** (+2 files:
LeadScoringSignalsTest + the rewritten firmographic pin).
