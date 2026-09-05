# Phase 35 — Analytics Dashboard (first-party web analytics) + Privacy close-out

Register target (12 rows): ANLY-001 (sessions), ANLY-002 (users), ANLY-003..008
(traffic sources: overall/organic/paid/social/referral/direct), ANLY-013 (organic
clicks), ANLY-014 (organic conversions); PRIV-002 (cookie preferences), PRIV-006
(bounce/complaint handling).

Stays honest: ANLY-012 (map rankings — local-pack positions genuinely need a
SERP/GBP data provider).

## The gap, honestly stated

The ANLY deferrals ("need GA4") predate the first-party pixel: sessions, users,
pageviews and channel-classified traffic are all measurable from Visitor
Intelligence today. GA4/GSC become enrichment (session engagement detail, search
impressions/CTR/queries), not prerequisites — stated in the notes. PRIV-002's old
note said "the product sets no cookies yet, nothing to gate" — also stale: the
pixel's `_pt_vid` and the experiment cookies exist now, so consent has something
real to gate.

## Design

**Web analytics (ANLY-001..008/013/014)** — `AnalyticsService::web()` from pixel
records: sessions (sessionized pageview events, the tracker's 30-minute window),
users (distinct visitors), pageviews; traffic split by the ChannelClassifier
buckets (organic/paid/social/referral/direct — sessions, visitors, identified
leads per channel); organic conversions (organic-first-touch visitors → contacts →
customers + won revenue). Organic sessions ARE the first-party measure of search
clicks landing on the site (ANLY-013); GSC adds impressions/CTR/query detail when
connected. New "Website analytics" section on the analytics dashboard with the
measurement provenance stated.

**Cookie consent (PRIV-002)** — the pixel now loads only with consent: the shared
pixel include becomes a nonce'd inline gate that reads the `pt_consent` cookie —
granted loads the pixel, denied loads nothing, undecided shows a small banner
(Allow / essential-only) whose decision sets the cookie for a year, records a
CookiePreference row through a public consent endpoint, and loads the pixel on
accept. Fully client-side gating, so page caching (P23 ETags) is unaffected; a
fresh org without a tracking key renders neither banner nor pixel, keeping the
P23 performance budget green.

**Bounce/complaint handling (PRIV-006)** — a provider-agnostic ESP webhook seam:
`POST /webhooks/email` guarded by a shared secret (endpoint refuses when no
secret is configured), accepting `{type: bounce|complaint, email}`. Every tenant
that has messaged the address gets the suppression (the dispatch pipeline already
honors suppressions) and its latest matching recipient rows are marked bounced.
Live ESP credentials remain external; the handling mechanism is real and tested —
the SEC-003 seam precedent.

## Tests (tests/Feature/Qa/AnalyticsPrivacyCloseoutTest.php)

1. web(): sessions/users/pageviews from seeded events; channel split lands each
   visitor in its classifier bucket with leads counted.
2. Organic conversions join organic first-touch → contact → won revenue; paid
   visitors never leak in.
3. Consent gate: pixel markup absent without a tracking key; with one, the page
   carries the consent gate and never a hard-coded pixel tag; the consent
   endpoint stores the preference row and refuses bad payloads.
4. ESP webhook: correct secret + bounce suppresses the address for the messaging
   tenant and marks the recipient; complaint suppresses too; wrong/missing
   secret refused; unconfigured endpoint refuses everything.
5. The dashboard carries the web section; the P23 budget/ETag behaviour is
   unchanged for a fresh org (re-pinned by the existing suite staying green).
