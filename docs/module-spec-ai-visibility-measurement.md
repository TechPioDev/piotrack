# Module Specification — Measurable AI Visibility (AIVM, "Module 07")

> Approved 2026-08-28 ("next" — third competitive spearhead). Jumpfactor sells AEO/GEO as its
> flagship ("be the answer ChatGPT gives, tracked to MRR"). Our dashboards exist; what's missing is
> measurement you can trust: engine-specific checks, stored evidence, and honest labeling of what
> is live versus simulated.

## Purpose

Make every AI-visibility number traceable: each check stores the **answer excerpt it was computed
from** (the receipt), engines run through **engine-specific drivers** (ChatGPT via the OpenAI API,
Gemini via the Gemini API — Http::fake-verified mechanisms, live once a key exists), analysis is a
single tested `AnswerAnalyzer` instead of per-driver guesswork, and the UI labels each engine live or
simulated so a fixture number can never be mistaken for a market finding.

## Feature IDs

AIVIS-002 (ChatGPT) and AIVIS-003 (Gemini) → **Tested** (mechanism; live requires key + outbound
443 — notes say so). AIVIS-004/005/006 (Perplexity/Copilot/Google AI) stay **Partially
Implemented** — no wired API; now explicitly labeled *simulated* in the UI. AIVIS-017 → **Tested**
(the swing alert was wired to notifications in Module 04 and is covered by AlertNotificationsTest).

## Database entities

`ai_visibility_checks` gains `answer_excerpt` (text, nullable): the first ~800 characters of the
engine's actual answer. No other schema changes.

## Design

- **`AnswerAnalyzer`** (new, unit-tested): `analyze(answer, brand, competitorNames)` →
  mentioned; position = the brand's rank by first appearance among detected entities (brand +
  known competitor names present in the answer); competitors found; share_of_answer = % of
  sentences naming the brand; excerpt. Deterministic string heuristics — no model calls, honestly
  approximate, and shared by every driver.
- **Contract change**: `AiSearchProvider::query(prompt, brand, competitors = [])`;
  `AiVisibilityResult` gains `answerExcerpt`. Fixture driver updated (invents an excerpt clearly
  marked as simulated text).
- **Drivers**: `OpenAiSearchProvider` rebuilt on the analyzer; new `GeminiAiSearchProvider`.
  Key resolution per driver: `config('seo.*.key')` first, then the platform AI console's stored
  key (`PlatformSetting ai.{name}.api_key`) — one key powers chat AND visibility.
- **`SeoProviderManager::aiFor(engine)`** maps chatgpt→openai, gemini→gemini, everything else →
  the configured default (fixture); `engineStatuses()` reports live/simulated per engine.
- **Runners**: `AiVisibilityDashboard::runLibrary` selects the driver per engine, passes the
  tenant's competitor names (analytics `competitors` table), stores excerpt + provider per check
  (the provider column existed but runLibrary never set it — fixed). `AiVisibilityService::check`
  same treatment.
- **UI** (`ai/visibility`): per-engine live/simulated chips; recent checks show their evidence
  excerpt; the existing fixture banner stays.

## Testing

Pest `AiVisibilityMeasurementTest`: analyzer heuristics (mention, ordering-based position,
competitor detection, sentence share, excerpt cap); OpenAI + Gemini drivers via `Http::fake`
(request shape + analyzed result + excerpt + failure fallback); platform-key fallback; runLibrary
per-engine driver selection + excerpt/provider persisted + competitor names passed; engine status
map; page props carry statuses and evidence.

## Out of scope

Perplexity/Copilot/Google-AI-Overviews drivers (no API path wired), a `claude` engine label,
per-tenant API keys, cost metering of visibility checks through AiGateway, AEO content tooling.
