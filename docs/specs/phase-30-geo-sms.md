# Phase 30 — GEO + SMS Automation close-out

Two modules whose deferral notes went stale as later stages shipped: SMS reminders
"need Booking (Stage 10)" — Booking shipped in Phase 18; GEO citation optimization is
"content/outreach work (Stage 9)" — the outreach pipeline shipped in Phase 17.

Register target (9 rows): GEO-011/012/013 (city/service/vertical-specific AI
recommendations), GEO-014/015/016 (citation-source analysis / optimization /
authority-source development), SMS-002 (appointment reminders), SMS-003 (sales
alerts), SMS-006 (event reminders).

## SMS Automation

- **SMS-003 — already shipped, note stale.** Sales-alert SMS fan-out exists as
  ALERT-002/004: `organization.alert_channels.sms_to`, configured on the alerts page,
  delivered best-effort on every alert fire, covered by RegisterCloseoutSprint1Test.
  Close on evidence.
- **SMS-002/006 — attendee SMS reminders.** The daily `sales:send-booking-reminders`
  command (owner notification + attendee email) gains the attendee SMS leg: a booked
  contact with a phone gets a reminder through MessageDispatcher (consent and
  suppression enforced per contact by the dispatcher, like every other SMS). Copy is
  type-aware from the booking page's meeting_type — an event/webinar reads as an event
  reminder, anything else as an appointment reminder. Appointment (002) and event
  (006) reminders are the same engine over differently-typed booking pages.

## GEO

- **Dimension recommendations (GEO-011/012/013)** — `byDimension` (mention rate per
  city/service/vertical) exists; the rows wanted recommendations, not just numbers.
  `dimensionRecommendations()`: a dimension value with 2+ checks and a mention rate
  under 50% produces a named platform action citing its numbers (city → location page
  + GBP/citations; service → service page + knowledge graph; vertical → vertical page
  + case study); a dimension with no prompt variants at all is called out ("add
  variants to the prompt library"). Strong values produce nothing.
- **Citation sources (GEO-014/015/016)** — checks already capture `cited_sources`;
  the loop into action was missing. `citationSources()` aggregates cited hosts across
  checks (count + share) and marks each `covered` (an outreach prospect or authority
  asset already exists for that host) or `gap`. One click targets a gap: an
  OutreachProspect in the idempotent "AI citation sources" digital-PR campaign —
  citation-source optimization and authority-source development run through the
  tested Phase 17 pitch→placement→typed-asset pipeline from there.

Both surfaces render on the AI-visibility page with the existing fixture/live
provider honesty banner.

## Tests (tests/Feature/Qa/GeoSmsCloseoutTest.php)

1. Reminder command sends the attendee SMS for a consenting contact with a phone,
   type-aware copy for events, skips contacts without phones, and respects opt-out
   (suppressed by the dispatcher, never sent).
2. Alert SMS evidence re-pinned alongside (channel configured → SMS attempted).
3. Dimension recommendations: weak city cited with its rate and action; strong
   service silent; missing vertical dimension prompts the variant recommendation.
4. Citation sources aggregate hosts with covered/gap status; targeting a gap creates
   the campaign + prospect idempotently; endpoint permission-checked.
