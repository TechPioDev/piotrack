# Phase 33 — Revenue Attribution close-out + Support attachments

Register target (7 rows): ATTR-006 (keyword), ATTR-007 (landing page), ATTR-008
(content), ATTR-009 (form), ATTR-010 (call), ATTR-011 (ad) attribution; SUPP-002
(ticket attachments).

## The gap, honestly stated

The ATTR deferral said per-dimension revenue linkage "needs the tracking pixel" —
the pixel shipped with Visitor Intelligence and grew click/scroll capture in
Phase 27. What was still missing: the pixel ignored `utm_term`/`utm_content`,
never recorded the landing path, and no rollup joined visitor first-touch through
contacts to won revenue. All in-house work now.

## Design

**Capture** — `visitors` gains `first_path`, `utm_term`, `utm_content`. The pixel
sends term/content with the pageview; the tracker stores them **first-touch,
set-once** like the existing UTM fields, and `first_path` locks on the first
pageview. (GSC/click-id joins remain a later enrichment for *organic* keyword
detail — UTM-based attribution is the honest, standard mechanism that works
today and is stated as such.)

**Rollups** — AttributionService grows `dimensionAttribution()`, all computed as
visitor-first-touch → identified contact → won-deal revenue:

- keywords: by `utm_term` (ATTR-006)
- ads: by `utm_content` (ATTR-011)
- landing pages: by `first_path` (ATTR-007)
- content: `first_path` resolved to the SitePage (`/s/{slug}`) or ContentPiece
  (by its URL path) it belongs to, labeled with the content's title; unresolved
  paths stay raw rather than being guessed (ATTR-008)
- forms: earliest FormSubmission per contact → revenue per form (ATTR-009)
- calls: earliest call per contact → revenue per call source, with call counts
  (ATTR-010 — the tracking-number source/campaign inheritance was already
  tested)

Unidentified visitors and contacts with no won deals contribute nothing —
buckets only ever contain revenue that actually closed.

**Surface** — the attribution page gains a "Revenue by dimension" section, one
table per dimension.

**SUPP-002** — Phase 21 made files attachable to tickets; the support screen
never showed them. Ticket cards now list their attachments (download links) and
carry an upload control posting through the existing scanned, tenant-checked
`files.store` endpoint with the ticket preset.

## Tests (tests/Feature/Qa/AttributionSupportCloseoutTest.php)

1. Pixel captures term/content/landing path, all first-touch set-once across
   later visits.
2. Keyword + ad revenue rollups join visitor → contact → won deals; anonymous
   visitors and unwon contacts contribute nothing.
3. Landing-page + content attribution resolve titles for known pages and keep
   unknown paths raw.
4. Form + call attribution bucket by form name and call source.
5. Ticket attachments: upload lands on the ticket through the scanner, the
   support page lists it, cross-tenant target refused (re-pinned).
