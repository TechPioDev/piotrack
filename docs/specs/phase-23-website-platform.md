# Phase 23 — MSP Website Platform close-out

Register target (13 rows): WEB-007/008/009/010 (template gallery), WEB-022 (gated lead
magnets), WEB-033/034/035/037 (experiments served on pages), WEB-038/041/042/043
(performance budget + caching), WEB-052/053 (publish-audit wiring).

Stay honest: WEB-006 (needs a real cross-browser matrix — BrowserStack-class),
WEB-036/054 (behaviour analytics provider), WEB-039/044 (CWV field data),
WEB-040 (asset pipeline/CDN), WEB-046/047/049/050 (operational services on
provisioned infra).

## Design

**Experiments served on pages (WEB-033/034/035/037)** — the named gap: "binding an
experiment variant to a specific site_page so traffic is split automatically".
`experiments.site_page_id` + `experiment_variants.content` (headline/subheadline/cta
overrides). The public page assigns a sticky variant per visitor (cookie), applies its
overrides, and counts the impression; a public form submission carrying the variant
cookie counts the conversion (org-checked). Stage 11's tested math (conversion rate,
lift, winner) now runs on live traffic.

**Gated lead magnets (WEB-022)** — a form whose `settings.lead_magnet_file_id` points at
a stored File delivers, after submission, a **signed** download URL (7-day expiry, no
guessable tokens); `files.download_count` tracks per-asset downloads.

**Publish-audit wiring (WEB-052/053)** — publishing a page runs the Stage 7 technical
auditor against its public URL (guarded: skipped when the app URL is not fetchable —
never blocks the publish), and `web:audit-published` re-audits all published pages daily
(scheduled 04:30, after rank tracking at 04:00 — rankings for mapped keywords were
already monitored; now the pages themselves are too).

**Performance budget + caching (WEB-038/041/042/043)** — the rendered public page gets a
pinned budget test (single document, no JavaScript beyond the opt-in chat widget, one
inline stylesheet, hard byte ceiling) and real HTTP caching: `Cache-Control` +
ETag/304 on public site pages.

**Template gallery (WEB-007/008/009/010)** — the named gap: "no template gallery ships
yet". `SiteBuilderService::templates()` ships four buyer-journey page blueprints (MSP
home, service page, vertical page, lead-magnet landing), each an ordered conversion
structure (hero → value → proof → FAQ → CTA/offer) with structural guidance copy —
the keyword-library discipline: domain knowledge, no fake data. Applying one creates a
draft page + sections for the tenant to edit; health checks already fail pages missing
CTA/proof.

## Tests (tests/Feature/Qa/WebsitePlatformCloseoutTest.php)

1. Variant serving: sticky assignment, override applied, impression counted once per
   visitor; conversion credited from the form submit; org isolation.
2. Lead magnet: signed link delivered post-submit, downloads counted, unsigned refused.
3. Publish triggers the audit (fetchable URL), skipped gracefully otherwise; the daily
   command audits published pages.
4. Budget: rendered page under the ceiling, no scripts, ETag/304 round-trip.
5. Templates: each creates the ordered draft structure and passes health checks.
