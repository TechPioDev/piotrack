# Phase 34 — Email Marketing close-out

Register target (7 rows): EMAIL-003 (plain-text sequences), EMAIL-009 (event
campaigns), EMAIL-012 (segmentation), EMAIL-013 (dynamic content), EMAIL-014
(behavioral triggers), EMAIL-015 (A/B testing), EMAIL-019 (conversion attribution).

## Gaps found in survey

- **A real segmentation bug**: campaign send resolves its audience from static
  list memberships only — a dynamic list's criteria members never received a
  campaign. ListService::members() (which applies criteria) exists and send()
  simply never used it.
- The `email_engagement` workflow trigger is registered but **nothing fires it**
  — opens/clicks record stats and intent but never reach the trigger.
- A/B fields are NOT in the schema (the old note overstated readiness).

## Design

- **Segmentation (EMAIL-012)** — send() resolves the audience through
  `ListService::members()`, so dynamic criteria (lifecycle stage, lead source,
  min score) get campaigns exactly as the list screen shows them. Static lists
  unchanged.
- **A/B testing (EMAIL-015)** — `campaigns.subject_b` +
  `campaign_recipients.variant`. When a B subject exists, recipients alternate
  A/B deterministically; each variant's subject renders through merge tags;
  results report per-variant recipients/opens/open-rate and the leader — only
  ever the tenant's own measured numbers.
- **Dynamic content (EMAIL-013)** — conditional blocks join merge tags:
  `{{#if field}}…{{else}}…{{/if}}` and `{{#if field=value}}…{{/if}}` over the
  merge-tag fields plus lifecycle_stage/lead_source, rendered per contact in
  both HTML and text bodies before tag substitution.
- **Behavioral triggers (EMAIL-014)** — the first campaign click fires
  `email_engagement` through MarketingTrigger (tenant context set from the
  recipient, since the tracking route is public), enrolling matching active
  workflows. Opens stay stats-only — a click is the engagement worth acting on.
- **Conversion attribution (EMAIL-019)** — per-campaign conversions: recipient
  contacts whose deals closed WON after the campaign was sent — customers and
  revenue on the campaign results. A win that predates the send never counts.
- **Sequences (EMAIL-003)** — closes on the workflow engine: per-step delays +
  send-email actions are the sequence runner; a plain-text sequence is a
  workflow chain of text emails, pinned end-to-end in the test.
- **Event campaigns (EMAIL-009)** — the event flow is composed of tested parts:
  an event-typed campaign announces to a segment, the booking page takes
  registrations (P18), and attendees get the reminder engine (P30). Pinned as a
  flow test.

## Tests (tests/Feature/Qa/EmailMarketingCloseoutTest.php)

1. A dynamic list's criteria members receive the campaign (the fixed bug),
   static membership behaviour unchanged.
2. A/B split alternates variants, subjects differ, per-variant opens and the
   leader report correctly; no B subject → no split.
3. Conditional blocks render per contact (match, else-branch, strip).
4. First click fires email_engagement and enrolls the matching workflow;
   second click doesn't re-enroll.
5. Conversion attribution counts only post-send wins with their revenue.
6. A two-step delayed plain-text sequence delivers both emails through the
   workflow engine; an event campaign sends to its segment.
