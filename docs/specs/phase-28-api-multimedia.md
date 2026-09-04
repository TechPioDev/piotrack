# Phase 28 — API Platform + Podcast/Multimedia Authority close-out

Two small modules, one phase (the Phase 21 precedent).

Register target (5 rows): API-001 (filtering/sorting parity), API-005 (endpoint
coverage), POD-001 (podcast appearances), POD-004 (webinar promotion), POD-009
(social clips).

Stays honest: POD-008 (YouTube distribution — uploading video genuinely needs the
YouTube Data API via an INTG connector).

## API Platform

The v1 standard (envelope, Sanctum, tenant header, entitlement, throttle,
idempotency) is tested; the two Partial reasons are "full filtering/sorting parity"
and "coverage limited to CRM read + contact create."

- **Sorting**: every index takes `?sort=` from an explicit whitelist (`field` /
  `-field`), unknown values 422 — id/created_at everywhere, plus lead_score and
  last_name for contacts, name for companies, value for deals.
- **Filtering**: contacts by lifecycle_stage/lead_source/company_id/owner_id;
  companies by search (name/domain) and industry; deals by status (existing) plus
  pipeline_id/stage_id/company_id.
- **Coverage**: PATCH contacts/{id}; POST + PATCH companies; POST + PATCH deals
  (default pipeline/stage resolution mirrored from the web controller, stage must
  belong to the deal's pipeline). Each route carries its own `can:` permission;
  writes remain idempotency-deduped. Deletes stay out of the API deliberately
  (destructive, and the web app audits them through its own flows).
- **Docs**: docs/api/README.md gains the sort/filter parameters and new endpoints.

## Podcast / Multimedia Authority

- **Podcast appearances (POD-001)** — the earned-media pipeline learns the podcast
  shape: outreach campaign type `podcast_booking`, placement kind
  `podcast_appearance` (a won pitch records a typed AuthorityAsset with the episode
  URL). Appearances then ride the PR channel rollup and reputation assets that are
  already tested.
- **Webinar promotion (POD-004) + social clips (POD-009)** — new
  `MultimediaPromotion` service over the tested social pipeline:
  - `promote(piece)`: for webinar/video/podcast pieces, one scheduled announcement
    post per network (linkedin/facebook/x/youtube), staggered, linked via
    `content_piece_id`, copy scaffolded from the piece's own title/excerpt/url.
  - `clips(piece, n)`: n clip posts rotating networks, staggered daily, typed
    `clip`, each with guidance copy and an empty `media_url` for the tenant's cut —
    the platform schedules and distributes clips; video editing itself is
    production work, stated in the note.
  - Refused (422-style RuntimeException) for non-multimedia content types.
  - Endpoints + buttons on the content piece page (`content.pieces.manage`).

## Tests

- tests/Feature/Qa/ApiPlatformCloseoutTest.php: sorting whitelist (asc/desc + 422),
  filters per resource, the five new write endpoints round-trip with permission and
  cross-tenant checks, envelope intact.
- tests/Feature/Qa/MultimediaAuthorityTest.php: podcast campaign → won pitch →
  typed appearance asset; promote() creates one linked scheduled post per network;
  clips() staggers typed clip posts; article pieces refused; endpoints wired.
