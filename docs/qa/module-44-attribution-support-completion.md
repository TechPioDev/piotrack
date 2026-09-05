# Module Completion Report — Revenue Attribution + Support, Phase 33

**Date:** 5 September 2026 · **Modules:** Revenue Attribution **17/17 Tested
(100%)**, was 11/17 · Customer Support Infrastructure **3/3 Tested (100%)**, was 2/3

## Revenue Attribution

The deferral said per-dimension revenue linkage "needs the tracking pixel" — the
pixel shipped with Visitor Intelligence and this phase gave it the missing
dimensions:

- **Capture** — the pixel now sends `utm_term` and `utm_content`; the tracker
  stores them first-touch set-once like the existing UTM fields, and a new
  `visitors.first_path` locks on the first pageview (later visits never overwrite
  any of them — asserted).
- **Rollups** — `dimensionAttribution()` joins visitor first-touch → identified
  contact → won-deal revenue, per: keyword (ATTR-006), ad creative (ATTR-011),
  landing page (ATTR-007), content — the landing path resolved to its site page or
  content piece and labeled with the content title, unresolved paths kept raw
  rather than guessed (ATTR-008) — form via earliest FormSubmission (ATTR-009),
  and call source via earliest call (ATTR-010). Anonymous visitors and unwon
  contacts contribute nothing; buckets only contain revenue that actually closed.
- **Surface** — a "Revenue by dimension" section on the attribution page, one
  table per dimension.
- **Stated enrichments** — GSC/click-id joins remain later connector work for
  *organic* keyword and platform-click detail; UTM-based attribution is the
  standard mechanism and is labeled as such.

## Customer Support Infrastructure

**SUPP-002** — the one named gap was attachments: Phase 21 made files attachable
to tickets, Phase 31 put the scanner in front of every upload, and this phase
surfaced them — ticket cards list their documents with download links and carry
an upload control through the scanned, tenant-checked `files.store` endpoint
(cross-tenant targets refused, re-pinned).

## Gate evidence

- Pest: **980 passed / 4,764 assertions** (+5:
  [AttributionSupportCloseoutTest](../../tests/Feature/Qa/AttributionSupportCloseoutTest.php)).
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
