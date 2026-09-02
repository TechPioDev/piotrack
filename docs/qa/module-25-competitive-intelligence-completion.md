# Module Completion Report — Competitive Intelligence, Phase 14

**Date:** 3 September 2026 · **Module:** Competitive Intelligence · **Register:** 7/13 Tested (54%), was 6/13 (46%)

## What shipped — CINT-005, the one honestly buildable row

**Competitor content monitoring without a data vendor**
([CompetitorContentMonitor](../../app/Services/Analytics/CompetitorContentMonitor.php)):
the same ADR-0005 philosophy the Phase 11 crawler established — a competitor's published
content lives on their public website, so monitoring it needs a fetch, not a provider.

- Discovery: their `sitemap.xml` first (15-page cap), homepage links as the fallback.
- Capture: url + title + normalized content hash per page; unreachable pages are
  recorded as unfetchable, an unreachable site yields an empty snapshot — never
  fabricated activity. Every fetch passes the SSRF `UrlGuard`.
- Each check stores the diff against the previous capture: **new**, **changed**
  (same URL, different content hash) and **removed** pages. The first capture is the
  baseline and is "new" exactly once.
- Surface: **Check content** per competitor on /analytics/competitors, with a content
  column showing pages captured, when, and new/changed/removed badges. Audited
  (`analytics.competitor.content_checked`), permission-gated
  (`analytics.competitors.manage`), guard refusals surface as validation errors.

## Why this phase closes only one row

The six remaining rows were scoped at Module 08 as **provider-gated and not faked**, and
that stands — each register note now names its exact unblock: ad libraries
(CINT-002/003 — Meta Ad Library / Google Ads Transparency), a backlink index
(CINT-004 — Ahrefs/SEMrush class), Google Places (CINT-006), review-source APIs
(CINT-007) and social APIs (CINT-009) — all additionally behind the blocked outbound
443. Inventing competitor ad spend, backlinks, map ranks, reviews or social numbers
would be worse than the gap.

## Gate evidence

- Pest: **884 passed / 3,936 assertions** (+5:
  [CompetitorContentMonitorTest](../../tests/Feature/Qa/CompetitorContentMonitorTest.php)
  — baseline capture, exact three-way diff via response sequences, sitemap-less
  fallback, SSRF refusal as validation error, HTTP + audit + permission + tenant
  isolation).
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
