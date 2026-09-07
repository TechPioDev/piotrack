# Phase 41 — Call Tracking + Lead Guarantee closeout

**Register targets:** CALL-003, CALL-004, CALL-005 (Call Tracking 72.7% → 100%);
PERF-004, PERF-010, PERF-011 (Lead Guarantee 72.7% → 100%).

## Position

- **CALL-005 AI summaries** — largely stale: `AiSalesAgent::summarizeCall` shipped and was
  gateway-tested in P25 (summarize from the call's transcript, refuse without one).
  Closes on that evidence, wired into the transcription flow below.
- **CALL-004 Transcription** — a provider seam like every other external capability
  (SMS/ads/AI): `TranscriptionProvider` contract with a fixture driver that states its
  provenance; POST calls/{call}/transcribe fills the transcript from the call's
  recording. A live speech-to-text driver is credentials, exactly like Twilio for SMS.
- **CALL-003 Recordings** — first-party: a rep attaches the recording URL to the call
  (validated https), the calls page plays it inline, and it feeds transcription.
  Automatic capture via telephony-provider webhooks stays gated (unchanged).
- **PERF-004 Reconciliation** — promised deliverables (stored on the agreement) matched
  against real project deliverables by normalized title: per-item delivered/approved/
  missing, with counts — automatic promised-vs-delivered reconciliation on the
  performance page.
- **PERF-010 SLA breach notification** — a `SlaBreachNotification` (operations category,
  deduped per agreement per day) fired by the alert sweep when a live agreement's window
  closes with targets missed (the existing BREACHED judgment).
- **PERF-011 ROI review artefact** — a stored `performance_reviews` row generated on
  demand per agreement: attainment snapshot, won revenue closed in the period
  (`deals.closed_at`), ad spend in the period, and the guarded ROI ratio. Listed on the
  performance page — a formal, timestamped review record, not a transient screen.

## Build

1. `App\Calls\TranscriptionProvider` + `FixtureTranscriptionProvider` + container
   binding (config `services.transcription.driver`, fixture default).
2. `CallController::recording` (PATCH url) + `::transcribe` (POST); routes; calls.tsx
   recording dialog + audio player + Transcribe button.
3. `PerformanceService::reconcileDeliverables()`; controller prop; performance.tsx panel.
4. `SlaBreachNotification`; `AlertSweep::checkSlaBreaches()`.
5. Migration `create_performance_reviews_table`; `PerformanceReview` model;
   `PerformanceService::generateRoiReview()`; POST performance/{agreement}/roi-review;
   performance.tsx reviews panel.
6. Tests `tests/Feature/Qa/CallPerformanceCloseoutTest.php` (~5).

## Out of scope (unchanged)

Live telephony/CallRail webhooks for auto-captured recordings; live speech-to-text
credentials (fixture-tested seam).
