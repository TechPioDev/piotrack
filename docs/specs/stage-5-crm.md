# Module Specification — CRM (Stage 5)

Stage 5. Spec completed 2026-08-13 before implementation. Depends on Stages 0–4.
The CRM is the first product module and the backbone for marketing, sales and attribution.

## Scope

Complete, tested CRM core: **companies, contacts, leads, deals (opportunities), pipelines &
stages, activities** (notes/tasks/calls/emails/meetings), with CRUD, tenant isolation, RBAC,
duplicate detection, lead conversion, filters, saved views, global search integration, CSV
import/export, and audit history.

**Honest partials (register notes):** contact enrichment (CRM-027 — needs a data provider/integration,
deferred); automated lead assignment (CRM-025 — creator-owner default + manual reassign now; rule
engine later); Excel/PDF export (IMEX-003/004 — CSV done, Excel/PDF later); lead scoring is a
separate module (LSCR, Stage 10).

## Feature IDs

CRM-001…030, IMEX-001…004.

## Data model (all tenant-scoped via BelongsToTenant unless noted)

- `companies` (name, domain, industry, size, phone, website, address, owner_id) + soft deletes
- `contacts` (company_id?, first_name, last_name, email, phone, title, lead_source, campaign,
  owner_id) + soft deletes
- `leads` (first_name, last_name, email, phone, company_name, source, campaign, status
  [new|working|qualified|unqualified|converted], owner_id, converted_contact_id, converted_deal_id,
  converted_at) + soft deletes
- `pipelines` (name, is_default) ; `pipeline_stages` (pipeline_id, name, sort_order, is_won, is_lost)
- `deals` (pipeline_id, stage_id, name, contact_id?, company_id?, value, mrr, arr,
  contract_term_months, ltv, status [open|won|lost], lead_source, campaign, owner_id,
  marketing_owner_id, expected_close_date, closed_at) + soft deletes
- `activities` (polymorphic subject: contact|company|lead|deal; type [note|task|call|email|meeting];
  user_id author; body; due_at; completed_at; occurred_at) + soft deletes
- `saved_views` (user_id, resource, name, filters json)
- `import_jobs` (user_id, resource, filename, status, total, imported, skipped, failed, errors json) — import history

## RBAC (new permissions)

`crm.contact.{read,create,update,delete}`, `crm.company.{...}`, `crm.lead.{...}`,
`crm.deal.{...}`, `crm.activity.manage`, `crm.import`. Grants: Owner/Admin = all; Marketing & Sales
Managers = all CRM; Marketing User & Sales Rep = read/create/update + activities (no delete, no
import); Analyst = read only; Viewer = read only (contacts/companies/deals).

All CRM routes also require the `crm` plan feature (`entitlement:crm` — every plan includes it).

## Business rules

- Duplicate detection: creating a contact with an email already in the organization is rejected
  (CRM-026); import skips duplicates and reports them.
- Lead conversion (CRM-003→005/008): creates/links a company (from company_name), creates a contact,
  optionally opens a deal in the default pipeline, marks the lead `converted` with links.
- Deal money fields (value/MRR/ARR/LTV) in minor units; win/loss sets status + closed_at + a won/lost
  stage; attribution fields (lead_source, campaign, owner, marketing_owner) preserved (feeds ATTR).
- New records default `owner_id` to the creating user (CRM-023/025 basic); owner is reassignable.
- Every create/update/delete records an audit event (satisfies AUDIT-004).

## Endpoints (tenant-scoped, verified, `entitlement:crm`, `can:crm.*`)

Resourceful `crm/contacts`, `crm/companies`, `crm/leads`, `crm/deals` (index with filters+pagination,
create, store, show, edit, update, destroy). `crm/leads/{lead}/convert`. `crm/deals/{deal}/stage`,
`.../won`, `.../lost`. `crm/activities` (store/update/complete/destroy, polymorphic). Pipelines
managed under a default seed; `crm/pipelines` read + basic manage. Saved views CRUD. Import:
`crm/contacts/import` (upload→preview), confirm; `crm/contacts/export` (CSV stream).

## Import pipeline (IMEX-001/002)

Upload CSV → detect headers → map to fields → validate rows (email format, required) → detect
duplicates (existing emails + within-file) → **preview** (valid/invalid/duplicate counts + sample)
→ confirm → create contacts, write an `import_jobs` record with counts + per-row errors (history).

## Search (CRM-028)

Extend `GlobalSearch` with contacts (name/email), companies (name/domain), deals (name) — grouped
and gated by the respective `crm.*.read` permission.

## Frontend

Sidebar CRM group (Contacts, Companies, Leads, Deals). Index data tables (search, owner/status
filter, pagination, saved views). Contact/company/deal detail with an activity timeline (add
note/task/call/email/meeting; complete tasks). Leads table with a Convert action. Deals kanban board
grouped by stage with stage-change + won/lost. Import wizard (upload → map → preview → confirm).
Permission-aware controls; deliberate empty states.

## Tests

- CRUD + validation for each entity; tenant isolation (cross-tenant 404, list no-leak);
  RBAC allow/deny per role; duplicate-contact rejection; lead conversion (contact+company+deal +
  status); activities on a contact; deal stage move + won/lost; import (map/validate/dedup/preview/
  commit + history + error report); CSV export; search grouping + isolation.
- Stages 1–4 stay green.

## Acceptance criteria (gate)

Full CRM CRUD with isolation + RBAC; lead workflow (lead → contact → deal) works end-to-end;
activities timeline; deals board with stage/win/loss; import pipeline with dedup + history; CSV
export; search finds CRM records tenant-scoped. Full quality gate green; browser QA; Module
Completion Report with honest status for partials.
