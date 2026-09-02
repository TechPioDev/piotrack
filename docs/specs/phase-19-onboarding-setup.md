# Phase 19 — Customer Onboarding close-out: the guided setup wizard

Register target: ONBD-006..012 (7 rows → module 100%).

## Design

One wizard at `/onboarding/setup` (gated `organization.update` — setup is an
admin/owner act) whose every step writes the real records other modules already run on,
so "onboarding" is not a parallel data silo:

| Step | Row | Writes |
| --- | --- | --- |
| Business profile | ONBD-006 | activates the selected ServiceLines + Verticals (others deactivated), creates the first SeoLocation from city/region |
| Website | ONBD-007 | `brand_profiles.website_url` (the Phase 10 entity field) |
| Marketing goals | ONBD-008 | KpiTarget rows (leads / sqls / mrr) for the next 90 days |
| ICP | ONBD-009 | live firmographic ScoringRules ("ICP: …", idempotent by name) — the ICP immediately scores leads through the Phase 9 engine |
| Competitors | ONBD-010 | Competitor records (deduped by domain) |
| Integrations | ONBD-011 | surfaces the Phase 3 connector registry with its live connectable/configured state and links to settings — vendor OAuth apps stay the noted gate for actual connections |
| Finish | ONBD-012 | runs the first technical audit against the captured website URL (SSRF-guarded; skipped gracefully when no URL) |

The dashboard checklist (ONBD-013/014, already Tested) gains derived steps for the new
work — website set, goals set, ICP rules present, competitors present, first audit run —
all pointing at the wizard, all resumable because they derive from state.

## Tests (tests/Feature/Qa/OnboardingSetupTest.php)

1. Business profile activates exactly the chosen taxonomy and creates the location.
2. Website/goals persist; ICP creates idempotent scoring rules that actually score a
   matching contact through LeadScoringService.
3. Competitors dedupe by domain.
4. Finish triggers the audit when a URL exists and completes cleanly when none does.
5. Checklist derives the new steps; viewer forbidden; tenant isolation.
