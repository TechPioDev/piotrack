# Module 12 Completion Report — Register Close-out Sprint 1

**Date:** 2026-09-01 · **Spec:** docs/module-spec-register-closeout-1.md · **Status:** Complete

## Register movement

**740 → 756 Tested** (of 1,191; 288 Partial · 142 Planned · 4 Implemented · 1 N/A).
Sixteen rows moved to Tested, each with a named test:

| Rows | What shipped |
| --- | --- |
| ALERT-001..004 | Sales-alert delivery: in-app + email (existing, now pinned), CRM timeline Activity on every fire, SMS via the `App\Messaging` provider abstraction, Slack/Teams incoming-webhook fan-out. Org-level channel config (`organizations.alert_channels` JSON) with a Delivery-channels card on the sales alerts page (`PUT sales/alerts/channels`, manage-gated, https-only webhook). Extra channels are best-effort — a failed delivery never breaks the triggering flow. |
| ALERT-006..009 | Event-fired alerts for KNOWN contacts only: repeat visit (new session beyond the first), content engagement (3rd content view), bottom-funnel pageview, meeting request (fired from `BookingService::book`, covering public pages and in-chat booking). All deduped per contact+type while unread. |
| SUPP-004 | Ticket notifications: reply/assign/resolve notify requester and assignee (in-app + email), never the actor, never the requester for internal notes. The old "notifies nobody" pin test rewritten to assert the built behavior. |
| SRCH-001/002 | Global search now spans leads (lead/mql/sql), campaigns and content pieces alongside the existing groups, permission-filtered; successful terms cached per user+org (last 5, 30 days) and served as recents. |
| IMEX-001 | Import breadth complete: keywords + competitors join contacts/companies/leads/deals — mapping, dedupe (phrase/name), validation (intent whitelist, numeric volume), domain normalization, ImportJob history. Exports for both added (`seo.keywords.export`, `analytics.competitors.export`). |
| BOOK-007..010 | Reminders/reschedule/cancel were already pinned by BookingJourneyTest (statuses were stale); new: `_pt_vid` cookie on a public booking links the contact to the visitor trail so first-touch UTM attribution survives into the meeting. |

Kept honest: IMEX-003 stays Partial (native .xlsx not built — no fake "Excel" claim); IMEX-004
(PDF) and BOOK-001/003/005 (calendar OAuth, availability engine, territories) stay Planned;
ALERT SMS delivers through the log driver until Twilio credentials exist.

## Gate

pint ✓ · phpstan (1G) 0 errors ✓ · prettier ✓ · eslint ✓ · tsc ✓ · Vitest 43 ✓ ·
**Pest 829 passed / 3,502 assertions** (+8 tests: RegisterCloseoutSprint1Test, extended
EntityImportExportTest, rewritten DeliveryPortalTest pin).

## Path to 100% — externally blocked items (user actions)

Live Stripe verification (test account) · outbound 443 (live AI/SEO engines, ads APIs) ·
calendar/GBP/Search Console OAuth apps · Twilio credentials · DR restore drill + Horizon
provisioning · CVE scan / pen test. Everything else remains buildable in further sprints.
