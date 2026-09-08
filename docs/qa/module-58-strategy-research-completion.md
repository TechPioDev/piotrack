# Module Completion Report — Marketing Strategy & Research

**Phase 47 · 2026-09-08 · Register: STRAT-005, STRAT-007, STRAT-008, STRAT-009, STRAT-014, STRAT-015, STRAT-016 → Tested (module 32/32, 100%)**

## Scope

The seven "Research and assessment" rows Stage 13 parked as qualitative/human. The
deferral notes predate the material the platform now holds — visitor chat questions
(P44), review and social sentiment (P17/P46), buying roles (P26), the vertical
messaging framework (P39), service-line bindings (P23/P36/P44), the measured funnel
chain — and the two proven honest-close patterns: the server-computed calculator
(P44 ROI) and evidence-beside-narrative (P22).

## What shipped — a Research workspace (`/strategy/research`)

- **TAM/SAM/SOM (STRAT-005)** — `MarketSizingService`: the market count is always
  human-entered (the platform never invents market data); average MRR and win rate
  derive from real won-deal records when left blank, every input carries provenance
  (entered / derived_from_records), the math runs server-side, and each run is stored
  as an auditable research `StrategyItem` with the full derivation.
- **Personas (STRAT-007)** — `buyer_personas` CRUD; the narrative is rep-authored
  beside a computed evidence panel: buying-role distribution from real contacts, top
  titles at won-deal companies, real visitor questions from chat.
- **Pain-point research (STRAT-008)** — `PainPointResearchService`: six deterministic
  keyword themes scanned over visitor chat, negative reviews, negative social
  interactions and open tickets, with per-source counts, source-labeled quotes, and
  the heuristic stated as such.
- **Journey map (STRAT-009)** — `JourneyMapService`: four stages, each measured —
  real funnel counts, published tof/mof/bof content coverage, stage conversion rates,
  average days-to-close from real won deals.
- **Service-line opportunity (STRAT-014)** — `deals.service_line_id` (hard binding,
  set on the deal board), `ServiceLineOpportunityService`: per-line won MRR, open
  pipeline, win rate + page/campaign/collateral coverage, recommendations citing
  their numbers, and an unbound-deal count instead of guesses.
- **Positioning research (STRAT-015)** — `PositioningResearchService`: angles from
  share of voice, keyword head-to-head, AI answer share and P22 differentiator
  reality checks (ownable vs table-stake), each citing its numbers, marked lead/gap.
- **Messaging analysis (STRAT-016)** — `MessagingAnalysisService`: brand USP /
  value proposition / differentiators + per-vertical frameworks checked across
  published content, published pages and sent campaigns via a transparent
  significant-word presence heuristic; per-element coverage plus the published
  assets carrying no messaging at all.

## Gate results

| Check | Result |
| --- | --- |
| `vendor/bin/pint --test` | pass |
| `phpstan analyse` (1G) | 0 errors |
| `npm run format:check` / `npx eslint .` / `npm run types` | pass |
| `npm run build` | pass |
| `npm run test` (Vitest) | 55 passed |
| `php vendor/bin/pest` | **1,044 passed, 5,336 assertions** |

New: `tests/Feature/Qa/StrategyResearchCloseoutTest.php` (6 tests, 63 assertions,
first-run pass) — TAM provenance + exact math + auditable artefact + override
labeling; personas with evidence panel and the cross-tenant fence; pain themes with
labeled quotes across all four signal sources; the measured journey incl. 30-day
time-to-close; service-line analysis on form-bound deals with number-citing
recommendations; positioning angles and messaging coverage incl. the orphan asset.

## Register effect

7 rows → Tested. Marketing Strategy & Research **32/32 (100%)** — the 47th complete
module. Global: **1,111/1,190 buildable Tested (93.4%)**.
