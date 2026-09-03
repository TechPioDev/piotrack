# Module Completion Report — MSP Website Platform, Phase 23

**Date:** 4 September 2026 · **Module:** MSP Website Platform · **Register:** 45/55 Tested (82%), was 30/55 (55%)

## What shipped

**Experiments served on live pages (WEB-033/034/035/037)** — the named gap closed:
`experiments.site_page_id` binds a running experiment to a public page.
[PageExperiments](../../app/Services/Web/PageExperiments.php) assigns a sticky
per-visitor variant (30-day cookie), applies the variant's headline/subheadline
overrides **in-memory** (the stored page is the control and never mutates), and counts
the impression exactly once per visitor; a public form submission carrying the cookie
credits the conversion, org-checked so a rival tenant's cookie converts nothing.
Stage 11's tested math (conversion rate, lift, winner) now runs on live traffic. The
experiments screen binds pages and takes per-variant headline overrides; bound pages
serve `private, no-cache` so the split is never cached. Fixed en route: the
`ExperimentService::record` guard compared the *increment* (so `(0, 1)` conversion
crediting always threw) — it now guards the resulting totals.

**Gated lead magnets (WEB-022)** — a form whose `settings.lead_magnet_file_id` points at
a stored File answers submission with a **signed 7-day download URL** (never a public
path), `files.download_count` counts every delivery, and an unsigned request is refused
with 403.

**Publish-audit wiring (WEB-052/053)** — publishing a page runs the Stage 7
TechnicalSeoAuditor against its public URL (SSRF-guarded; skipped without ever blocking
the publish when the app URL is not fetchable), and `web:audit-published` re-audits
every published page daily at 04:30 per-tenant — after rank tracking at 04:00, so both
the mapped keywords and the pages themselves are monitored.

**Performance budget + caching (WEB-038/041/042/043)** — the budget is now **pinned by
test**: the rendered public page is a single server-rendered document under a hard
100KB ceiling, one inline stylesheet, no external CSS, and no executable JavaScript
(JSON-LD data blocks only; the chat widget and first-party pixel are opt-in,
same-origin, async/defer). Real HTTP caching: `public, max-age=300,
stale-while-revalidate=600` plus a content ETag answering `If-None-Match` with 304.
Fixed en route: the per-request CSP nonce was stamped on the JSON-LD tags, changing the
ETag every render and killing 304 revalidation — removed, since ld+json is a data block
CSP never executes.

**Template gallery (WEB-007/008/009/010)** — `SiteBuilderService::templates()` ships
four buyer-journey blueprints (MSP homepage, service page, vertical page, lead-magnet
landing), each an ordered hero → value → proof → FAQ → CTA/offer structure carrying
structural guidance copy (the keyword-library discipline: domain knowledge, never fake
proof or invented numbers). Applying one drafts the page + ordered sections for the
tenant to edit; the existing health checks still refuse to publish without real CTA and
proof content. Gallery UI on the Pages screen.

## Honest scoping — 10 rows stay

- **WEB-006** — cross-browser risk is low by construction (broadly supported CSS, no
  JS), but honest compatibility claims need a real browser matrix (BrowserStack-class).
- **WEB-036/054** — behaviour analysis/monitoring (recordings, heatmaps) need a
  behaviour-analytics provider; the first-party pixel records visits, not behaviour.
- **WEB-039/044** — Core Web Vitals and mobile speed are FIELD data (CrUX/PSI/RUM
  against production); the lab-side budget is what the codebase can pin, and it is.
- **WEB-040** — image pipeline/CDN transforms need an asset pipeline on the delivery
  path; section content is text-first today.
- **WEB-046/047/049/050** — maintenance, patching, uptime/perf monitoring are
  operational services on provisioned infra; the in-app share (health checks, daily
  audits) is Tested.

## Gate evidence

- Pest: **928 passed / 4,272 assertions** (+6:
  [WebsitePlatformCloseoutTest](../../tests/Feature/Qa/WebsitePlatformCloseoutTest.php)).
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
