# Phase 29 — Proprietary Data Layer close-out (segmented benchmarks)

Register target (7 rows): BENCH-003 (CPC by service), BENCH-004 (CPC by city/region),
BENCH-005 (SEO conversion rates), BENCH-013 (best keywords), BENCH-014 (best offers),
BENCH-015 (best verticals), BENCH-016 (best ads).

## The gap, honestly stated

The notes deferred these as cohort-gated ("unlocks as the tenant base grows"), but the
cohort gate was only half the story: the per-dimension AGGREGATIONS themselves were
never built ("engine-side taxonomy partially exists"). The register discipline settled
in Phase 25 applies: build and test the mechanism, guard the emission. The suite can
seed multi-tenant cohorts above and below the floor, so both emission and suppression
are provable today; production emission still unlocks with the real tenant base — by
the already-shipped guard, not by trust.

## Design

**The missing binding** — `ad_campaigns.service_line_id` (nullable FK, campaign form
select), completing the taxonomy axis P12 started with `seo_location_id`. Cross-tenant
dimensions come from keys that are canonical across tenants: `service_lines.key`,
normalized region/city strings, normalized keyword phrases, `booking_pages.meeting_type`,
normalized `companies.industry`, and ad `platform`.

**BenchmarkService grows a segmented layer** — same k-anonymity floor applied PER
SEGMENT (a segment's cohort is the count of contributing orgs; below the floor the
segment is omitted, never averaged into vagueness):

| Row | Dimension | Per-org value | Emitted per segment |
|---|---|---|---|
| 003 | service_lines.key | CPC (spend/clicks, cents) | cohort, peer median, your value |
| 004 | location region (city fallback) | CPC | same |
| 013 | keyword phrase (normalized) | best current position | cohort, page-one share, median position |
| 014 | booking meeting_type | completion rate % | cohort, median rate, your value |
| 015 | company industry (normalized) | avg won-deal value | cohort, median value, your value |
| 016 | ad platform | CTR % (and CPC) | cohort, median CTR, median CPC, your CTR |

**BENCH-005** is org-level, not segmented: `seo_conversion_rate` joins the flat METRICS
list — organic-sourced contacts who became customers / organic contacts, per org, same
floor and percentile treatment as the other nine.

**Surfaces** — BenchmarkController passes `segmented`; benchmarks.tsx renders one table
per dimension (segment · cohort · peer median · you) under the existing metric cards,
with the suppression note. Campaign dialog gains the service-line select.

## Tests (tests/Feature/Qa/BenchmarkSegmentsTest.php)

Floor configured to 2 via `analytics.benchmark_min_cohort`; multi-org seeding proves,
for each dimension: correct math, per-segment suppression below the floor, the current
tenant's own value, and cross-tenant aggregation that never leaks a single org's raw
row. Plus: seo_conversion_rate in the flat benchmarks, campaign service binding via
the endpoint, and the benchmarks page carrying the segmented prop.
