# Phase 18 — Appointment Booking close-out

Register target: BOOK-001/003/005/006/011/012 (6 rows → module 100%).

## Design

**Salesperson availability (BOOK-003)** — the availability engine already exists
(`ChatBookingSlots`, built for in-chat booking); the public page ignored it and took any
datetime. Generalized: the public booking page now offers real slots (inside the page's
configured days/hours, future, not already taken), a picked slot is re-validated
server-side, and the manual field remains as an explicit "request another time".
Availability (weekdays, start/end) is configured on the booking page.

**Calendar integration (BOOK-001)** — the universal, OAuth-free kind: every booking gets
a tokened `.ics` download (linked in the confirmation email), and every booking page
gets a secret ICS feed URL that Google/Outlook/Apple Calendar can subscribe to
(upcoming, non-cancelled bookings). Two-way OAuth sync remains a connector enhancement
and the note says so.

**Territory-based assignment (BOOK-005)** — assignment mode `territory`: the public form
asks for a city/area, matched case-insensitively against branch city/region/territory
(`seo_locations`, which gains an `owner_id` branch rep); the branch owner gets the
meeting, with round-robin as the honest fallback when nothing matches.

**Qualification forms (BOOK-006)** — per-page custom questions (label + required),
rendered on the public page, validated, stored as `bookings.answers`; UTM parameters on
the booking link are captured onto the booking itself alongside the existing
visitor-trail attribution.

**No-show workflow (BOOK-011)** — marking a booking `no_show` fires the new
`booking_no_show` automation trigger (workflows can enroll) and sends the prospect a
re-book email pointing at the booking page.

**Post-meeting follow-up (BOOK-012)** — marking `completed` fires `booking_completed`
(automation) and creates a follow-up activity for the owner, due the next day.

## Tests (tests/Feature/Qa/BookingCloseoutTest.php)

1. Slots respect availability config; a booked slot disappears; slot bookings land
   exactly on the slot.
2. Required qualification questions validate; answers + UTM stored.
3. Territory assignment matches the branch rep; unmatched city falls back round-robin.
4. ICS: confirmation email links the invite; the .ics and the page feed render
   text/calendar with the right DTSTART; bad tokens 404.
5. No-show fires the trigger + re-book email; completed fires the trigger + follow-up
   activity.
6. Permission gating + tenant isolation hold on new config fields.
