# Module Completion Report — Call Tracking + Lead Guarantee / Performance Model

**Phase 41 · 2026-09-08 · Register: CALL-003, CALL-004, CALL-005, PERF-004, PERF-010, PERF-011 → Tested (both modules 100%: Call Tracking 11/11, Lead Guarantee 11/11)**

## Scope

Six rows across two modules: the three call rows blocked on "provider-side recordings/
transcription/summaries" (partly stale — the AI summary shipped in P25), and the three
performance rows whose notes named the exact missing pieces (reconciliation, breach
notification, review artefact).

## What shipped

### Recordings → transcription → summary chain (CALL-003/004/005)

- **CALL-003:** a rep attaches the recording URL to a call (https enforced), the calls
  page plays it inline, and the UI states plainly that automatic capture arrives with a
  telephony-provider connection.
- **CALL-004:** transcription runs through a new `TranscriptionProvider` seam — the same
  pattern as SMS, ads and AI providers. The fixture driver ships, is deterministic, and
  states its own provenance so fixture text can never masquerade as a real transcript;
  `POST calls/{call}/transcribe` fills the transcript from the recording and refuses
  without one. A live speech-to-text driver is credentials plus one class.
- **CALL-005:** closes on the P25 evidence — `AiSalesAgent::summarizeCall` through the
  governed gateway, working on the transcript. Phase 41's test drives the whole chain:
  attach → transcribe → summarize, with the gateway mock proving the summary saw what
  was said.

### Guaranteed-deliverables reconciliation (PERF-004)

`PerformanceService::reconcileDeliverables` — the agreement's promised items matched
against real project deliverables by normalized title (fuzzy contains both ways), with
one deliberate rule: a client-**approved** deliverable counts as delivered even when its
status label lags, because the sign-off is the stronger fact. Per-item delivered/
approved/missing plus counts, rendered on the performance page automatically.

### SLA breach notification (PERF-010)

`SlaBreachNotification` (operations category) fired by the daily alert sweep for live
agreements whose window closed with targets missed — the existing BREACHED judgment,
now pushed to owners with the missed targets and their numbers ("leads 0/50"), deduped
per agreement per day, riding the full P40 notification stack (in-app, email, and the
org's Slack/Teams/webhook channels).

### ROI review artefact (PERF-011)

`performance_reviews` — a stored, timestamped snapshot per agreement period: attainment,
deliverable reconciliation, won revenue closed **in the window** (`deals.closed_at`
bounded), ad spend in the window, and the guarded ROI ratio (no spend recorded means no
ratio, never infinity). Generated on demand from the agreement card and listed on the
performance page.

## Gate results

| Check | Result |
| --- | --- |
| `vendor/bin/pint --test` | pass |
| `phpstan analyse` (1G) | 0 errors |
| `npm run format:check` / `npx eslint .` / `npm run types` | pass |
| `npm run build` | pass |
| `npm run test` (Vitest) | 55 passed |
| `php vendor/bin/pest` | **1,015 passed, 5,086 assertions** |

New: `tests/Feature/Qa/CallPerformanceCloseoutTest.php` (4 tests) — the full recording/
transcription/summary chain with the no-recording refusal and https guard; fuzzy
reconciliation incl. the approved-beats-status rule; the sweep-driven breach
notification with per-day dedupe; and the period-bounded ROI review (out-of-window
revenue and spend proven excluded).

## Register effect

6 rows → Tested. Call Tracking **11/11** and Lead Guarantee / Performance Model
**11/11** — the 37th and 38th complete modules. Global: **1,066/1,190 buildable Tested
(89.6%)**.
