# Phase 27 — Conversion Rate Optimization close-out

Register target (7 rows): CRO-010 (heatmaps), CRO-011 (user behavior analysis),
CRO-012 (funnel analysis), CRO-013 (conversion-path analysis), CRO-014 (bounce-rate
optimization), CRO-015 (meeting conversion optimization), CRO-016 (lead conversion
optimization).

## The gap, honestly stated

CRO-010/011/014 were deferred as "require a behaviour-analytics provider + the web
tracking pixel" — but the first-party pixel SHIPPED with Visitor Intelligence
(`/t/{key}.js`, `visitor_events` per visitor with paths and timestamps). What a
provider adds beyond that is session *replay*; click heatmaps, scroll-depth maps,
per-page behavior and bounce rates are first-party data the pixel can capture itself.
CRO-012/013/015/016 were Partial pending "step-level drop-off visualisation" — a
computation and a view over records the platform already holds.

## Design

**Pixel capture (feeds 010/011/014)** — the ~1KB script gains two event types, still
cookieless-beacon, same endpoint, same validation discipline:

- `click`: `x_pct` (0–100 of viewport width), `y_pct` (0–100 of document height), and
  the clicked element's short label (tag + trimmed text) in `title`.
- `scroll`: max scroll depth as `y_pct`, sent once per page on `pagehide`.

`visitor_events` gains nullable `x_pct`/`y_pct`; ingest stores them (clicks/scrolls
never bump page_views or fire pageview scoring).

**BehaviorAnalytics service** (`app/Services/Analytics/BehaviorAnalytics.php`):

- `heatmap(path)`: 10×10 click-density grid + top click targets by label + click count
  + scroll-depth distribution (quartile buckets) for one page.
- `pages()`: per-path behavior table — pageviews, unique visitors, clicks,
  average scroll depth.
- `bounceRates()`: sessions rebuilt per visitor from pageview timestamps (30-minute
  gap = new session, matching the tracker), landing path = the session's first path;
  bounce = single-pageview session. Guarded: a landing path under 5 sessions reports
  `insufficient` instead of a rate.

**FunnelInsights service** (CRO-012/013/015/016):

- `dropOff()`: cumulative funnel — leads (all contacts) → reached MQL+ → reached SQL+
  → meetings (bookings) → closed-won — with step-to-step conversion %, the weakest
  step flagged, guarded under 10 leads.
- `conversionPaths()`: aggregate first-touch → last-touch channel pairs for contacts
  on closed-won deals (the Stage 11 attribution engine supplies the touches).
- `recommendations()`: named platform actions derived from the data that triggered
  them — weakest funnel step → the module that works that step (experiments on pages,
  booking automation, lead-source doubling-down), high-bounce landing pages → bind an
  experiment / fix the page's health checks. Every recommendation cites its number;
  insufficient data says so.

**UI** — new `analytics/behavior` page (path picker, click-density grid, top targets,
scroll depth, bounce table, an explicit "first-party pixel; session replay needs a
provider" note) and a funnel-insights section on the analytics dashboard (step bars
with conversion %, weakest-step callout, paths table, recommendations).

**Stays honest** — session replay/recordings and mouse-movement maps remain
provider-gated and are named in the notes; nothing here claims them.

## Tests (tests/Feature/Qa/CroCloseoutTest.php)

1. The served pixel carries click + scroll capture; the endpoint ingests both with
   coordinates and rejects out-of-range values.
2. Heatmap: seeded clicks land in the right grid buckets with top targets; scroll
   depths bucket correctly.
3. Bounce: sessionization by 30-minute gaps, landing-path attribution, bounce vs
   non-bounce, and the under-5-sessions guard.
4. Drop-off: cumulative counts, step conversions, weakest step, under-10-leads guard.
5. Conversion paths aggregate first→last touch pairs for won deals only.
6. Recommendations cite the triggering numbers and stay honest on an empty org.
