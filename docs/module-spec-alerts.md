# Module Specification — Alerts & Notifications (ALRT, "Module 04")

> Approved 2026-08-27 ("next in the Phase 1 order" → tier 3). Live Stripe verification, the other
> half of tier 3, stays blocked until a Stripe test account exists — tracked, not attempted here.
> Finding during discovery: the billing page already ships plan card + usage meters + invoices
> (the Phase 1 score under-read it), so this module carries tier 3's real gap: Notifications (40%).

## Purpose

Make the platform tell people what happened without being watched: business alerts (SQL promoted,
meeting booked), an operational alert (plan limit approaching), and marketing alerts (ranking drop,
AI-visibility swing) — all through the existing preference-respecting notification center. External
channels (SMS/Slack/webhooks) stay Planned: they need connectors that don't exist yet.

## Users & roles

Recipients: the record's owner where one exists, otherwise organization owners (existing
`NotificationDispatcher` targeting). Preferences: two new categories, `sales` and `marketing`,
join the per-user matrix (in-app always on, email opt-out — existing base-class behavior).

## Feature IDs

NOTIF-006 (business alerts → Tested), NOTIF-007 (operational alerts → Tested for the usage alert;
note names what still waits on INTG events), NOTIF-009 (marketing alerts → Tested as mechanism —
detection runs on recorded rankings/checks whatever their provider; provider liveness is tracked
separately). NOTIF-003/004/005 untouched (Planned).

## Database entities

None new. Dedupe uses the existing `notifications` table (a `key` field added to the payload; a
notification with the same key already created today is not re-sent).

## Behavior

- **SQL promoted** (`sales`): fired inside `LeadScoringService::apply()` at the existing promotion
  point — which fires at most once per contact by construction (the guard that prevents
  re-promotion also prevents duplicate alerts). Recipient: contact owner, else org owners.
- **Meeting booked** (`sales`): fired in `BookingService::book()` to the assigned owner — covers
  public booking pages AND in-chat booking, which share the service.
- **Usage limit approaching** (`billing`): daily sweep; any metered limit at ≥80% notifies org
  owners, deduped per key per day.
- **Ranking drop** (`marketing`): daily sweep; a tracked keyword whose latest recorded position is
  ≥5 worse than the previous check notifies org owners, deduped per keyword per day.
- **AI visibility swing** (`marketing`): daily sweep; `AiVisibilityDashboard::alert()` (existing
  AIVIS-017 window comparison) with `changed=true` notifies org owners, deduped per day.
- **`alerts:sweep`** console command runs the three detectors per organization (tenant context set
  per org, same pattern as `analytics:snapshot-growth-scores`), scheduled daily.

## UI

None new to build: the notification center, bell badge, and preference matrix already render from
`NotificationPreference::CATEGORIES`, which gains the two categories.

## Testing

Pest `AlertNotificationsTest`: promotion notifies the owner once (recompute does not repeat);
booking notifies the assigned owner; usage sweep fires at 80% (real counter: increment Emails to
20,000 of Growth's 25,000) and not below; ranking-drop fires on a ≥5-position fall and dedupes on
a second run; AI swing fires when the window comparison changes; a disabled email preference drops
the mail channel while database stays; new categories appear in the settings props.

## Out of scope

SMS/Slack/Teams/webhook channels, lead-surge statistics, workflow-failure events (fire with INTG),
digest emails, per-alert thresholds UI.
