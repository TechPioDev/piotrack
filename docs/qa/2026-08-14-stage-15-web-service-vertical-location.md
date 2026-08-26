# Module Completion Report — Website, Service Lines, Verticals & Multi-Location (Stage 15)

Date: 2026-08-14
Spec: [docs/specs/stage-15-web-service-vertical-location.md](../specs/stage-15-web-service-vertical-location.md)
Scope: WEB-001…055, SVC-001…024, VERT-001…020, MLOC-001…012 (111 features)

## Why this stage exists

These four modules were never scoped into Stages 6–13. The phase plan said they would "distribute
across Stages 6–11", no stage spec ever claimed them, and I did not catch it until the Stage 14
readiness audit. This stage closes that gap so the register can be read honestly.

## Status summary

| Module                                | Tested | Partial | Planned |
| ------------------------------------- | -----: | ------: | ------: |
| WEB — MSP Website Platform (55)       |     30 |      15 |      10 |
| SVC — Service-Specific Campaigns (24) |     24 |       — |       — |
| VERT — Vertical Marketing (20)        |     14 |       6 |       — |
| MLOC — Multi-Location Support (12)    |      5 |       6 |       1 |
| **Total (111)**                       | **73** |  **27** |  **11** |

## Architecture delivered

- **Data model** — `service_lines`, `verticals`, `site_pages`, `page_sections`, `site_navigation`, and
  `seo_locations` extended with `territory` / `gbp_place_id` / `is_active` (MLOC extends the Stage 7
  location record rather than introducing a second notion of "location").
- **`SiteBuilderService`** — typed pages (service / vertical / location / landing / campaign / resource)
  built from ordered section blocks, navigation, and publish/unpublish. Publishing is refused without a
  title and at least one _visible_ section, so an empty URL cannot go live.
- **`SiteHealthService`** — eight checks per page read from real content (meta title, meta description
  length, headline, visible sections, a CTA, a wired conversion path, third-party proof, published
  state). **A check with no data fails rather than passing by default**, so a blank page scores near
  zero instead of looking healthy; the site rollup lists the weakest pages first.
- **`TaxonomyService`** — the 24 MSP service lines and 12 verticals (with their compliance framing)
  provisioned with every organization, idempotently, plus coverage reporting that counts the pages,
  keywords, campaigns and content addressing each — weakest first, so gaps are the headline.
- **`LocationService`** — branches with territories, their location pages, and per-branch rollups of
  leads, SQLs and won value.
- **Public rendering** at `/s/{slug}`: server-rendered, mobile-first, no JavaScript, `prefers-color-scheme`
  aware. Draft pages 404 so an unpublished URL cannot be guessed.
- 15 routes, 3 `web.*` permissions, 2 Inertia pages + a Website sidebar group.

## The honest framing for SVC and VERT

The 24 services and 12 verticals are a **taxonomy with real targeting**, not 36 shipped campaigns. What
is built and tested: the records are provisioned per tenant, pages bind to them, and coverage is
reported across pages/keywords/campaigns/content. What is _not_ claimed: the campaign creative and copy
for "SOC" or "Healthcare" — that is human marketing work. Each register note says exactly this.

Where an earlier stage already does the work, the row cross-references it instead of double-claiming:
A/B, multivariate, CRO and funnel optimization → Stage 11's experiment engine (measured, **not yet
served** — no variant-to-page traffic split); SEO and ranking monitoring → Stage 7; local SEO/NAP →
Stage 7; local PPC → Stage 8; vertical content/ads/sequences/ABM → Stages 6/8/9/10.

## Defects discovered & fixed

- **One tenant's page could shadow another's on the public URL space.** `site_pages.slug` was unique
  _per tenant_, but `/s/{slug}` is a single global URL space — two organizations naming a page
  "Managed IT" collided and the second became unreachable. Caught by a test written specifically to
  check it. Slugs are now globally unique (the convention booking pages and public forms already used)
  with collisions auto-suffixed, and the test asserts both pages resolve to their own tenant.
- **Pages could not be re-targeted after creation.** `update` accepted no `service_line_id`,
  `vertical_id`, `seo_location_id` or `type`, so a coverage gap could only be closed by creating a _new_
  page — which defeats the purpose of the coverage report. Raised during UI review; now editable, with a
  test that re-targets a page and asserts the coverage count moves.
- **A navigation item could be created with no destination** (both `site_page_id` and `url` nullable),
  rendering a dead link. Now `required_without` each other, with a test.

## Automated test results

**Pest 485/485 PASS** (1586 assertions) · PHPStan L6 0 errors · Pint · Prettier · ESLint · tsc ·
`npm run build`. +17 Stage 15 tests: taxonomy seeding + idempotence; page build, slug collision,
section add/reorder/type validation; publish refused when empty or only hidden sections; publish /
unpublish; public render with view counting; draft 404; health scoring both directions incl. the
failing-check list; weakest-first rollup; coverage reporting; location attribution through company
address incl. the deliberately unattributed case; location page linkage; re-targeting; dead-link
refusal; RBAC; tenant isolation; and the URL-shadowing regression.

## Manual QA

Verified through the test suite rather than a browser (browser login needs a password, outside the
assistant's allowed actions). That covers the substance: the public page renders its real headline and
CTA and increments its view count, drafts 404, health scores move with content, and two tenants' pages
resolve independently. Both UI pages type-check and lint clean.

## Deferred (tracked in register, not dropped)

- Core Web Vitals and mobile speed scores — field data (CrUX / PageSpeed / RUM); estimating them would
  be fabrication.
- Image/CDN optimization and edge caching for tenant page delivery.
- Behaviour analytics (heatmaps, session recordings) — provider + tracking script.
- Uptime/patching/maintenance monitoring — needs provisioned hosting.
- Serving A/B variants on a live page (the engine measures; it does not yet split traffic).
- Explicit vertical foreign keys on content/ads/sequences (coverage is name-matched today).
- Live Google Business Profile API for managing multiple branch profiles.
- Franchise support — needs a parent/child organization hierarchy the tenancy model does not have.

## Completion

**APPROVED — Stage 15 gate passed.** Every module in the Feature Traceability Register has now been
scoped and worked. The register no longer contains a module that was silently skipped.

**This does not make the product production-ready** — the Stage 14 audit's other blockers stand: no
infrastructure has been provisioned, Stripe is unverified against live keys, no DR drill has run, and no
load or accessibility audit has been performed.
