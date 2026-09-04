# piotrack Public API v1

The piotrack API is a token-authenticated, tenant-scoped REST API over the CRM
data model. It is versioned under `/api/v1` and returns JSON.

> **Availability.** The API is a plan feature (`api`). It is included on the
> **Professional**, **Agency**, and **Enterprise** plans. Requests from
> organizations on other plans receive `403 Forbidden`.

---

## Authentication

The API uses [Laravel Sanctum](https://laravel.com/docs/sanctum) personal access
tokens. Create a token under **Settings → API tokens**, then send it as a bearer
token:

```
Authorization: Bearer <token>
Accept: application/json
```

Requests without a valid token receive `401 Unauthorized`.

## Choosing an organization

A token belongs to a user, and a user can be a member of several organizations.
Select the organization a request acts within with the `X-Organization-Id`
header:

```
X-Organization-Id: 42
```

- If the header is present, it must be a numeric id of an organization the token
  owner is an **active member** of, otherwise the request fails with `400`.
- If the header is omitted, the user's current organization is used.
- If neither resolves to a membership, the request fails with `400`.

All data is isolated per organization; you can never read or write another
tenant's records, regardless of the ids you pass.

## Permissions

Every endpoint is additionally gated by the caller's role in the selected
organization (the same RBAC permissions as the web app). For example, creating a
contact requires `crm.contact.create`. A caller lacking the permission receives
`403 Forbidden`.

## Rate limiting

Requests are limited to **60 per minute** per token (falling back to client IP).
Exceeding the limit returns `429 Too Many Requests` with a `Retry-After` header.

## Idempotency

Send an `Idempotency-Key` header on unsafe requests (`POST`/`PUT`/`PATCH`/
`DELETE`) to make retries safe:

```
Idempotency-Key: 6f9619ff-8b86-d011-b42d-00cf4fc964ff
```

The first successful response for a given key is stored for 24 hours and
replayed for any repeat with the same key (scoped to caller + organization +
method + path). Replayed responses carry `Idempotent-Replayed: true`. This
prevents a retried create from producing a duplicate record.

## Request tracing

Every response carries an `X-Request-Id` header. Send your own (8–64 chars of
`A–Z a–z 0–9 . _ -`) to correlate calls end-to-end; one is generated otherwise.

---

## Response envelope

**Single resource:**

```json
{ "data": { "id": 1, "name": "Ada Lovelace" } }
```

**Collection** (paginated):

```json
{
    "data": [{ "id": 1, "name": "Ada Lovelace" }],
    "meta": { "current_page": 1, "per_page": 25, "total": 1, "last_page": 1 }
}
```

**Error:**

```json
{ "message": "The given data was invalid.", "errors": { "first_name": ["The first name field is required."] } }
```

| Status | Meaning                                             |
| ------ | --------------------------------------------------- |
| `200`  | OK                                                  |
| `201`  | Created                                             |
| `400`  | Bad organization context                            |
| `401`  | Missing/invalid token                               |
| `403`  | Plan does not include the API, or permission denied |
| `404`  | Resource not found in this organization             |
| `422`  | Validation failed                                   |
| `429`  | Rate limit exceeded                                 |

---

## Endpoints

All paths are relative to `https://piotrack.com/api/v1`.

### Contacts

| Method  | Path             | Permission           | Notes                                                                                                                                                          |
| ------- | ---------------- | -------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET`   | `/contacts`      | `crm.contact.read`   | Query: `search`, `per_page` (1–100, default 25), filters `lifecycle_stage`, `lead_source`, `company_id`, `owner_id`, `sort` (`id`, `created_at`, `lead_score`, `last_name`, each also as `-field` for descending) |
| `GET`   | `/contacts/{id}` | `crm.contact.read`   |                                                                                                                                                                |
| `POST`  | `/contacts`      | `crm.contact.create` | Body below; rejects duplicate email in the org                                                                                                                 |
| `PATCH` | `/contacts/{id}` | `crm.contact.update` | Same fields plus `lifecycle_stage`; duplicate email refused                                                                                                     |

**Create body:**

| Field         | Rules                                    |
| ------------- | ---------------------------------------- |
| `first_name`  | required, string, max 120                |
| `last_name`   | nullable, string, max 120                |
| `email`       | nullable, email, max 255, unique per org |
| `phone`       | nullable, string, max 40                 |
| `title`       | nullable, string, max 120                |
| `company_id`  | nullable, must belong to the org         |
| `lead_source` | nullable, string, max 120                |
| `owner_id`    | nullable, must be an org member          |

### Companies

| Method  | Path              | Permission           | Notes                                                                                                  |
| ------- | ----------------- | -------------------- | ------------------------------------------------------------------------------------------------------ |
| `GET`   | `/companies`      | `crm.company.read`   | Query: `search` (name/domain), `per_page`, filter `industry`, `sort` (`id`, `created_at`, `name`, `-…`) |
| `GET`   | `/companies/{id}` | `crm.company.read`   |                                                                                                        |
| `POST`  | `/companies`      | `crm.company.create` | `name` required; `domain`, `industry`, `size`, `phone`, `website` optional                              |
| `PATCH` | `/companies/{id}` | `crm.company.update` | Same fields, all optional                                                                              |

### Deals

| Method  | Path          | Permission        | Notes                                                                                                                          |
| ------- | ------------- | ----------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `GET`   | `/deals`      | `crm.deal.read`   | Query: `status` (`open`/`won`/`lost`), `per_page`, filters `pipeline_id`, `stage_id`, `company_id`, `sort` (`id`, `created_at`, `value`, `-…`) |
| `GET`   | `/deals/{id}` | `crm.deal.read`   |                                                                                                                                |
| `POST`  | `/deals`      | `crm.deal.create` | `name` required; defaults to the default pipeline's first open stage; `stage_id` must belong to that pipeline                    |
| `PATCH` | `/deals/{id}` | `crm.deal.update` | Same fields; `stage_id` must belong to the deal's own pipeline                                                                  |

---

## Examples

List contacts:

```bash
curl https://piotrack.com/api/v1/contacts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "X-Organization-Id: 42"
```

Create a contact idempotently:

```bash
curl -X POST https://piotrack.com/api/v1/contacts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "X-Organization-Id: 42" \
  -H "Idempotency-Key: 6f9619ff-8b86-d011-b42d-00cf4fc964ff" \
  -d '{"first_name":"Ada","last_name":"Lovelace","email":"ada@example.com"}'
```

---

## Status & roadmap

**Implemented and tested (this release):** authentication, organization scoping,
plan gating, rate limiting, idempotency, request tracing, the response envelope,
read + create + update endpoints for contacts/companies/deals, and whitelisted
list filtering and sorting (`?sort=field` / `?sort=-field`; unknown fields are a
422, never leaked into ORDER BY).

**Deliberately out of scope:** deletes — destructive operations stay in the web
app where they are audited through their own flows.

**Planned:** activities, cursor pagination,
and webhooks. These are tracked in the Feature Traceability Register under the
`API-*` and `INTG-*` identifiers.
