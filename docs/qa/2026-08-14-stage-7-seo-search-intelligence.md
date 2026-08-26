# Module Completion Report — SEO & Search Intelligence (Stage 7)

Date: 2026-08-14
Spec: [docs/specs/stage-7-seo-search-intelligence.md](../specs/stage-7-seo-search-intelligence.md)
ADR: [ADR-0005 — SEO data-source abstraction](../architecture/adr/ADR-0005-seo-data-source-abstraction.md)
Scope: TSEO-001…027, KSEO-001…019, LSEO-001…022, AEO-001…019, GEO-001…016, LLMO-001…018 (121 features)

## Status summary

| Area                                                       | Result                                                                        |
| ---------------------------------------------------------- | ----------------------------------------------------------------------------- |
| On-page technical audit (13 checks, scored)                | Tested (TSEO-001/005/006/007/008/009/011/012/015/020/027)                     |
| Multi-page crawl / indexation / robots+sitemap / redirects | Partial — extend from the same auditor (TSEO-002/003/004/010/013/014/016/018) |
| Core Web Vitals, Search Console, penalty/backlink audit    | Planned — external APIs (TSEO-017/019/021/022/023/024/025/026)                |
| Keyword clustering + page mapping + content gap            | Tested (KSEO-013/014/015)                                                     |
| Rank tracking + competitor + page-one/top-three            | Tested on fixture driver (KSEO-016/017/018/019)                               |
| Keyword research / volume / difficulty                     | Partial — needs a keyword-data API (KSEO-001/002/009/010)                     |
| Locations + citations + NAP consistency                    | Tested (LSEO-011/012/018/019)                                                 |
| Local keywords / geo targeting / local rank                | Partial (LSEO-002/003/004/005/022)                                            |
| GBP / Maps / Map-Pack / reviews / local links              | Planned — GBP API + Stage 13 REP (LSEO-001/009/010/013/015/016/017)           |
| Answer/FAQ readiness scoring + schema JSON-LD              | Tested (AEO-002/003/005/008/009/010…016/017)                                  |
| AI-visibility (mention/citation/share) across engines      | Tested on fixture driver (GEO-001…010, AEO-018)                               |
| Machine-readability / semantic / structured-data scoring   | Tested (LLMO-001/002/003/011/017)                                             |
| Live SERP + live AI-engine data                            | Implemented — untested (no credentials)                                       |

Per ADR-0005, technical audit + schema + readiness + NAP are computed **in-house and Tested**; the
rank/AI-visibility _pipelines_ are Tested on the **fixture** drivers, while the real `serpapi`/`openai`
drivers are real code labelled _Implemented (untested — requires credentials)_, never "Tested" (§38).

## Architecture delivered

- **Data model** (7 tenant-scoped tables): `seo_audits`, `keywords`, `keyword_rankings`,
  `seo_locations`, `citations`, `ai_visibility_checks`, `structured_data`.
- **`TechnicalSeoAuditor`** — real on-page auditor: `Http` fetch + PHP `DOMDocument`/`DOMXPath`
  analysis of 13 weighted checks → 0–100 score + issue list; `analyze(html,url)` pure + directly
  tested, `crawl(url)` fetches then persists (fetch failure → recorded failed audit, no crash).
- **`SchemaGenerator`** — JSON-LD for Organization/LocalBusiness/Service/FAQPage/Article/Person/Review
  (null-omitting, valid); **`ContentReadinessScorer`** — machine-readability heuristic (semantic HTML,
  JSON-LD, headings, Q&A blocks, lists, depth); **`NapConsistencyChecker`** — normalizes case/
  punctuation/company-suffix/phone before comparing.
- **`KeywordService`** (clustering by primary token, content-gap) + **`RankTracker`** (history,
  current position, competitor, page-one/top-three) over the `RankProvider` abstraction (ADR-0005);
  **`AiVisibilityService`** over the `AiSearchProvider` abstraction. `SeoProviderManager` resolves
  `fixture` (tested) vs `serpapi`/`openai` (real, untested) via `config/seo.php`, bound in the container.
- **RBAC + entitlement**: 5 `seo.*` permissions (view / audits.manage / keywords.manage /
  local.manage / ai.manage) mapped across roles; TSEO/KSEO/LSEO gated by `entitlement:seo`, AEO/GEO/
  LLMO additionally by `entitlement:ai_visibility`.
- **Controllers + 20 routes** (`/seo/*`, `can:`- + entitlement-gated, tenant-scoped binding) +
  audit events (`seo.audit.run`, `seo.keyword.created`, `seo.rank.checked`, `seo.location.created`,
  `seo.citation.checked`, `seo.schema.generated`, `seo.ai.checked`).
- **UI**: sidebar **SEO** group; 7 Inertia/React pages (dashboard, audits index/show, keywords,
  local, ai-visibility, schema) with the audit report rendering per-check pass/warn/fail pills.

## Automated test results

- **Pest: 256/256 PASS** (873 assertions) — +27 SEO tests across 5 suites:
    - Technical audit (5): good page scores >90 / 0 issues; bad page flagged (title/meta/viewport/https/
      dual-H1); `crawl` persists a scored audit (Http::fake); fetch failure → failed audit; controller run + audit event.
    - Schema/readiness (5): Organization/FAQPage/LocalBusiness JSON-LD shapes + null omission; controller
      save; readiness scores good ≫ poor HTML.
    - Keyword/rank (6): add + dedupe; rank history + current position; competitor row; page-one/top-three
      flags; cluster + content gap; controller rank check.
    - Local/NAP (5): consistent (normalized) vs inconsistent (mismatched fields) vs missing; controller
      creates citation with NAP status; tenant isolation.
    - AI-visibility + access (6): fixture check records share; controller check; viewer read-vs-manage;
      `seo` feature gating; `ai_visibility` feature gating (Growth blocked); tenant isolation.
- PHPStan L6: 0 errors · Pint PASS · Prettier PASS · ESLint PASS · tsc PASS · `npm run build` PASS.

## Manual QA (browser, http://localhost:8734)

Logged in as an Owner on the **Professional** plan (so both `seo` + `ai_visibility` are entitled):

- SEO sidebar group + all six pages render.
- **Technical audit report** (the centerpiece) rendered a real DOM analysis: score **96**, HTTP 200,
  and all 13 checks with pass/warn pills — correctly flagging **"Canonical URL — warn: No canonical
  link"** while passing title (46 chars), meta description (102 chars), single H1, viewport, image
  alt, JSON-LD, Open Graph, lang, HTTPS and content depth (351 words).
- Keywords, AI Visibility (feature-gated, allowed on Professional) and Schema pages render with their
  forms + correct empty states.

> Auditing the app's own `localhost:8734` was avoided in QA: the single-process `artisan serve` would
> deadlock fetching itself. The auditor's live fetch path is covered by the `crawl` + `Http::fake`
> tests; the seeded audit above used the real `analyze()` on fixture HTML.

## Defects discovered & fixed

- PHPStan: `DOMXPath::query()` returns `DOMNodeList|false` (not nullable) — replaced nullsafe
  `?->length`/`?->item()` with explicit `!== false` handling via a `count()` helper across the auditor
  and readiness scorer.
- `SchemaGenerator` Review branch filtered array-only values against `''` (always-true) → filter on
  `!== null`; nested collection map in `LocalController` → `->all()`.

## Deferred (tracked in register, not dropped)

- Live SERP rankings (SerpApi) + live AI-engine visibility (OpenAI/Perplexity) — credentials; Core Web
  Vitals / site-speed (PageSpeed API); Search Console + GBP/Maps via INTG connectors; penalty +
  backlink audits (external link data); multi-page crawl at scale; keyword research/volume (keyword
  API); content/authority work (Stage 9), reviews (Stage 13 REP), knowledge-graph (later).

## Completion

**APPROVED — Stage 7 (SEO & Search Intelligence) gate passed.** piotrack now runs real on-page
technical audits, generates JSON-LD, scores machine-readability, manages keywords with clustering +
gap analysis, checks NAP consistency, and tracks keyword rankings + AI-engine visibility end-to-end on
tested fixture drivers — all tenant-scoped, RBAC- and plan-gated. Next per the phase plan: Stage 8 —
Advertising (PPC / LinkedIn / Meta / Retargeting).
