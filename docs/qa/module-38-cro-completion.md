# Module Completion Report — Conversion Rate Optimization, Phase 27

**Date:** 4 September 2026 · **Module:** CRO · **Register:** 17/17 Tested (**100%**), was 10/17 (59%)

## What shipped

**First-party behavior capture (CRO-010/011/014)** — the deferral said heatmaps needed
"a behaviour-analytics provider + the web tracking pixel," but the pixel shipped with
Visitor Intelligence; what a provider adds beyond it is session *replay*. The ~1KB
script now records **click positions** (viewport-x% × document-y%, with the clicked
element's label) and **max scroll depth** (once, on pagehide) — same beacon, same
endpoint, validated 0–100.
[BehaviorAnalytics](../../app/Services/Analytics/BehaviorAnalytics.php) turns that
into: a 10×10 click-density grid + top click targets + scroll-depth buckets per page,
a per-page behavior table (views, uniques, clicks, avg depth), and **bounce rates per
landing path** — sessions rebuilt from pageview timestamps with the tracker's own
30-minute window, guarded under 5 sessions. New Behavior page under Analytics; the UI
states plainly that session replay needs a recording provider and is never simulated.

**Funnel insights (CRO-012/013/015/016)** —
[FunnelInsights](../../app/Services/Analytics/FunnelInsights.php) on the analytics
dashboard:

- **Step drop-off** — cumulative steps (leads → reached-MQL → reached-SQL → meetings →
  won) so conversions are honest despite lifecycle_stage storing only the current
  stage; step-to-step %, weakest step flagged, guarded under 10 leads.
- **Conversion paths** — aggregate first-touch → last-touch channel pairs for contacts
  on closed-won deals, via the tested attribution engine.
- **Recommendations** — every line cites the number that triggered it: the weakest
  step names its module fix (booking automation for SQL→meeting, scoring/nurture for
  lead→MQL), landing pages bouncing ≥70% get "bind an experiment + fix health checks"
  with their rate, and an idle funnel (published pages, zero running experiments) is
  called out. Too little data says so instead of guessing.

## Honest scoping

Nothing in this module is deferred any more. Session replay/recordings and
mouse-movement maps remain what a Hotjar/Clarity-class provider is for, named in the
notes — the rows never claimed them.

## Gate evidence

- Pest: **949 passed / 4,508 assertions** (+6:
  [CroCloseoutTest](../../tests/Feature/Qa/CroCloseoutTest.php)).
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
