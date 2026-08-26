# 07 — Security Architecture

Security is backend-enforced; frontend gating is UX only (Master Prompt §21, §66.16).

## Authentication

- Password hashing with argon2id/bcrypt; configurable password policy.
- Server-side sessions with secure, HttpOnly, SameSite cookies (web) and scoped bearer tokens (API).
- MFA-ready: TOTP enrollment + recovery codes shipped in Stage 1 architecture, enforced per-org policy later.
- Brute-force protection: per-account and per-IP throttling, lockout with audit events.
- Email verification required before tenant data access.

## Authorization & isolation

- RBAC policy checks on every endpoint (see [02](02-roles-and-permissions.md)).
- Tenant isolation via layered scoping (see [03](03-multi-tenancy.md)) with a CI cross-tenant test suite.

## Application security (OWASP-aligned)

- CSRF tokens on all state-changing web requests.
- XSS: contextual output encoding, CSP headers, sanitized rich-text.
- SQLi: ORM/parameterized queries only; no string-built SQL.
- SSRF: outbound fetches (webhooks, URL imports, crawlers) go through an allowlist-and-metadata-IP-blocking egress helper.
- Security headers: HSTS, X-Content-Type-Options, frame-ancestors, referrer-policy.
- Rate limiting on auth, API, and expensive endpoints; abuse alerting.
- File uploads: type/size validation, content sniffing, isolated storage, no execution paths.

## Secrets & crypto

- Secrets only in environment/secret manager — never in repo, frontend bundles, or API responses
  (API keys shown once at creation).
- TLS everywhere; at-rest encryption for OAuth tokens, integration credentials, MFA seeds and other
  sensitive columns (app-level envelope encryption).
- Key rotation procedure documented.

## Webhooks & third parties

- Inbound: signature verification per provider, idempotent processing.
- Outbound: signed payloads (HMAC), per-endpoint secrets, retry with backoff.
- Least-privilege OAuth scopes; tokens revocable from integration settings.

## Auditing & monitoring

- AUDIT module records security-relevant events with actor/tenant/IP/device.
- Security log stream + alerting on anomalies (failed-login spikes, permission escalations,
  mass exports, impersonation usage).

## Security testing

- Static analysis + dependency audit in CI (blocking).
- Authorization/isolation test suites per module gate.
- Pre-launch checklist per Master Prompt §63; external pentest before GA is recommended.
