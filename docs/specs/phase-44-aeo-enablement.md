# Phase 44 — AEO + Sales Enablement closeout

**Register targets:** AEO-001, AEO-004, AEO-006, AEO-007, AEO-019 (AEO 73.7% → 100%);
ENAB-008, ENAB-014, ENAB-015, ENAB-016, ENAB-018 (Sales Enablement 73.7% → 100%).

## AEO position

Stage 7 shipped the question inventory (the AiPrompt library with category/service/city/
vertical facets) and the readiness/schema scorers (ContentReadinessScorer,
LlmoContentScorer, KnowledgeGraphService). The five open rows are the optimization layer
over that machinery — all first-party:

- **AEO-001 Question research** — mine real questions the tenant already receives: chat
  messages ending in "?", and tracked keywords with question modifiers; suggest ones not
  yet in the prompt library, one-click add.
- **AEO-004 Featured-snippet targeting** — per published page: question headings with a
  snippet-length answer directly after (≤320 chars), list/steps presence — the format
  Google lifts; failures named per page.
- **AEO-006 Conversational-query optimization** — inventory analysis: conversational
  share (who/what/how/…), long-tail share, per-category coverage, with counts.
- **AEO-007 Entity optimization** — rides `KnowledgeGraphService::completeness()`
  (sameAs, alternates, credentialed experts, categorized services, full-NAP branches),
  surfaced as the entity checklist.
- **AEO-019 AI-Overview optimization** — LlmoContentScorer across published pages:
  extractable/structured/sourced content is what AI Overviews cite; per-page scores with
  the weakest pages named. Live AI-Overview presence stays with the AI-visibility
  fixture/engine seam (unchanged).

## ENAB position

- **ENAB-008 ROI calculator** — computed server-side (employees, downtime cost/hours,
  IT spend → exposure, managed cost, net benefit, ROI ratio) and saved as a
  `roi_calculator` SalesAsset snapshot; interactive form on the enablement page.
- **ENAB-014 Proposal templates** — a `proposal_template` asset type with merge fields;
  generate-for-deal renders {{company}}/{{contact}}/{{deal_name}}/{{deal_value}}/
  {{services}} into a stored `proposal` asset tagged to the deal. (The AI outline from
  P25 remains available separately.)
- **ENAB-015/016 Vertical/service collateral** — the P39 binding pattern:
  `sales_assets.vertical_id` + `service_line_id`, selects + filters in the library.
- **ENAB-018 Sales training** — stale deferral: the TRAIN LMS shipped at 100% in P15
  with a seeded sales course ("Sales process on the pipeline"); closes on that
  evidence.

## Build

1. Migration: `sales_assets.vertical_id` + `service_line_id` (nullable FKs).
2. `App\Services\Seo\AnswerEngineOptimizer` (mineQuestions, snippetTargets,
   conversationalCoverage, aiOverviewReadiness) + panels on the LLMO page + add-question
   endpoint.
3. `EnablementController`: roi (computed store), proposal generation for a deal,
   types += proposal_template/proposal, binding validation + props; enablement page UI.
4. Tests `tests/Feature/Qa/AeoEnablementCloseoutTest.php` (~5).

## Out of scope (unchanged)

Live SERP/AI-Overview presence data (AI-visibility engine seam), LMS beyond TRAIN.
