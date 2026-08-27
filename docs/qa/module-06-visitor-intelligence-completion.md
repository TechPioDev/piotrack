# Module Completion Report — Visitor Intelligence (Module 06)

Date: 2026-08-28 · Spec: `docs/module-spec-visitor-intelligence.md` · Second competitive spearhead:
Buyer Intent was 31%; real-time visitor ID is Jumpfactor's loudest public claim. This module ships
the first-party half honestly — reverse-IP company enrichment needs a data provider and was NOT
faked (INTENT-002 stays Planned, note says why).

## What shipped

1. **The tracker**: `GET /t/{key}.js` serves a ~1KB dependency-free pixel (visitor cookie +
   localStorage id, pageview with path/title/referrer/UTM on load, `piotrack.identify(email)`);
   `POST /t/{key}/e` ingests — keyed to the tenant, throttled, CSRF-exempt, validated (junk
   visitor ids 422, unknown keys 404 so tenants can't be probed).
2. **The rollup**: `visitors` (sessions on a 30-minute quiet window, page views, first-touch
   referrer/UTM never overwritten, last path, intent score) over raw `visitor_events`.
3. **Identity resolution**, two honest paths: `identify(email)` links the tenant's matching
   contact; a hosted-form submission links automatically — the pixel cookie rides the same-domain
   POST into `LeadCaptureService` (`_pt_vid` excepted from cookie encryption).
4. **Intent wiring**: path rules score high-intent (8: pricing/contact/book…), service interest
   (3), content view (2). Heat accrues on the visitor row always; the moment a contact is linked
   the same behavior records real `IntentSignal`s — so the existing scoring, alerts (Module 04's
   sweep included) and next-action engine light up from browsing. Anonymous behavior never
   fabricates a contact signal (test-pinned).
5. **Sales → Visitors page**: identified-first table (contact link, company, sessions, pages,
   Hot/Warm/Browsing heat, first-touch source, last seen), install snippet with copy button;
   opening the page first time mints the org's tracking key. Our hosted public pages (site pages,
   forms, landing, booking) auto-embed the pixel.

## Gate

- Pest **783 passed (3,206 assertions)** — `VisitorIntelligenceTest` (8 tests, 44 assertions):
  script serving, session window via time travel, first-touch immutability, junk-id rejection,
  identify + post-identification signal weights, anonymous heat isolation, form-cookie linking,
  tenant sealing, key minting.
- Vitest 43, Pint, PHPStan, Prettier, ESLint, tsc clean; migration run; assets built.
- Live: key minted on first page view, tracker served as JS, event ingested, visitor rendered
  "Warm (8)" with source `newsletter` seconds later.

## Register

INTENT-001/003/004/005/006/009 → **Tested**; INTENT-002 stays **Planned** (provider-gated, note
explains); INTENT-013 note updated. **Buyer Intent: 31% → 75%.**
Totals: **733 Tested / 299 Partially Implemented / 150 Planned** of 1,191.

## Out of scope, unchanged

Reverse-IP company enrichment, chat-widget cross-domain visitor linking, per-tenant path weights,
geo, digests.
