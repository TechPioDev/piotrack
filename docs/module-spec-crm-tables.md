# Module Specification — CRM Data Tables (CRMT, "Module 03")

> Approved 2026-08-27 per the Phase 1 order. Spec per Master Prompt §58–59.

## Purpose

Give the CRM list pages the data-table standard the audit found missing (DSGN-005): filters beyond
search, sortable columns, status/lifecycle pills, bulk actions, and per-user saved views — with
Contacts as the flagship, Leads and Companies inheriting the pattern proportionately.

## Users & roles

Existing CRM permissions only: read gates viewing/filters/sorting/saved views (views are personal);
`crm.contact.update` gates bulk assign/stage; `crm.contact.delete` gates bulk delete. Viewer role
must be refused every bulk mutation (test-pinned).

## Feature IDs

DSGN-005 (data-table standard, Planned → Tested), CRM-029 (field filters, Partial → Tested),
CRM-030 (saved views UI, Partial → Tested). CRM-024 (marketing-owner UI) untouched.

## Database entities

None new — `saved_views` (organization_id, user_id, resource, name, filters json) exists since
Stage 5 with no UI; this module wires it.

## API endpoints

- `GET crm/contacts` gains validated params: `lifecycle` (enum), `source` (string), `company`
  (TenantExists), `owner` (member), `sort` ∈ {name, email, company, owner, lead_score, created_at} +
  `dir` ∈ {asc, desc} — **allow-listed, never raw input into orderBy**. Rows gain lifecycle_stage,
  lead_score, temperature (LeadScoringService), created_at. Props gain `sources` (distinct
  lead_source values) and `views` (the user's saved views for resource=contacts).
- `POST crm/contacts/bulk` (`can:crm.contact.update`): {action: assign|stage|delete, ids[] (≤100,
  tenant-validated), owner_id?, lifecycle_stage?}. Delete additionally requires
  `crm.contact.delete` (403 otherwise). One audit entry with count. Redirect back with status.
- `POST crm/contacts/views` (`can:crm.contact.read`): {name ≤60, filters object} → stores for the
  current user. `DELETE crm/contacts/views/{view}`: owner-only (403 for another user's view),
  tenant-scoped (404 cross-tenant).
- `GET crm/leads` gains `sort`/`dir` (name, company_name, score?, created_at — per existing columns)
  and keeps its status filter; `GET crm/companies` gains `sort`/`dir` (name, contacts_count,
  deals_count, created_at) — same allow-list rule.

## UI

- **Contacts**: filter bar (search, owner, lifecycle, source selects, clear); sortable headers with
  direction indicator; Stage pill (color per lifecycle) + Score cell (value + temperature color);
  row checkboxes + page select-all; bulk bar (assign owner, set stage, delete w/ confirm), each
  permission-gated; saved-views menu (apply, save current, delete own). Existing create dialog,
  import/export, pagination, empty state unchanged.
- **Leads**: sortable headers + status pills (existing filter kept).
- **Companies**: sortable headers.
- Pills use theme tokens; no new dependencies.

## Testing

Pest `CrmTableTest`: sort allow-list (works for allowed, ignored/rejected for junk), lifecycle +
source filters, bulk assign/stage/delete (rows change, audit written), viewer forbidden, bulk with
another tenant's id fails validation and changes nothing, saved views CRUD + isolation (other
user's and other tenant's views invisible; deleting another user's view 403s), leads/companies
sort. Vitest: existing suites stay green (page prop changes covered by Pest + smoke test).

## Out of scope

Column show/hide, server-side saved-view sharing across users, bulk add-to-list (crosses into
marketing permissions — later), Excel export, inline editing.
