# Module Completion Report — LLM Optimization (LLMO), Phase 10

**Date:** 2 September 2026 · **Module:** LLM Optimization - LLMO · **Register:** 17/18 Tested (94%), was 5/18 (28%)

## What shipped

**Knowledge graph from first-party records** ([KnowledgeGraphService](../../app/Services/Seo/KnowledgeGraphService.php)):
one schema.org `@graph` assembled from data the platform already holds — Organization +
BrandProfile entity fields, active ServiceLines as a taxonomy, SeoLocations, and the new
`expert_profiles`. Every node carries an `@id`; relationships are `@id` references
(provider, worksFor, parentOrganization, areaServed, knowsAbout). Nothing external,
nothing invented. `publish()` stores it as a `StructuredData` row (`KnowledgeGraph`) the
schema page lists for embedding, with an audit entry.

**Entity data**: `brand_profiles` gained legal_name, alternate_names, website_url,
logo_url, founded_year, same_as, disambiguation (LLMO-005/010); new tenant-scoped
`expert_profiles` table carries credentials/knows_about/sameAs — E-E-A-T signals rendered
as `hasCredential` + `knowsAbout` (LLMO-007/008).

**Retrieval-readiness audit** (LLMO-018): `completeness()` — seven deterministic checks
(org anchor, disambiguation, alternate names, service taxonomy, location NAP, expert
credentials, relationship edges), each failing item naming its fix.

**LLMO content scoring** ([LlmoContentScorer](../../app/Services/Seo/LlmoContentScorer.php)):
ContentReadinessScorer's six factors plus citations (LLMO-012), concise definitions
(LLMO-013) and fact density (LLMO-014) — all DOM/regex heuristics.

**Surface**: `/seo/llmo` (sidebar → SEO → LLMO; `ai_visibility` entitlement, seo.view /
seo.ai.manage) — readiness checklist, entity form, expert CRUD, live JSON-LD preview +
publish, paste-in content scorer.

## Honest scoping

- **LLMO-015 (Original data) stays Planned** — original research/data is tenant authoring
  work; the facts factor rewards it once present. No platform feature can generate
  original data honestly.
- The graph reflects records the tenant maintains; empty sections simply produce fewer
  nodes, never placeholders.

## Gate evidence

- Pest: **868 passed / 3,801 assertions** (+5 tests, +71 over Phase 9), including
  [LlmoKnowledgeGraphTest](../../tests/Feature/Qa/LlmoKnowledgeGraphTest.php) (5 tests:
  graph assembly + relationships, readiness audit both directions, scorer determinism,
  HTTP CRUD + publish + audit, tenant isolation + permission gating).
- Pint clean · PHPStan **0 errors** — including 9 latent findings in earlier modules
  surfaced by this phase's fuller analysis (redundant nullsafes, a dead null-check in
  EmailTrackingService, missing WebhookEndpoint property types) — all fixed, not
  suppressed.
- Prettier/ESLint/tsc clean · Vite build ok · Vitest 43 passed.
- Register updated: LLMO 17 Tested / 1 Planned with per-row evidence notes.
