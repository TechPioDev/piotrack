# Module Specification — Growth Command Center (CMDC, "Module 02")

> Approved 2026-08-27, per the Phase 1 order (tier 2). Spec per Master Prompt §58–59.

## Purpose

Turn `/dashboard` from a KPI board into the command center the audit prescribed: what happened
(period-compared KPIs), what's trending (leads, won MRR, Growth Score), where revenue comes from
(funnel + channel attribution), what needs attention (alerts, waiting chats, hot leads), and what to
work next (top open deals, Growth Score recommendations). **Every figure from existing tenant data;
no new tables; nothing invented.**

## Users & roles

Every member (the dashboard is deliberately ungated — the existing controller comment documents this
choice; module-specific pages stay gated on their own routes). No new permissions.

## Feature IDs

Analytics Dashboard rows covering the executive dashboard/command-center concern (statuses reviewed at
gate); DSGN-006 gains period comparison (stays Partial until user-selectable date ranges exist).

## Windowing honesty rules

- Period = last 30 days vs the 30 before. Delta % is null when the previous period is 0 (rendered as
  "new", never ∞ or 100%).
- Only flows with real timestamps get deltas: new leads (`contacts.created_at`), meetings booked
  (`bookings.created_at`), deals won + new MRR (`deals.closed_at`). Stocks (qualified pipeline, ARR)
  and states without a promotion timestamp (SQLs) show current value with **no delta** — a fabricated
  delta would lie.
- Growth Score trend comes from stored daily snapshots (`growth_scores`); the current score is computed
  live. One point renders as a point, not a fake line.

## Database entities

None new.

## API / props (Inertia, `dashboard` page)

`DashboardController` composes a new `App\Services\Analytics\CommandCenterService` plus existing
`GrowthScoreService`, `AttributionService`, `AnalyticsService`:

- `kpis`: {new_leads, meetings, deals_won, new_mrr} each {value, previous, delta_pct|null} +
  {qualified_pipeline, sqls, arr} plain.
- `leadTrend`: 30 daily points. `mrrTrend`: cumulative won MRR across the window, daily.
- `growthScore`: {overall, band, recommendations (top 2), history [{label, value}]}.
- `funnel`: ordered leads→MQL→SQL→meetings→opportunities→won (existing funnel()).
- `channels`: won revenue by lead_source (existing channelRevenue()).
- `attention`: {alerts (≤5 unread: type, message, contact), waiting_chats, hot_leads}.
- `topDeals`: ≤5 open by value {id, name, stage, value}.
- `sources`, `onboarding`: unchanged.

## UI

Rebuild `resources/js/pages/dashboard.tsx` on the Module 01 kit: KPI row with StatCard deltas; Growth
Score card (score, band, mini trend, recommendations); lead-trend and cumulative-MRR LineCharts;
funnel + channel BarLists; attention feed with links (all-clear state); top-deals table; the existing
animated lead-sources list retained. Defensive prop normalization kept (page renders zeros, never
crashes, when props are absent — pinned by the existing Vitest test, which is updated for new names).

## Testing

Pest `CommandCenterTest`: window edges (row in current vs previous vs outside), delta math incl.
null-previous, growth score history wiring, attention counts (unread alert, waiting chat, hot lead),
top-deals ordering/exclusion of closed, channels wiring, and the empty tenant (zeros, null deltas, no
errors). Vitest: dashboard renders complete + absent payloads (updated), chart kit already covered.

## Out of scope

User-selectable date ranges, per-role dashboards, widget customization, CRM table upgrade (next),
alerts beyond what `SalesAlert` already stores.
