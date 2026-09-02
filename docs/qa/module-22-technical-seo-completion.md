# Module Completion Report — Technical SEO, Phase 11

**Date:** 2 September 2026 · **Module:** Technical SEO · **Register:** 22/27 Tested (81%), was 11/27 (41%)

## What shipped

**Bounded site crawler** ([SiteCrawler](../../app/Services/Seo/SiteCrawler.php)): the
single-URL auditor's philosophy — PHP DOM + Laravel Http, no crawler SaaS (ADR-0005) —
extended into a same-host BFS crawl (20-page budget). robots.txt and sitemap.xml are
fetched first (sitemap URLs join the queue, which is how orphans become discoverable);
every URL passes the SSRF `UrlGuard`; redirects are recorded, never blindly followed.

The report is ten deterministic sections, each item a sentence naming its page:
indexation (noindex, cross-URL canonicals — TSEO-003), robots.txt findings (TSEO-014),
robots-blocked-but-linked URLs (TSEO-004), sitemap gaps (TSEO-013), orphans and dead
ends (TSEO-010), duplicate titles/descriptions/content hashes (TSEO-016), broken
internal links with sources (TSEO-017), redirect chains and loops (TSEO-018), HTML-weight
heuristics (TSEO-021), and click-depth architecture (TSEO-022). Only verified facts are
claimed — a sitemap URL the budget never reached is "not crawled", never "broken".

**Storage/surface**: tenant-scoped `site_crawls` table; crawl form + history on
`/seo/audits`, full report at `/seo/audits/crawl/{id}` with a per-page table
(status, depth, inlinks/outlinks, HTML KB). SSRF refusals surface as validation
errors, not 500s.

## Honest scoping — 5 rows stay Planned

- **TSEO-019 (Core Web Vitals)** — needs Chrome UX/PageSpeed field data (blocked
  outbound 443); the report says so explicitly rather than faking it with lab guesses.
- **TSEO-023 (Search Console)** — needs the GSC OAuth connector (vendor app).
- **TSEO-024/025 (penalty audit/recovery)**, **TSEO-026 (backlink audit)** — need
  external link/penalty data (GSC/Ahrefs).

## Gate evidence

- Pest: **873 passed / 3,867 assertions** (+5 tests over Phase 10), including
  [TechnicalSeoCrawlTest](../../tests/Feature/Qa/TechnicalSeoCrawlTest.php) — a fake
  site with one planted defect per section (noindex page, robots-blocked link, sitemap
  gap, sitemap-only orphan, dead end, duplicate titles/descriptions, 404 link, redirect):
  every defect found in its own section, nothing invented, budget enforced, SSRF start
  URL refused, tenant isolation + permission gating pinned.
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 43.
- Register: Technical SEO 22 Tested / 5 Planned with per-row evidence notes.
