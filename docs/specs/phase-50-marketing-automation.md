# Phase 50 — Marketing Automation close-out (AUTO-003/005/008/027/029)

**Goal: Marketing Automation 24/29 → 29/29 (100%).**

## The stale-deferral insight

All five notes predate what the platform now holds. "Needs a site tracking script" — the
first-party pixel has captured pageviews/clicks/scroll since P27/P35, and VisitorTracker
already knows when a pageview belongs to an identified contact. "Needs Stage 10 INTENT" —
IntentService has recorded scored signals since Stage 10. "Content-download needs tracking" —
P23's gated lead magnets hand out signed download links whose redemption is a first-party
event. "Retargeting-audience action needs ad connectors" — P16's audiences are first-party
records with CSV/customer-match exports; only the live push is connector-gated, exactly as it
is everywhere else. And AUTO-029 is the QA-pinned branching gap, guarded by a test that
deliberately fails when `workflow_steps` gains a condition column — that pin flips to a
positive pin this phase.

## Register rows

| Row | Close |
| --- | --- |
| AUTO-003 page-visit workflows | `page_visit` trigger fired from `VisitorTracker::ingest` when a pageview belongs to an identified contact; `trigger_config.path_contains` pins it to a path. |
| AUTO-005 content-download workflows | `content_download` trigger fired when a gated lead-magnet link is actually redeemed — the signed URL now carries the capturing contact, tamper-proof. |
| AUTO-008 buyer-intent workflows | `intent_threshold` trigger fired on every recorded intent signal with the contact's current score; `trigger_config.min_intent_score` gates enrollment. |
| AUTO-027 retargeting-audience action | `add_to_audience` action drops the contact into a list-sourced retargeting audience (membership is list-derived, so exports include them immediately); rule-sourced audiences are rule-owned and the action refuses to fake membership. |
| AUTO-029 conditional branching | `workflow_steps.condition` (JSON): field / operator / value / on_fail. Fields include real contact attributes AND `engaged_since_enrollment` (a genuine check against OutboundMessage opens/clicks after enrollment — QA §21 step 6's "Check Engagement"). `on_fail: skip` continues, `exit` ends the enrollment. |

## Build

- Migration: `workflow_steps.condition` JSON nullable.
- `MarketingTrigger::matches` += `path_contains` (substring) and `min_*` (numeric >=) config semantics.
- Firing sites: VisitorTracker (page_visit), PublicFormController::magnet (content_download,
  org context set from the file, contact resolved from the signed param), IntentService::record
  (intent_threshold with the recomputed score).
- `ActionExecutor` += add_to_audience; `WorkflowEngine::processEnrollment` evaluates conditions.
- WorkflowController: TRIGGERS/ACTIONS extended, step validation accepts `condition`,
  show props += audiences; automation UI gains trigger-config inputs, the audience select and
  a "Only run when…" condition block.
- The AUTO-029 pin test in MarketingAutomationTest flips from asserting absence to asserting
  the shipped semantics (deliberate register-status change, documented here).
- `tests/Feature/Qa/MarketingAutomationCloseoutTest.php` (~5 tests).

## Honesty lines

- Enrollment stays unique-per-active and metered (ENTL-004) for every new trigger.
- The audience action only touches list-sourced audiences — rule-sourced membership is
  computed from rules and is never faked per-contact.
- The engagement condition reads real open/click timestamps, scoped to after enrollment.
