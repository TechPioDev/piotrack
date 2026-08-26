# Module Specification — Design System & Application Shell (DSGN-SHELL, "Module 01")

> Scoped from the Phase 1 baseline (docs/qa/phase-1-baseline.md). Approved 2026-08-27.

## Purpose

Give the authenticated application the visual foundation the audit found missing: surface hierarchy
instead of white-on-white, contained horizontal scrolling instead of a stretching document, one heading
standard on every page, and a chart kit that puts the first real visualizations on the five dashboards
that today show KPI tiles only. Perception-of-quality work; **no new business features**.

## Users & roles

Every authenticated user; no new permissions. Existing `*.view` permissions keep gating each dashboard.

## Feature IDs

DSGN rows in `docs/register/feature-register.csv` covering design tokens / color system / typography /
surface standards / data-visualization standards (statuses updated at the gate with evidence), plus the
three defects recorded in Module 00 §13 (shell overflow, missing h1 pages, stale inbox link).

## User stories

- As any user, pages read as layered software (ground → card → elevated), in light and dark themes.
- As a marketer/SEO/ads/content/sales user, my module dashboard answers "what's the trend?" and
  "how do items compare?" with charts computed from my organization's real records — and an honest
  empty state when there is no data yet.
- As a user on a narrow window, wide boards scroll inside their panel; the page itself never scrolls sideways.

## Subscription requirements

None new. Charts render from data already gated by each module's entitlement.

## Database entities

None. **No new tables, no migrations.** All chart series derive from existing tables
(contacts, campaigns, keywords, seo_audits, ad_metrics, content_pieces, deals × pipeline_stages).

## API endpoints

None new. The five dashboard controllers gain additional Inertia props (server-computed series):

- `marketing/dashboard`: `trend` (new contacts/day, 30d), funnel from existing `lifecycle`.
- `seo/dashboard`: `distribution` (top-3 / page-1 / 11–20 / 21+ / unranked buckets), `auditTrend` (score by audit).
- `advertising/dashboard`: `trend` (spend + clicks by day, 30d, from ad_metrics).
- `content/dashboard`: pipeline rendered from existing `byStatus` (idea→draft→in_review→approved→published).
- `sales/dashboard`: `pipeline` (open deal value by stage from the default pipeline).

All queries run inside tenant scope; money stays in minor units server-side, formatted client-side.

## UI pages & components

- **Tokens (`resources/css/app.css`)**: light `--background` becomes a subtle brand-biased neutral;
  dark `--card` lifts above `--background`; sidebar tokens follow; `--chart-1..5` repointed to a
  coherent brand-led palette (both themes). Cards/popovers stay white in light — they now read elevated.
- **Shell (`components/ui/sidebar.tsx` SidebarInset)**: add `min-w-0` so flex children constrain;
  wide content scrolls in its own container (deals board already has `overflow-x-auto`).
- **Chart kit (`components/charts/`)**: hand-rolled SVG, no new dependency —
  `LineChart` (trend, area fill, endpoint emphasis), `BarList` (labeled horizontal comparison),
  `SegmentBar` (single stacked distribution with legend). Each renders a stated empty state when the
  series is empty; colors only via `--chart-*`/theme tokens; `aria-label` on every chart.
- **PageHeader adoption** on the six h1-less pages: analytics/attribution, analytics/growth-score,
  billing, seo/ai-visibility, projects, settings/members (settings pages keep their layout; the page
  title becomes a real `h1`).
- Fix the stale `/chat/inbox` link if it exists in code.

## Integrations

None.

## Testing

- Pest: dashboard prop tests per controller — series present, computed from seeded rows (e.g. a seeded
  ad_metric's spend appears on its day; seeded deals sum into their stage; keyword buckets count
  correctly), and empty-tenant series are empty arrays (no invented data).
- Vitest: chart kit — renders bars/paths from data, honors empty state, no NaN coordinates on
  single-point/zero-value series.
- Existing MenuSmokeTest keeps every page rendering; full gate before commit.

## Out of scope

Dashboard customization/widgets, the Growth Command Center, role-based dashboards, CRM table upgrades,
any new metrics not already derivable — later modules.
