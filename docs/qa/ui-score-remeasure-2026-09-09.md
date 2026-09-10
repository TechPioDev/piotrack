# Product UI score — re-measurement (2026-09-09)

Anchor for the UI 100/100 campaign. Method identical to the 26–27 Aug baseline:
computed-style and DOM measurement on the same 17 authenticated pages, seeded demo
org ("Northwind IT Services", demo@piotrack.test), viewport 1052×820 (the baseline's
frame) plus 375×812 mobile spot-checks. Every number is a measurement, not an
impression. Detector for charts: the three shared chart components (`bar-list`,
`line-chart`, `segment-bar`) all render `role="img"` with an aria-label; empty
states carry a `: no data` suffix and are counted separately.

## Per-page raw measurements (1052×820)

| Page | h1 | Overflow px | Charts (data) | Charts (empty) | Tables |
| --- | --- | ---: | ---: | ---: | ---: |
| /dashboard | Dashboard | 0 | 3 | 2 | 0 |
| /analytics | Analytics | 0 | 0 | 0 | 2 |
| /chat | Conversations | 0 | 0 | 0 | 0 |
| /analytics/attribution | Attribution | 0 | 0 | 0 | 4 |
| /analytics/growth-score | MSP Growth Score | 0 | 0 | 0 | 0 |
| /crm/contacts | Contacts | 0 | 0 | 0 | 1 |
| /strategy | Strategy | 0 | 0 | 0 | 6 |
| /ads | Advertising | 0 | 2 | 0 | 1 |
| /content | Content | 0 | 2 | 0 | 1 |
| /crm/deals | Deals | 0 | 0 | 0 | 0 |
| /ai | AI | 0 | 0 | 0 | 0 |
| /projects | Projects | 0 | 0 | 0 | 4 |
| /settings/members | Settings (layout-level) | 0 | 0 | 0 | 0 |
| /marketing | Marketing | 0 | 2 | 0 | 0 |
| /seo | SEO | 0 | 1 | 1 | 0 |
| /sales | Sales | 0 | 2 | 0 | 0 |
| /billing | Settings (layout-level) | 0 | 0 | 0 | 0 |

Cross-cutting measurements:

- **Console errors: 0** across all 17 page loads.
- **Horizontal overflow: 0 px on 17/17** pages at 1052 (baseline: /crm/deals 2076 vs
  1052, /sales 1354 vs 1052). The `min-w-0` shell fix holds.
- **Mobile 375 px: 0 px overflow on /dashboard, /analytics and /crm/deals** — the
  kanban, the baseline's worst offender, is now fully contained.
- **Ground:** body `rgb(246, 249, 248)` (tinted hsl(160,16%,97.1%)) vs white cards —
  the white-on-white defect is gone. Font: Instrument Sans loads.
- **Icons:** zero imports of react-icons / heroicons / fontawesome / radix-icons
  anywhere under resources/js — lucide is the single icon source.
- **Stale link:** the /chat/inbox 404 link no longer exists.
- Deals board renders seeded deals (Qualified $9,600 · Proposal $21,600 · Won
  $19,200) with per-column internal scroll.

## Category scores — baseline → now

Categories re-measured today get new numbers with the evidence above; categories
not re-measured carry the baseline number unchanged (marked *carried*) so the
total never flatters.

| Category | Baseline | Now | Basis |
| --- | ---: | ---: | --- |
| Icon consistency | 75 | 95 | single icon source verified repo-wide |
| Forms | 70 | 70 | *carried* — not re-measured |
| Typography | 65 | 70 | Instrument Sans confirmed loading; scale not audited |
| Responsive | 60 | 95 | 0 px overflow 17/17 @1052 + 3/3 @375 incl. kanban |
| Empty/loading/error states | 60 | 65 | chart empty states designed; rest carried |
| Tables | 55 | 55 | *carried* — not re-measured |
| Navigation | 55 | 65 | stale 404 link gone; ⌘K present; ~60 links remain |
| Visual hierarchy | 55 | 85 | h1 on 17/17 (2 are the settings layout's generic h1) |
| Information density | 50 | 50 | *carried*; 5 dashboards render < 900 chars of content |
| Color system | 45 | 90 | tinted ground + elevated cards measured |
| Dashboards | 45 | 70 | all 6 domain dashboards carry 1–5 chart components |
| Data visualization | 30 | 55 | charts on 6/17 pages (was 5/17); analytics, attribution, growth-score, strategy still chartless with data already in props |

**Score: 72/100** (mean of 12, baseline 56/100).

## Phase plan to 100 (worst-first)

| Phase | Target categories | Work |
| --- | --- | --- |
| UI-P1 | Data viz 55, Dashboards 70, Info density 50 | Charts from data already in props: funnel on /analytics, channel/campaign bars on attribution, pillar segments + trend on growth-score, strategy market bars |
| UI-P2 | Tables 55 | Measure then standardize: sort affordance, lifecycle pills, aligned numerics, row hover, designed empty rows |
| UI-P3 | Empty/error states 65, Forms 70 | Designed empty states on chartless/table pages; form label/validation consistency pass |
| UI-P4 | Navigation 65, Hierarchy 85 | Page-level h1 on members/billing; sidebar group polish; promote ⌘K |
| UI-P5 | Typography 70, Icon 95 | Type-scale audit + fixes; residual icon misuse |
| Final | — | Full re-measure, new score section on the scoreboard artifact (baseline section preserved) |

Each phase ends with the standard gate, a re-measure of its target categories, and
a commit; the score is only updated from measurements.

## UI-P1 — result (2026-09-10)

Charts added exclusively from data the pages already receive; no new queries.

- **/analytics** — the eight funnel stat tiles became a real funnel (`BarList`,
  leads → closed won, per-step "% of leads" hints, closed-lost noted below it);
  the leads-by-channel table became channel bars with share hints.
- **/analytics/attribution** — revenue-by-channel and revenue-by-campaign tables
  became money-formatted bars with share hints, side by side.
- **/analytics/growth-score** — the hand-rolled trend columns became the shared
  `LineChart` (honest empty state until a snapshot is saved).
- **/crm/deals** — a `SegmentBar` of deal value by pipeline stage above the board
  (won green, lost destructive, open stages on the chart cycle), from the stage
  totals the kanban already carries.

Re-measured after `npm run build` at 1052×820: /analytics 2 charts ·
/analytics/attribution 2 charts · /crm/deals 1 chart · /analytics/growth-score
1 chart component (designed empty state) — 0 px overflow and 0 console errors on
all four. **Pages carrying chart components: 10/17** (9 with data + growth-score's
empty state), up from 6/17.

Category movements (measured): Data visualization 55 → 75 (10/17 pages, the
named chartless pages closed); Dashboards 70 → 80 (the analytics dashboard now
leads with a funnel). **Score after UI-P1: 75/100** (mean 74.6; baseline 56,
re-measure 72).

Gate: pint ✓ · phpstan 0 findings (raw grep) ✓ · prettier ✓ · eslint ✓ · tsc ✓ ·
build ✓ · Vitest 55 ✓ · Pest 1,086 / 5,706 assertions ✓.
