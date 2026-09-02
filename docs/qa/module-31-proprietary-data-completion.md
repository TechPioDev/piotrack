# Module Completion Report — Proprietary Data Layer, Phase 20

**Date:** 3 September 2026 · **Module:** Proprietary Data Layer · **Register:** 11/18 Tested (61%), was 9/18 (50%)

## What shipped — the proposal-stage convention (BENCH-008/009)

The two rows' note named the gap exactly: the benchmark engine was generic and tested,
but meeting→proposal and proposal→win rates "need an explicit proposal stage convention
across tenants before they can be emitted honestly". The convention now exists:

- `pipeline_stages.is_proposal` — the default pipeline's "Proposal" stage carries it
  (provisioner for new tenants, data-fix for existing ones).
- `deals.proposal_sent_at` — stamped the **first** time a deal moves into a proposal
  stage and never rewritten on re-entry (test-pinned with time travel), so the funnel
  date stays honest.
- Two new metrics through the identical k-anonymity machinery: `meeting_to_proposal`
  and `proposal_to_win`, suppressed below the cohort floor exactly like every other
  benchmark (test-pinned: exact medians/percentiles across a 3-org cohort, null below
  it). Labeled on /analytics/benchmarks.

## Honest scoping — 7 rows stay Planned, and why that is correct

BENCH-003/004/005 and BENCH-013..016 (per-service/geo CPC, SEO conversion rates,
best-performing keyword/offer/vertical/ad benchmarks) are gated not by missing code but
by **data**: emitting them requires a contributing cohort larger than the k-anonymity
floor — which is the product's own honesty guard, not an obstacle to engineer around.
Their notes now say precisely that; they unlock as the tenant base grows.

## Gate evidence

- Pest: **912 passed / 4,147 assertions** (+3:
  [BenchmarkProposalTest](../../tests/Feature/Qa/BenchmarkProposalTest.php)).
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
