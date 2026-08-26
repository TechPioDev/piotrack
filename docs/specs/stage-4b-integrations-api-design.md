# Module Specification — Stage 4 continuation (INTG · API · DSGN)

Completed 2026-08-14 before implementation. Finishes the Core-Platform modules deferred from Stage 4.

## Scope

- **INTG** — the reusable connector framework (registry, connect/disconnect/reconnect, sync engine,
  per-connector health + error logs) with one fully-working demo connector. Real third-party
  connectors (Google/Microsoft/social/CRM/comms — INTG-004…010) stay Planned: they need OAuth apps
  and credentials that don't exist in this environment.
- **API** — a public, versioned REST API (`/api/v1`) authenticated with the Sanctum tokens from
  Stage 1, tenant-scoped, entitlement-gated (higher plans), with a standard envelope, error format,
  request IDs, pagination, rate limiting, and idempotency keys. Plus written API docs.
- **DSGN** — formal design-system documentation of the already-built token system and component
  library, and the data-table / dashboard / empty-state / confirmation / loading standards.

## INTG (INTG-001/002/003 → tested framework; 004…010 → Planned)

Data (tenant-scoped via BelongsToTenant):

- `integrations` (organization_id, provider, name, status [connected|disconnected|error],
  credentials [encrypted], scopes [json], settings [json], last_synced_at, last_error, timestamps),
  unique(organization_id, provider).
- `sync_runs` (organization_id, integration_id, status [running|success|failed], started_at,
  finished_at, records, error).

Code:

- `ConnectorRegistry` — the catalog of available connectors (like PlanCatalog): key, name, category,
  auth type (none|api_key|oauth), whether connectable in this environment.
- `IntegrationService` — connect (store encrypted credentials), disconnect, reconnect, and sync
  (records a `sync_run`, updates health, catches failures → status `error`). Sync is dispatched as a
  queued `RunIntegrationSync` job (JOBS).
- Health is derived: connected + recent success = healthy; error/failed = degraded.
- A working **demo connector** (`api_key` auth) whose sync records a run so connect → sync → history
  → disconnect is proven end to end; a credential sentinel forces a failure for the dunning-style test.

RBAC: new `integrations.view` / `integrations.manage`. Owner/Admin/Managers manage; others view.
UI: `settings/integrations` — available connectors, connected list with health, connect form,
disconnect/reconnect, sync-now, and recent sync runs. Audit events on connect/disconnect/sync.

## API (API-001…005)

- `routes/api.php` `v1` group. Middleware: `auth:sanctum` → `SetApiOrganization` (resolves the tenant
  from an `X-Organization-Id` header validated against membership, else the user's current
  organization) → `entitlement:api` (API-005: higher plans only) → `throttle:api` → per-endpoint
  `can:` gates. Unsafe methods honor an `Idempotency-Key` header (API-004).
- Envelope: lists return `{ data: [...], meta: { current_page, last_page, per_page, total } }`;
  single returns `{ data: {...} }`. Errors use `{ message, errors }` with a 4xx/5xx status and the
  `X-Request-Id` header (Stage 0). Versioning: URI prefix `/api/v1`.
- Endpoints (reusing CRM permissions): `GET /contacts` (paginated, `?search=`), `GET /contacts/{id}`,
  `POST /contacts` (idempotent), `GET /companies`, `GET /deals`, and `GET /user` (existing).
- Docs: `docs/api/README.md` (auth, org header, versioning, errors, pagination, rate limits,
  idempotency, endpoint reference).

## DSGN (DSGN-001…009)

`docs/design-system.md` documenting: color/typography/spacing/icon tokens (theme-aware), the
component library inventory, responsive breakpoints, WCAG-oriented practices, and the standards for
data tables, dashboards, empty states, destructive confirmations, and loading/error/retry states —
each pointing at the real implementation. Most are Tested/in-use; documentation is the deliverable.

## Business rules

- One integration per (organization, provider). Credentials are encrypted at rest and never returned
  to the client. Disconnect clears credentials.
- API requests without a resolvable organization → 400; without the `api` entitlement → 403; over the
  rate limit → 429. A repeated `Idempotency-Key` replays the first response without re-executing.

## Tests

- INTG: connect (creds encrypted, status connected), sync success records a run + updates health,
  sync failure → status error + failed run, disconnect clears creds, tenant isolation, RBAC (viewer
  can't manage).
- API: token auth required (401 without), org scoping (only the org's records; cross-org 404),
  pagination + search, standard error envelope, idempotency (duplicate key → same response, one
  create), entitlement gate (Growth trial → 403; Professional → 200), permission gate.
- Stages 1–5 stay green.

## Acceptance criteria (gate)

Connector framework connects/syncs/disconnects a demo connector with health + history and isolation;
API v1 authenticates via tokens, scopes to the tenant, gates by plan, paginates, returns the standard
envelope + errors, and is idempotent; design-system docs published. Full quality gate green; browser
QA of the integrations page; Module Completion Report with honest Planned status for real connectors.
