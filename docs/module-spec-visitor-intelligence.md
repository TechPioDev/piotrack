# Module Specification — Visitor Intelligence (VINT, "Module 06")

> Approved 2026-08-28 ("next" — second competitive spearhead). Buyer Intent sits at 31% and
> real-time visitor ID is Jumpfactor's loudest public claim. This module builds the first-party
> half honestly: our own tracking pixel, sessions, identity resolution and intent signals.
> Reverse-IP company lookup needs an external data provider and is NOT faked (INTENT-002 stays
> unclaimed as such; an identified contact's company is shown, which is first-party truth).

## Purpose

Answer "who is on my website right now, what do they care about, and how hot are they?" from
first-party data: a 1KB tracker snippet records pageviews and sessions per visitor, identity is
resolved when the visitor tells us who they are (a form submit on our hosted pages, or an explicit
`identify` call), and identified activity feeds the existing intent-signal engine so scoring,
alerts and next-action recommendations light up from real behavior.

## Users & roles

`sales.view` gates the new Visitors page. The tracking endpoints are public (keyed + throttled).

## Feature IDs

INTENT-001 anonymous visitor tracking+identification → Tested · INTENT-003 repeat-visitor
detection (30-minute session window) → Tested · INTENT-004 high-intent pages → Tested ·
INTENT-005 service-interest detection → Tested · INTENT-006 bottom-funnel behavior → Tested ·
INTENT-009 content-consumption signals → Tested · INTENT-013 note updated (repeat-visit window
feeds it). INTENT-002 (company identification via IP/enrichment) stays **Planned** — provider-gated.

## Database entities

- `visitors`: organization_id, visitor_key (unique per org), contact_id?, email?, first/last_seen_at,
  visits (sessions), page_views, intent_score, last_path, referrer, utm_source/medium/campaign
  (first-touch, never overwritten), timestamps.
- `visitor_events`: organization_id, visitor_id FK cascade, type (pageview|identify), path, title,
  timestamps. Raw trail behind the rollup.
- `organizations.tracking_key` (nullable unique, `tk_` + random; generated lazily when the
  Visitors page is first opened).

## Public surface

- `GET /t/{key}.js` — the tracker (server-rendered JS, cacheable, throttled): persists `_pt_vid`
  in a first-party cookie + localStorage, sends a pageview (path, title, referrer, UTM) on load,
  exposes `piotrack.identify(email)`.
- `POST /t/{key}/e` — ingest (throttle 60/min, CSRF-exempt, validated): resolves the org by key
  (`withoutGlobalScope` then tenant context, the established public-endpoint pattern), upserts the
  visitor (new session when last_seen > 30 min ago; first-touch UTM kept), stores the event, and
  scores paths: pricing/contact/book → high-intent (8), service paths → service interest (3),
  blog/guides/resources → content view (2). Scores accrue on the visitor row always, and as
  `IntentSignal`s through the existing `IntentService` once a contact is linked.
- `identify`: links the visitor to the contact with that email (tenant-scoped) or stores the email
  for later linking. Form submissions on our hosted pages link automatically: the pixel cookie
  rides the same-domain POST, `LeadCaptureService::capture()` gains an optional visitor key and
  links the new/found contact. `_pt_vid` joins the cookie-encryption exceptions; `t/*` joins the
  CSRF exceptions.
- Our hosted public pages (site pages, forms, landing pages, booking) auto-embed the tracker when
  the organization has a tracking key.

## UI

**Sales → Visitors** (new page + sidebar entry): install snippet with copy button (existing
clipboard lib), visitor table — identified (contact name → CRM link, company) or "Anonymous", pages
viewed, sessions, intent score, first-touch source, last seen. Identified-first ordering.

## Testing

Pest `VisitorIntelligenceTest`: ingest happy path + bad key 404 + junk vid rejected; session
counting across the 30-minute boundary (travel); first-touch UTM immutability; identify links by
email + records the signal; form submit with the pixel cookie links the contact; path scoring
writes the right signal types and weights (and none for anonymous — score accrues on the visitor
row only); tenant isolation (key A cannot write org B, page scoped); Visitors page props; tracker
JS served with the right content type.

## Out of scope

Reverse-IP/company enrichment (needs provider), cross-domain chat-widget visitor linking, weekly
digests, per-tenant path-weight configuration (constants v1), geo enrichment.
