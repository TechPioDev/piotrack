# Module Completion Report — Appointment Booking, Phase 18

**Date:** 3 September 2026 · **Module:** Appointment Booking · **Register: 12/12 Tested (100%)**, was 6/12 (50%)

## What shipped

**Salesperson availability (BOOK-003)** — the availability engine already existed
(`ChatBookingSlots`, built for in-chat booking); the public page had been ignoring it and
accepting any datetime. Generalized: the public page now offers real slots (inside the
page's configured working days/hours, in the future, not already taken — 24 slots over
14 days), a picked slot is revalidated server-side ("that time was just taken"), and a
taken slot disappears from the next visitor's list. The manual field survives as an
explicit "request another time".

**Calendar integration (BOOK-001)** — the OAuth-free, universal kind
([IcsCalendar](../../app/Services/Sales/IcsCalendar.php)): every booking gets a tokened
`.ics` invite linked from its confirmation email, and every booking page gets a secret
ICS feed URL that Google/Outlook/Apple Calendar can subscribe to (upcoming,
non-cancelled bookings, shown on the sales booking page). Two-way OAuth sync remains a
connector enhancement — the note says so.

**Territory-based assignment (BOOK-005)** — a `territory` page asks the visitor for
their city/area and routes the meeting to the matching branch's rep
(`seo_locations.owner_id`, matched case-insensitively against city/region/territory),
with round-robin as the fallback when nothing matches — never an unowned meeting,
never a guessed match.

**Qualification forms (BOOK-006)** — per-page custom questions (label + required)
rendered and validated on the public page, answers stored keyed by their question; the
booking link's own UTM parameters are captured onto the booking alongside the existing
visitor-trail attribution.

**No-show workflow (BOOK-011)** and **post-meeting follow-up (BOOK-012)** — the two new
automation triggers `booking_no_show` and `booking_completed` are workflow-creatable;
a no-show also sends the prospect a re-book email pointing straight at the page, and a
completed meeting also creates a dated follow-up task for the owner.

## Gate evidence

- Pest: **904 passed / 4,081 assertions** (+6:
  [BookingCloseoutTest](../../tests/Feature/Qa/BookingCloseoutTest.php) — slot honesty
  end to end, required-question validation + stored answers/UTM, territory routing +
  fallback, invite + feed as text/calendar with exact DTSTART, both automation statuses,
  permission gating).
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
