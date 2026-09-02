# Phase 10 — LLM Optimization close-out: knowledge graph + LLMO content scoring

Register target: LLMO-004..010, 012, 013, 014, 016, 018 (12 rows). LLMO-015 (original
data) remains Planned — producing original research/data is tenant authoring work; the
scorer rewards it once present.

## Design

Everything is assembled from first-party records the platform already holds — no external
service, no invented entity data:

| Entity | Source |
| --- | --- |
| Organization | `Organization` + `BrandProfile` (new entity fields) |
| Services + taxonomy | `ServiceLine` (name, category, description) |
| Locations | `SeoLocation` (full NAP) |
| People / experts | new `expert_profiles` table (name, title, bio, credentials, sameAs) |

### New schema

- `brand_profiles` gains entity fields: `legal_name`, `alternate_names` (json),
  `website_url`, `logo_url`, `founded_year`, `same_as` (json), `disambiguation` (text).
  Brand disambiguation (LLMO-010) is literally a brand concern, so it lives on the brand
  profile rather than a new table.
- `expert_profiles`: tenant-scoped person entities with `credentials` (json list) and
  `knows_about` (json list) — the E-E-A-T signals LLMs read (LLMO-007/008).

### KnowledgeGraphService (`app/Services/Seo/KnowledgeGraphService.php`)

- `build()` — a single schema.org `@graph` where every node carries an `@id` and
  relationships are expressed as `@id` references: services `provider` → org, locations
  `parentOrganization` → org, people `worksFor` → org, org `knowsAbout` service names,
  services `areaServed` city names (LLMO-004/005/006/009/016).
- `completeness()` — deterministic checklist scoring the graph's retrieval readiness
  (org info, disambiguation, service taxonomy, locations, expert credentials,
  relationship count) with a fix hint per failing item (LLMO-018).
- `publish()` — stores the graph as a `StructuredData` row (`schema_type:
  KnowledgeGraph`) so the existing schema page lists it and the tenant embeds it.

### LlmoContentScorer (`app/Services/Seo/LlmoContentScorer.php`)

Wraps ContentReadinessScorer's six factors and adds three LLMO-specific ones, all
deterministic DOM/regex heuristics — no LLM call, no invented numbers:

- `citations` — outbound source links / cite/blockquote present (LLMO-012)
- `definitions` — a concise "X is/are/means/refers to" definition or `<dfn>` (LLMO-013)
- `facts` — numeric fact density: ≥5 figures (%, $, counts, years) (LLMO-014)

### Surface

`/seo/llmo` (entitlement `ai_visibility`, `seo.view` / `seo.ai.manage`): completeness
audit, brand entity form, expert profile CRUD, live JSON-LD graph preview + publish,
paste-in content scorer. Routes in `routes/seo.php` inside the ai-visibility group.

## Tests (`tests/Feature/Qa/LlmoKnowledgeGraphTest.php`)

1. Graph assembles all four entity types with `@id` cross-references from first-party data.
2. Disambiguation fields flow through; completeness flags a missing/weak entity.
3. Expert CRUD renders credentials as `hasCredential` + `knowsAbout`.
4. Scorer factors pass/fail deterministically on crafted HTML.
5. Publish creates the StructuredData row; audit logged.
6. Tenant isolation + permission gating on every endpoint.
