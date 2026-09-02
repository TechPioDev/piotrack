# Phase 14 — Competitive Intelligence close-out

Register target: CINT-005 (competitor content monitoring) — the one remaining row that is
buildable without a data vendor, using the in-house fetch philosophy (ADR-0005) the
Phase 11 site crawler established: a competitor's published content is on their public
website; monitoring it needs no provider.

Honestly still Planned (each note names its exact unblock):
CINT-002/003 (ad libraries — Meta Ad Library / Google Ads Transparency APIs),
CINT-004 (backlink index — Ahrefs/SEMrush class), CINT-006 (Google Places API),
CINT-007 (review-source APIs), CINT-009 (social platform APIs). All additionally
behind the blocked outbound 443.

## Design — CompetitorContentMonitor

`app/Services/Analytics/CompetitorContentMonitor.php`:

- `check(Competitor)` — SSRF-guarded fetch of the competitor's public site:
  `sitemap.xml` first (up to 15 same-host URLs), homepage links as the fallback
  discovery; each page fetched once for its title and a normalized body hash.
- Snapshot stored per run (`competitor_snapshots`: pages json, counts, diffs); the diff
  against the previous snapshot is computed at check time and stored with it:
  **new** URLs, **changed** pages (same URL, different content hash), **removed** URLs.
  First run is the baseline — everything is "new" exactly once.
- Nothing invented: a page that cannot be fetched is recorded as unfetchable, and a
  competitor with no reachable site yields an empty snapshot, not fabricated activity.

Surface: "Check content" per tracked competitor on /analytics/competitors (existing
`analytics.competitors.manage` permission), with the latest snapshot summary and the
changes it found. Route: POST `competitors/{competitor}/check-content`.

## Tests (tests/Feature/Qa/CompetitorContentMonitorTest.php)

1. Baseline snapshot: sitemap-discovered pages captured with titles+hashes, all new.
2. Second check: one added page, one edited page, one removed — diff names each exactly.
3. No sitemap: homepage-link fallback discovers pages.
4. A private/unfetchable domain is refused by the SSRF guard as a validation error.
5. Permission gating + tenant isolation on the endpoint and snapshots.
