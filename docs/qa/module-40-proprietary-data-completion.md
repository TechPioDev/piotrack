# Module Completion Report — Proprietary Data Layer, Phase 29

**Date:** 4 September 2026 · **Module:** Proprietary Data Layer · **Register:** 18/18 Tested (**100%**), was 11/18 (61%)

## What shipped

The seven remaining rows were deferred as cohort-gated ("unlocks as the tenant base
grows"), but the cohort gate was only half the story — the per-dimension aggregations
were never built. The Phase 25 discipline applies: build and test the mechanism, guard
the emission. The suite seeds synthetic multi-org cohorts above and below the floor,
so emission AND suppression are both proven today; production segments unlock with the
real tenant base by the shipped guard, not by trust.

**[BenchmarkService](../../app/Services/Analytics/BenchmarkService.php) grew a
segmented layer** — the k-anonymity floor applied PER SEGMENT (a segment with fewer
contributing orgs than the floor is withheld entirely, never blurred into an average):

- **CPC by service (BENCH-003)** — campaigns bind to a service line (new
  `ad_campaigns.service_line_id` + campaign-dialog select); the canonical
  `service_lines.key` is the cross-tenant dimension.
- **CPC by city/region (BENCH-004)** — P12's campaign location binding, region with
  city fallback.
- **Best keywords (BENCH-013)** — normalized phrases tracked by enough orgs: page-one
  share, cohort median position, your best.
- **Best offers (BENCH-014)** — booking meeting types by completion rate.
- **Best verticals (BENCH-015)** — normalized company industries by average won-deal
  value.
- **Best ads (BENCH-016)** — platforms by CTR, median CPC alongside.

**SEO conversion rate (BENCH-005)** joined the flat metric list — organic-sourced
contacts who became customers, per org, same floor/percentile treatment as the other
nine.

The benchmarks page renders one table per dimension (segment · cohort · peer median ·
you) with the per-segment suppression rule stated plainly.

## Honest scoping

Nothing in this module is deferred any more. What remains external is the tenant base
itself: a production segment emits only when real contributing orgs cross the floor —
which is the product's own guarantee, now enforced per segment.

## Gate evidence

- Pest: **962 passed / 4,649 assertions** (+5:
  [BenchmarkSegmentsTest](../../tests/Feature/Qa/BenchmarkSegmentsTest.php));
  the existing BenchmarkProposalTest suite still green.
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
