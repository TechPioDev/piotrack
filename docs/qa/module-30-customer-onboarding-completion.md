# Module Completion Report — Customer Onboarding, Phase 19

**Date:** 3 September 2026 · **Module:** Customer Onboarding · **Register: 14/14 Tested (100%)**, was 7/14 (50%)

## What shipped — the guided setup wizard (`/onboarding/setup`)

Seven steps, and every one writes the real records the rest of the platform runs on —
onboarding leaves no parallel data silo behind:

- **Business profile (ONBD-006)** — activates exactly the chosen service lines and
  verticals (idempotent re-runs re-activate cleanly) and creates the home-market branch.
- **Website (ONBD-007)** — `brand_profiles.website_url`, the same entity field the
  Phase 10 knowledge graph and the audits anchor on.
- **Marketing goals (ONBD-008)** — leads/SQLs/MRR targets stored as `KpiTarget` rows for
  the next 90 days: the strategy dashboard tracks them, nothing is a wish list.
- **ICP setup (ONBD-009)** — the ideal customer becomes **live firmographic scoring
  rules** (size +15, region +10, industry +5, idempotent by name): a matching lead
  scores 30 through `LeadScoringService` the moment setup finishes — pinned in the test.
- **Competitors (ONBD-010)** — tracked `Competitor` records, deduped by domain.
- **Integrations (ONBD-011)** — the Phase 3 connector registry with its real
  connected / ready / needs-vendor-app state, linking to settings; actual connections
  wait on vendor OAuth apps and the step says so.
- **Finish (ONBD-012)** — runs the first technical site audit against the captured URL
  (SSRF-guarded); no URL means quiet completion with a nudge, never an error.

The dashboard checklist (ONBD-013/014, already Tested) gains five derived steps —
website, goals, ICP, competitors, first audit — all resumable because they derive from
state, all pointing at the wizard. The wizard sits behind `organization.update`: setup
decides taxonomy, goals and scoring for the whole tenant, so it is an admin act.

## Gate evidence

- Pest: **909 passed / 4,131 assertions** (+5:
  [OnboardingSetupTest](../../tests/Feature/Qa/OnboardingSetupTest.php) — exact taxonomy
  activation + branch dedupe, ICP-scores-a-lead-immediately, competitor dedupe, finish
  with and without a URL, derived checklist + permission gating).
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
