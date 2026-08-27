# Module Completion Report — Measurable AI Visibility (Module 07)

Date: 2026-08-28 · Spec: `docs/module-spec-ai-visibility-measurement.md` · Third competitive
spearhead: productizing what Jumpfactor sells as its flagship AEO/GEO service — with measurement
you can audit.

## What shipped

1. **`AnswerAnalyzer`** — one tested meaning of every number, shared by all drivers: mentioned
   (brand presence), position (rank by first appearance among the brand + the tenant's known
   competitor names), competitors found, share-of-answer (% of sentences naming the brand), cited
   URLs, and the evidence excerpt. Deterministic heuristics; no model call needed to analyze.
2. **Engine-specific drivers**: the OpenAI driver rebuilt on the analyzer (it previously reported
   "mentioned → position 1, share 100" — crude to the point of misleading) and a new Gemini
   driver. Both Http::fake-verified end to end, both fail closed. Keys resolve from env first,
   then the **platform AI console's stored key** — one key powers chat AND visibility.
3. **Per-engine routing**: `runLibrary` used one provider for all five engine labels; now
   `SeoProviderManager::aiFor(engine)` maps chatgpt→OpenAI and gemini→Gemini the moment a key
   exists, everything else stays on the configured default. The `provider` column is now recorded
   on library runs (it existed but was never set there — fixed).
4. **Evidence**: `ai_visibility_checks.answer_excerpt` stores the first ~800 chars of the actual
   answer; the fixture driver's excerpts are stamped `[Simulated answer — fixture driver…]` so
   they can never read as market findings. The visibility page grew an "Evidence — the answers
   behind the numbers" section (expandable receipts) and **live/simulated chips per engine**.
5. AIVIS-017 closed: the change alert now actually notifies (wired in Module 04).

## Gate

- Pest **792 passed (3,242 assertions)** — `AiVisibilityMeasurementTest` (9 tests): analyzer
  heuristics incl. ordering-based position and sentence share; both drivers via Http::fake incl.
  fail-closed; platform-key fallback; per-engine provider + evidence persisted on library runs
  (live engine records the real answer while the unwired engine stays visibly simulated); page
  props.
- Vitest 43, Pint, PHPStan, Prettier, ESLint, tsc clean; migration run; assets built.
- Live: ran the library on the seeded org — all five engines honestly chipped *simulated*,
  10 evidence receipts stored, each stamped as fixture text.

## Register

AIVIS-002, AIVIS-003, AIVIS-017 → **Tested** (mechanism; live needs key + outbound 443, notes say
so). AIVIS-004/005/006 stay **Partially Implemented** with notes naming the missing API paths.
**AI Visibility Dashboard: 65% → 88%.** Totals: **736 Tested / 296 Partial / 150 Planned** of 1,191.

## To go fully live (user-side, already tracked)

SonicWall outbound-443 change (request doc includes the AI hosts) → put a real key in
Platform → AI Provider → chatgpt/gemini chips flip to live and nightly checks record real answers.

## Out of scope, unchanged

Perplexity/Copilot/Google-AI-Overviews drivers, a `claude` engine label, per-tenant keys, AiGateway
cost metering for visibility checks, AEO content tooling.
