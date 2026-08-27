# Module Completion Report — CRM Data Tables (Module 03)

Date: 2026-08-27 · Spec: `docs/module-spec-crm-tables.md` · No new tables (wired the dormant
`saved_views` table from Stage 5).

## What shipped

1. **Contacts, the flagship table**: filter bar (search + lifecycle + source + owner, sources offered
   only where values exist), allow-listed sortable columns (name, email, lead_score, created_at —
   request input maps through a constant, never into `orderBy` raw), lifecycle **stage pills**
   (funnel-colored) and **score cells** with hot/warm/cold temperature, filtered empty state.
2. **Bulk actions** (`POST crm/contacts/bulk`, ≤100 ids, every id `TenantExists`-validated):
   assign owner, set lifecycle stage, delete — the route requires `crm.contact.update`, delete
   re-checks `crm.contact.delete` inside; one audit entry per operation with the count.
3. **Saved views (CRM-030)**: save the current filters+sort as a personal view, apply from the
   header, delete your own — another member's views are invisible and undeletable (403), another
   tenant's 404 via the scoped binding.
4. **Leads**: sortable name/company headers (status filter + pills already existed).
   **Companies**: sortable name/contacts/deals headers.
5. `Contact::LIFECYCLE_STAGES` added as the single source for validation and pickers.

## Gate

- Pest **760 passed (3,089 assertions)** — `CrmTableTest` (7 tests, 46 assertions): filters, sort
  allow-list (junk column → validation error), pill payload, bulk happy paths + audit, viewer/analyst
  forbidden, cross-tenant bulk ids rejected with nothing changed, saved-view personality + tenancy,
  leads/companies sort.
- Vitest **43 passed** · Pint, PHPStan, Prettier, ESLint, tsc clean · assets rebuilt.
- Live verification: `?lifecycle=sql&sort=lead_score&dir=desc` renders 5 SQL contacts ordered
  82→68, all pills/checkboxes/sort buttons present, no overflow.

## Register

DSGN-005 → **Tested**, CRM-029 → **Tested**, CRM-030 → **Tested**.
Totals: **706 Tested / 306 Partially Implemented / 170 Planned / 8 Implemented / 1 N/A** of 1,191.

## Out of scope, unchanged

Column show/hide, shared views, bulk add-to-list (marketing permission boundary), inline editing.
