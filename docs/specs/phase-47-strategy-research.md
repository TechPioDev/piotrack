# Phase 47 — Marketing Strategy & Research close-out (STRAT-005/007/008/009/014/015/016)

**Goal: Marketing Strategy & Research 25/32 → 32/32 (100%).**

## The stale-deferral insight

All seven open rows carry Stage-13-era notes calling the work "qualitative/human". Those notes
predate the material the platform now holds: visitor chat questions (P44), review + social
sentiment (P17/P46), buying roles (P26), the vertical messaging framework (P39), service-line
bindings on pages/ads/assets (P23/P36/P44), the measured funnel chain, and two proven honest-close
patterns — the server-computed calculator (P44 ROI) and evidence-beside-narrative (P22 brand
positioning). Each row closes with the platform doing real, computable analysis; where narrative
is genuinely human it is authored against computed first-party evidence, never replaced by it.

## Register rows

| Row | Close |
| --- | --- |
| STRAT-005 TAM | `MarketSizingService` — TAM/SAM/SOM calculator: market count entered by the rep, avg MRR and win rate **derived from real won-deal records** when available (provenance per input: entered / derived / default), all math server-side, saved as an auditable `StrategyItem` (type research). P44 ROI precedent. |
| STRAT-007 Personas | `buyer_personas` table + CRUD + `PersonaEvidenceService` — buying-role distribution from real contacts, top titles at won-deal companies, top visitor questions from chat. Narrative fields stay rep-authored, written against computed evidence. |
| STRAT-008 Pain points | `PainPointResearchService` — deterministic keyword theme buckets scanned over visitor chat messages, negative reviews, negative social interactions (P46) and open tickets; per-theme counts, source-labeled sample quotes, provenance line. Heuristic stated as such. |
| STRAT-009 Journey map | `JourneyMapService` — awareness/consideration/decision/customer stages, each measured: real funnel numbers, published content per tof/mof/bof stage, conversion rates, avg days-to-close from real won deals. |
| STRAT-014 Service-line opportunity | migration adds `deals.service_line_id` (P39 hard-binding); `ServiceLineOpportunityService` — per line: won MRR, open pipeline, win rate + coverage (pages, ad campaigns, sales assets) + recommendations citing their numbers (P43 pattern). Deal form gains the select. |
| STRAT-015 Positioning research | `PositioningResearchService` — angles composed from the tested competitive landscape (share of voice, head-to-head, AI share), P22 verified differentiators, and recorded competitor messaging; every angle cites its numbers, marked lead/gap. |
| STRAT-016 Messaging analysis | `MessagingAnalysisService` — brand USP/value-prop/differentiators + per-vertical P39 frameworks checked for presence across published content, published site pages and sent campaigns via a transparent significant-word heuristic (stated in the UI); per-element coverage + published assets carrying no messaging. |

## Build

- Migration `2026_09_08_200001_add_strategy_research.php`: `buyer_personas`
  (org-scoped narrative fields) + `deals.service_line_id` (nullable, nullOnDelete).
  MySQL-safe identifier lengths.
- `app/Models/BuyerPersona.php`; `Deal::serviceLine()`.
- Seven services under `app/Services/Strategy/` as above.
- `app/Http/Controllers/Strategy/ResearchController.php`: research page props +
  `storeTam` + persona CRUD; routes in the delivery strategy group
  (view: GET research; manage: POST tam, personas).
- `DealController::validateData` += `service_line_id`; deals page select.
- `resources/js/pages/strategy/research.tsx` + sidebar entry.
- `tests/Feature/Qa/StrategyResearchCloseoutTest.php` (~6 tests).

## Honesty lines

- TAM: the market count is always human-entered — the platform never invents market data; it
  contributes the derivation of the tenant's own economics and the auditable math.
- Pain/messaging analysis: keyword heuristics stated as heuristics in the UI.
- Empty states: no signals → zeros and "no signal yet" lines, never invented findings.
- Persona narrative, interview findings and positioning prose remain human; the platform's
  contribution is the evidence beside them.
