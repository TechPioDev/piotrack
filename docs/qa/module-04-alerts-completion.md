# Module Completion Report — Alerts & Notifications (Module 04)

Date: 2026-08-27 · Spec: `docs/module-spec-alerts.md` · No new tables; no front-end changes needed
(the notification center and preference matrix render the new categories from the constant).

## Discovery correction

Tier 3 named "billing usage meters" as a gap; the billing page already ships plan card, usage
meters with progress bars, invoices and billing details — the Phase 1 DOM score under-read a
compact page. Tier 3's real gap was Notifications (40%), which this module carries.
**Live Stripe verification remains blocked** until a Stripe test account exists (user-side).

## What shipped

1. **SQL-promotion alert** (`sales`): fired at the scoring service's promotion point — at most once
   per contact by construction. Contact owner first, org owners as fallback.
2. **Meeting-booked alert** (`sales`): fired in `BookingService::book()`, so public booking pages
   and in-chat booking both notify the assigned seller.
3. **Usage-limit alert** (`billing`): any metered limit at ≥80% notifies org owners.
4. **Ranking-drop alert** (`marketing`): a tracked keyword ≥5 positions worse between its two most
   recent recorded checks.
5. **AI-visibility swing alert** (`marketing`): the existing AIVIS-017 window comparison, now
   actually notifying someone.
6. **`alerts:sweep`** command runs detectors 3–5 per organization in tenant context, scheduled
   daily at 07:00 (after the nightly data jobs). Dedupe: a `key` field in the notification payload;
   the same key already delivered to that user today is skipped — re-running the sweep is safe.
7. Preference matrix gains `sales` and `marketing` categories (in-app always on, email opt-out) —
   which also fixes the pre-existing `SalesAlertNotification` using a category the matrix never
   offered to opt out of.

## Gate

- Pest **768 passed (3,113 assertions)** — `AlertNotificationsTest` (8 tests): promotion fires
  once across recomputes; booking notifies the seller; usage alert at exactly 80% (real counter:
  20,000 of Growth's 25,000 emails) and silent at 40%, deduped on the second sweep; ranking drop
  of 7 fires while a wobble of 2 stays quiet, deduped; AI swing +100pts fires; a disabled email
  preference drops mail while database remains; new categories reach the settings page.
  Assertions read real `notifications` rows — faking the channel would blind the dedupe under test.
- Vitest 43, Pint, PHPStan, Prettier, ESLint, tsc all clean.
- Live: `alerts:sweep` on the seeded org reports 0/0/0 — honestly quiet (nothing near a limit, no
  qualifying drops). Local delivery note: queued notifications need `queue:work` outside tests.

## Register

NOTIF-006 → **Tested**, NOTIF-007 → **Tested** (note names what still waits on INTG/automation
events), NOTIF-009 → **Tested** (mechanism; rank-data liveness tracked via provider columns).
Totals: **709 Tested / 304 Partially Implemented / 169 Planned / 8 Implemented / 1 N/A** of 1,191.

## Out of scope, unchanged

SMS/Slack/Teams/webhook channels (Planned, need connectors), digest emails, lead-surge statistics,
alert-threshold settings UI, live Stripe run.
