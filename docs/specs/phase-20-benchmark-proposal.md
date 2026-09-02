# Phase 20 — Proprietary Data Layer: the proposal-stage convention

Register target: BENCH-008/009 (meeting→proposal and proposal→win rates). Their note
named the gap precisely: the benchmark engine is generic and tested, but the two rates
"need an explicit proposal stage convention across tenants before they can be emitted
honestly". This phase establishes that convention.

Stay Planned honestly: BENCH-003/004/005/013..016 — their gate is not code but data:
per-service+geo ad performance across a contributing cohort **larger than the
k-anonymity floor**. The floor is the product's own honesty guard; the rows unlock as
the tenant base grows, not as a build task.

## Design

- `pipeline_stages.is_proposal` — the convention flag; the default pipeline's
  "Proposal" stage carries it (provisioner + data-fix for existing tenants).
- `deals.proposal_sent_at` — stamped the FIRST time a deal moves into a
  proposal-flagged stage (like `closed_at` for won/lost); never overwritten by
  re-entry, so the funnel date stays honest.
- `BenchmarkService` gains two metrics through the same k-anonymity machinery:
  `meeting_to_proposal` (orgs' proposal-stamped deals ÷ meetings) and
  `proposal_to_win` (won deals that passed a proposal ÷ proposal-stamped deals).
  Suppression below the cohort floor applies exactly as for every other metric.

## Tests (tests/Feature/Qa/BenchmarkProposalTest.php)

1. Moving a deal into the proposal stage stamps `proposal_sent_at` once; re-entry
   never rewrites it.
2. Both rates emit correct math across a qualifying cohort, and the current tenant's
   percentile is right.
3. Below the k-anonymity floor both are suppressed (null), like every other metric.
