# Phase 12 — Multi-Location MSP close-out

Register target: MLOC-005/006/008/009/010/011 (6 rows). MLOC-002 stays Partial honestly —
managing multiple live Google Business Profiles needs the GBP API (blocked outbound 443);
the per-branch place-id mapping, NAP records and citation checking already exist.

## Design

**Per-branch marketing scoping** — one migration adds a nullable `seo_location_id` to
`ad_campaigns` and `content_pieces`; the campaign and content controllers accept the
binding (TenantExists-validated). `LocationService::report()` grows per-branch columns
from real records: citations (total/consistent), geo keywords matching the branch city,
scoped campaigns, scoped content (MLOC-005/006).

**Regional calendar (MLOC-008/011)** — `LocationService::regionalCalendar()` groups
content pieces and campaigns by branch, with unscoped items under "Central" — the
centralized-marketing view is the same calendar, so central vs regional is one screen.

**Brand compliance (MLOC-010)** — `LocationService::brandCompliance()`: deterministic
per-branch checks on first-party data only — published location page exists, branch NAP
complete, page title carries the city or branch name, meta description present, brand
tagline present in page sections when the profile defines one. Each failure names its fix.

**Franchise support (MLOC-009)** — minimal but real parent/child hierarchy:
`organizations.parent_organization_id`, a `FranchiseService` with `linkChild` (only a
user who owns BOTH organizations may link), `rollup` (per-child contacts, won value,
published pages — explicit withoutGlobalScope reads scoped to linked child ids, the
established cross-org pattern), and `pushBrand` (copy the franchisor brand profile +
entity fields down to a child). Surface: `settings/franchise` (organization.view /
organization.update). Unlink restores independence; a child shows its franchisor.

## Tests (tests/Feature/Qa/MultiLocationFranchiseTest.php)

1. Per-branch report rolls up citations, geo keywords, scoped campaigns/content.
2. Regional calendar groups by branch + Central; brand compliance flags a deviating
   branch and passes a compliant one.
3. Franchise: owner-of-both links a child, rollup reflects real child records, brand
   push lands in the child profile.
4. Authorization: non-owner cannot link; child of another parent 404s; viewer read-only.
5. Tenant isolation: linking never leaks other tenants' data into normal queries.
