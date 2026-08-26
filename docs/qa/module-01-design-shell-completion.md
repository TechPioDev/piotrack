# Module Completion Report — Design System & Application Shell (Module 01)

Date: 2026-08-27 · Spec: `docs/module-spec-design-shell.md` · Approved scope: surface hierarchy,
overflow containment, heading standard, chart kit, first charts on the five chartless dashboards.

## What shipped

1. **Surface hierarchy (both themes).** Light: page ground `hsl(160,16%,97.1%)` under white cards;
   dark: ground `hsl(165,8%,4.1%)` under lifted `hsl(160,6%,7.1%)` cards; sidebar tokens follow.
   `--chart-1..5` repointed to a brand-led palette (teal, blue, amber, violet, rose) in both themes.
   Live-verified: body `rgb(246,249,248)` vs card `rgb(255,255,255)`; dark `rgb(10,11,11)` vs `rgb(17,19,18)`.
2. **Document overflow fixed at the shell.** `SidebarInset` gained `min-w-0`; wide content now scrolls
   in its own container. Live-verified: `/crm/deals` scrollWidth 2076→1067 (== viewport); `/sales` clean.
3. **Heading standard.** `Heading` (the top heading on 57 pages) now renders a real `h1` at the 24px
   system title size, matching `PageHeader`. All six audit-flagged pages verified (billing/members via
   the settings layout's heading). The audit's `/chat/inbox` 404 was a mis-guessed URL — no app link
   exists; nothing to fix.
4. **Chart kit** (`resources/js/components/charts/`): `LineChart`, `BarList`, `SegmentBar` — hand-rolled
   SVG on theme tokens, no new dependency, `aria-label` on every chart, honest empty states, no NaN on
   degenerate series. 7 Vitest tests.
5. **Real charts on the five dashboards** (new server-computed props, all tenant-scoped):
   - Marketing: new-contacts/day 30-day trend + lifecycle mix segment bar.
   - SEO: ranking-distribution bars (incl. Unranked — not hidden) + audit-score trend.
   - Advertising: spend/day + clicks/day trends summed across campaigns from `ad_metrics`.
   - Content: editorial pipeline bars (workflow order) + review-sentiment segment bar.
   - Sales: open-pipeline-value-by-stage bars (won/lost excluded) + temperature segment bar.

## Defect fixed in passing

`AcmeJourneyTest` ordered pipeline stages by a nonexistent `position` column — silently a no-op on
sqlite (double-quoted identifiers fall back to string literals in ORDER BY) but a hard error on
Postgres. Now uses the relation's real `sort_order` ordering.

## Gate

- Pest **746 passed (2,988 assertions)** — includes new `DashboardSeriesTest` (5 tests, 27 assertions:
  seeded rows appear in the right buckets/days/stages; empty tenants get empty/zero series, never
  invented data).
- Vitest **43 passed** — includes `charts.test.tsx` (7 tests).
- Pint, PHPStan (level max), Prettier, ESLint, tsc: clean. Assets rebuilt.
- Live browser verification: surface separation (both themes), overflow containment, charts rendering
  with seeded data, h1 present on previously flagged pages.

## Register

DSGN-001, -002, -007 → Tested; DSGN-006 → Partially Implemented (date filters/period compare still
missing); DSGN-003 note updated (overflow fixed; full breakpoint matrix pending).
Register totals: **703 Tested / 308 Partially Implemented / 171 Planned / 8 Implemented / 1 N/A** of 1,191.

## Out of scope, unchanged

Dashboard date filters and period comparison, Growth Command Center, CRM data-table upgrade, billing
usage meters — next modules per the Phase 1 order.
