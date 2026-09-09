# Piotrack Public API — Reference (v1)

**Base URL:** `https://<your-piotrack-host>/api/v1` · **Format:** JSON · **Status:** stable.
Everything documented here is enforced in code and pinned by the automated suite (see
`tests/Feature/Qa/ApiPlatformCloseoutTest.php` and `tests/Feature/Qa/ApiDocsTest.php`,
which fails the build if an API route is added without being documented on this page).

---

## Authentication

Every request carries a personal access token (Laravel Sanctum) as a bearer header:

```
Authorization: Bearer <token>
```

Tokens are issued in the app at **Settings → API tokens**. The token is shown once at
creation; store it securely. Revoking the token there ends its access immediately.

`GET /api/user` (outside the v1 group) returns the authenticated token's user.

## Tenant scoping

Piotrack is multi-tenant, so every v1 request must name the organization it acts in:

```
X-Organization-Id: <numeric organization id>
```

The header is validated against the caller's own memberships. Responses:

| Situation | Status | Body |
| --- | --- | --- |
| Missing/invalid token | 401 | `{"message": "Unauthenticated.", "errors": []}` |
| Header missing | 400 | `{"message": "No organization context. Pass an X-Organization-Id header.", ...}` |
| Header not numeric | 400 | `{"message": "X-Organization-Id must be a numeric organization id.", ...}` |
| Not a member of that organization | 400 | `{"message": "You are not a member of the requested organization.", ...}` |
| Plan's API-call allowance exhausted | 429 | `{"message": "API call limit for the current period reached.", ...}` (this refusal is **not** counted against the allowance) |

Access additionally requires the organization's plan to include the `api` feature
(entitlement-gated), and each endpoint enforces the same per-user permission the web app
uses (e.g. `crm.contact.read`) — a token can never do more than its user can.

## Rate limiting & idempotency

- **60 requests/minute per token** (per IP when unauthenticated). Exceeding it returns
  429 with the standard `Retry-After` header.
- **Idempotency:** send an `Idempotency-Key` header on any POST/PATCH. The first
  successful response is cached and replayed verbatim for any repeat with the same key,
  scoped to your token + organization + endpoint — a retried create can never make two
  records. Requests without the header are unaffected.

## Conventions

- **Envelope:** single resources return `{"data": {...}}`; lists return
  `{"data": [...], "meta": {"current_page", "per_page", "total", "last_page"}}`.
- **Errors:** `{"message": "...", "errors": {field: [messages]}}` — validation failures
  are 422.
- **Tracing:** every response carries an `X-Request-Id` header; quote it when reporting
  an issue (it appears in the platform's structured logs).
- **Pagination:** `?per_page=` (1–100, default 25) and `?page=`.
- **Sorting:** `?sort=<field>` ascending or `?sort=-<field>` descending, from each
  endpoint's whitelist below. Unknown fields are a 422, never a raw ORDER BY.
- **Money:** integer **minor units** (cents). `value: 450000` is $4,500.00.
- **Deletes:** deliberately not exposed in v1. Deletion stays in the web app where it is
  audited and permission-gated per record.

---

## Contacts

### GET /api/v1/contacts — list
Permission `crm.contact.read`.

| Query param | Meaning |
| --- | --- |
| `search` | name/email search (max 100 chars) |
| `lifecycle_stage` | one of the platform lifecycle stages (e.g. `lead`, `mql`, `sql`, `customer`) |
| `lead_source` | exact match |
| `company_id`, `owner_id` | numeric filters |
| `sort` | `id`, `created_at`, `lead_score`, `last_name` (± prefix) — default newest first |
| `per_page`, `page` | pagination |

### POST /api/v1/contacts — create
Permission `crm.contact.create`. Body fields: `first_name` (required), `last_name`,
`email`, `phone`, `title`, `company_id`, `lead_source`, `owner_id` (both id fields must
belong to the organization; `owner_id` defaults to the token's user). A duplicate `email`
in the organization is refused with 422. Creation counts against the plan's contact
limit; exceeding it is a 422 naming the limit. Returns **201** with the contact.

### GET /api/v1/contacts/{id} — fetch
Permission `crm.contact.read`. 404 for another tenant's contact — ids never leak.

### PATCH /api/v1/contacts/{id} — update
Permission `crm.contact.update`. Same fields as create (all optional, `sometimes`
semantics) plus `lifecycle_stage`. Duplicate-email refusal excludes the record itself.

**Contact object:** `id`, `first_name`, `last_name`, `name`, `email`, `phone`, `title`,
`lifecycle_stage`, `lead_score`, `lead_source`, `company {id, name}|null`,
`owner {id, name}|null`, timestamps.

## Companies

### GET /api/v1/companies — list
Permission `crm.company.read`. Filters: `search`, `industry`; sort whitelist: `id`,
`created_at`, `name`. Each row includes `contacts_count` and `deals_count`.

### POST /api/v1/companies — create
Permission `crm.company.create`. Fields: `name` (required), `domain`, `industry`,
`size`, `phone`, `website`. Returns **201**.

### GET /api/v1/companies/{id} — fetch
Permission `crm.company.read`.

### PATCH /api/v1/companies/{id} — update
Permission `crm.company.update`. Same fields, all optional.

## Deals

### GET /api/v1/deals — list
Permission `crm.deal.read`. Filters: `status` (`open|won|lost`), `pipeline_id`,
`stage_id`, `company_id`; sort whitelist: `id`, `created_at`, `value`.

### POST /api/v1/deals — create
Permission `crm.deal.create`. Fields: `name` (required), `contact_id`, `company_id`,
`value`, `mrr` (major units in the request; stored and returned as minor units),
`stage_id` (must belong to the default pipeline), `lead_source`,
`expected_close_date`, `owner_id`. Returns **201**.

### GET /api/v1/deals/{id} — fetch
Permission `crm.deal.read`.

### PATCH /api/v1/deals/{id} — update
Permission `crm.deal.update`. Same fields, all optional. Stage changes through the API
follow the same won/lost bookkeeping as the web board.

**Deal object:** `id`, `name`, `value` (minor units), `currency`, `status`,
`stage`, `company {id, name}|null`, `owner {id, name}|null`, `expected_close_date`,
timestamps.

---

## Outbound webhooks (the event stream)

Configured in the app at **Settings → Integrations** (not via the API). Piotrack POSTs
JSON events to your endpoint with:

```
X-Piotrack-Signature: hex HMAC-SHA256 of the raw request body, keyed with your endpoint secret
```

Verify by recomputing the HMAC over the exact raw body. Deliveries are queued with
retries (3 attempts); failures surface on the endpoint's health row, and every endpoint
has a test-ping button. This is also the working Zapier path (outbound to a Zapier
catch hook).

## Quick start

```bash
curl -s https://<host>/api/v1/contacts \
  -H "Authorization: Bearer $PIOTRACK_TOKEN" \
  -H "X-Organization-Id: 1"

curl -s -X POST https://<host>/api/v1/contacts \
  -H "Authorization: Bearer $PIOTRACK_TOKEN" \
  -H "X-Organization-Id: 1" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{"first_name": "Ada", "email": "ada@example.com", "lead_source": "api"}'
```
