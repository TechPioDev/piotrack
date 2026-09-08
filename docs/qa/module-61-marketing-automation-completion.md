# Module Completion Report — Marketing Automation

**Phase 50 · 2026-09-09 · Register: AUTO-003, AUTO-005, AUTO-008, AUTO-027, AUTO-029 → Tested (module 29/29, 100%)**

## Scope

Five rows whose notes predate the platform's own capabilities: "needs a site tracking
script" (the first-party pixel shipped in P27/P35), "needs Stage 10 INTENT" (IntentService
has recorded scored signals since Stage 10), "needs ad connectors" (P16 audiences are
first-party records; only the live push is connector-gated), and the QA-pinned branching
gap of 2026-08-19.

## What shipped

- **Page-visit workflows (AUTO-003)** — `VisitorTracker` fires `page_visit` when a pageview
  belongs to an identified contact; `trigger_config.path_contains` pins the page. New
  `path_contains` and `min_*` semantics in `MarketingTrigger::matches`.
- **Content-download workflows (AUTO-005)** — the P23 signed lead-magnet URL now carries
  the capturing contact (inside the signature, tamper-proof); actual redemption fires
  `content_download`, org-scoped from the file's own tenant.
- **Buyer-intent workflows (AUTO-008)** — every recorded intent signal re-evaluates
  `intent_threshold` workflows against the contact's current rolling score;
  `min_intent_score` gates enrollment; unique-per-active enrollment prevents duplicates.
- **Retargeting-audience action (AUTO-027)** — `add_to_audience` drops the contact into a
  list-sourced audience (membership is list-derived, so every export includes them
  immediately, and `member_count` rebuilds). Rule-sourced membership stays rule-owned —
  the action refuses to fake it.
- **Conditional branching (AUTO-029)** — `workflow_steps.condition`
  `{field, operator, value, on_fail}`: real contact attributes plus
  `engaged_since_enrollment`, a genuine check against OutboundMessage open/click
  timestamps **after** enrollment (QA §21 step 6, "Check Engagement"). `skip` continues
  the sequence; `exit` ends the enrollment. The automation UI gained trigger-config
  inputs, the audience select, and the "Only run when…" block.

## Deliberate test change

`MarketingAutomationTest`'s pin — which failed by design if `workflow_steps` ever gained a
condition column — now asserts the shipped shape instead, with its header note updated to
record the closure. Semantics are covered by the new closeout test.

## Gate results

| Check | Result |
| --- | --- |
| `vendor/bin/pint --test` | pass |
| `phpstan analyse` (1G) | 0 errors |
| `npm run format:check` / `npx eslint .` / `npm run types` | pass |
| `npm run build` | pass |
| `npm run test` (Vitest) | 55 passed |
| `php vendor/bin/pest` | **1,059 passed, 5,464 assertions** |

New: `tests/Feature/Qa/MarketingAutomationCloseoutTest.php` (5 tests) — the pixel-driven
path-pinned enrollment (wrong path enrolls nobody), gated-link redemption firing with the
signed contact, the intent floor (below = nothing, crossing = exactly once), the audience
action adding through the list while refusing the rule-sourced audience, and branching's
three outcomes (skip-and-finish, engaged-pass, exit).

## Register effect

5 rows → Tested. Marketing Automation **29/29 (100%)** — the 52nd complete module.
Global: **1,126/1,190 buildable Tested (94.6%)**.
