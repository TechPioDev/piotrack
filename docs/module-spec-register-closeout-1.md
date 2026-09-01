# Module Specification — Register Close-out Sprint 1 (Module 12)

> Approved course: "work on missing task" toward 100% register coverage (2026-09-01).
> Targets the smallest lagging register modules where features are shipped-but-stale or
> one step from done. External-dependency rows (calendar OAuth, live SMS delivery,
> Stripe, GBP) stay honestly Planned/Partial with justification.

## Scope (register rows)

**Sales Alerts** — ALERT-001 email channel test; ALERT-002 SMS channel through the existing
marketing SMS provider abstraction; ALERT-003 CRM timeline entry on alert fire; ALERT-004
Slack/Teams incoming-webhook channel (org-configured URL, Http-faked tests); ALERT-006
repeat-visitor, ALERT-007 content-engagement, ALERT-008 bottom-funnel (fired from visitor
events), ALERT-009 meeting-request (fired on booking creation).

**Support** — SUPP-004 ticket notifications to requester and assignee on reply/assign/resolve.

**Global Search** — SRCH-001 add leads, campaigns and content groups; SRCH-002 suggestions +
per-user recent searches (cache-backed).

**Import/Export** — IMEX-001/003 extend CSV import + export to contacts, keywords, competitors
(companies/leads/deals shipped in Module 09). IMEX-004 (PDF) stays Planned — no PDF engine, and
nothing gets faked.

**Booking** — BOOK-007/008/009 tests for shipped reminders/reschedule/cancel; BOOK-010 meeting
source attribution (UTM/visitor linkage on public bookings).

## Testing

Every row moved to Tested carries a Pest test in this sprint's files; tenant isolation and
permission checks included where new endpoints/columns appear. Full gate before commit.
