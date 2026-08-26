# ADR-0005 — SEO data-source abstraction (rank + AI-search) with working fixture drivers

**Status:** Accepted (2026-08-14) · Enables Stage 7 (SEO & search intelligence: KSEO, LSEO, AEO, GEO, LLMO) and Master Prompt §16, §44, §38.

## Context

Search-intelligence features need data that only external services can provide: **SERP rankings**
(Google position for a keyword) and **AI-search visibility** (whether ChatGPT/Gemini/Perplexity/AI
Overviews mention or cite a brand for a prompt). Real data requires paid APIs — a SERP provider
(SerpApi/DataForSEO/Semrush) and LLM APIs (OpenAI/Anthropic/Perplexity) — with accounts and keys.
This environment has **none of those credentials**, and Master Prompt §38 forbids presenting fabricated
rankings/visibility as real; we must separate what is Tested from what needs credentials.

By contrast, **technical on-page SEO** (crawling a URL and analysing its HTML) and **structured-data
generation** need no third party — they are computed from the page itself and are built for real.

## Decision

**Technical SEO and schema generation are computed in-house and fully tested.** The `TechnicalSeoAuditor`
fetches a URL (Laravel HTTP client) and analyses the HTML with PHP's built-in `DOMDocument`/`DOMXPath`
— no crawler SaaS. `SchemaGenerator` emits JSON-LD from our own entity data. `NapConsistencyChecker`
and `ContentReadinessScorer` are pure functions over our data / the parsed page. All Tested.

**External-data features go through narrow provider interfaces — never a vendor SDK directly:**

- `RankProvider` → `RankResult{position, url}` for (keyword, domain, engine, location).
- `AiSearchProvider` → `AiVisibilityResult{mentioned, position, citedSources, competitors}` for
  (prompt, brand).

Drivers are selected by config (`SEO_RANK_PROVIDER`, `SEO_AI_PROVIDER`):

1. **`fixture` (default, fully working & tested)** — deterministic results derived from a hash of the
   inputs. This is **not a stub of the pipeline**: keyword/rank history, page-one/top-three flags,
   share-of-answer, competitor comparison, snapshots-over-time and all reporting run for real against
   the fixture driver. It is the driver used in development and tests, and a legitimate "manual /
   bring-your-own-data" mode (ranks can also be entered by hand).

2. **`serpapi` / `dataforseo` (rank) and `openai` / `perplexity` (AI)** — real drivers implementing the
   same interface over the vendor HTTP APIs. Real code, but with no credentials here they are **not run
   in tests**; register status _Implemented (untested — requires credentials)_, never "Tested."
   Activated by config + keys.

A manager resolves the active driver so type-hinting the interface yields it (mirrors
`PaymentProviderManager` / `MessagingProviderManager`). Google Business Profile / Search Console data
(LSEO/TSEO-023) arrive through the **INTG** connector framework when those OAuth connectors land, and
are surfaced as "coming soon" until then.

## Consequences

- The genuinely computable half of SEO — technical audits, schema/JSON-LD, NAP consistency, content
  readiness, keyword inventory + clustering + mapping + gap analysis, and the whole rank/AI-visibility
  _pipeline_ — is real and tested with no SEO/LLM account.
- Turning on live rankings / AI visibility is configuration + credentials, not a rewrite (§16, §44).
- We stay honest per §38: computed checks and fixture-driven flows are Tested; live SERP/LLM data is
  labelled untested until keys exist.
