# Module Specification — Identity & Authentication (AUTH)

Stage 1. Spec completed 2026-08-12 before implementation (Master Prompt §58–59).

## Purpose

Secure platform identity: registration through verified login, hardened session handling,
MFA, and personal API tokens — the base every later module authenticates against.

## Users & roles

All platform users. No RBAC yet (Stage 2); every capability here is self-service on the
authenticated user's own account. Future org-level enforcement (password policies per org,
mandatory MFA) is prepared but not built.

## Feature IDs & gap analysis vs starter kit

| ID       | Feature                     | Kit status                      | Stage 1 work                                                                                  |
| -------- | --------------------------- | ------------------------------- | --------------------------------------------------------------------------------------------- |
| AUTH-001 | Registration                | ✅ present                      | Audit event; keep tests green                                                                 |
| AUTH-002 | Email verification          | ⚠️ routes exist, unenforced     | `MustVerifyEmail` on User + `verified` middleware on app routes; audit event                  |
| AUTH-003 | Password strength           | ⚠️ `Password::defaults()` unset | min 12 everywhere; `uncompromised()` in production; update weak test passwords                |
| AUTH-004 | Login/logout                | ✅ present                      | Audit events                                                                                  |
| AUTH-005 | Password hashing            | ✅ bcrypt(12)                   | Verify config; document choice (cross-platform; argon2id revisit at deploy)                   |
| AUTH-006 | Sessions & secure cookies   | ⚠️ partial                      | DB sessions ✓; secure-cookie env documented; **logout other browser sessions** (revocability) |
| AUTH-007 | Brute-force protection      | ⚠️ login only (5/min + Lockout) | Keep; add route throttles to register/forgot/reset/confirm + 2FA challenge; lockout test      |
| AUTH-008 | Failed-login audit events   | ❌                              | Audit-log foundation (below)                                                                  |
| AUTH-009 | Password reset              | ✅ present                      | Audit event                                                                                   |
| AUTH-010 | MFA (TOTP + recovery codes) | ❌                              | Full enrollment/challenge implementation                                                      |
| AUTH-011 | API keys                    | ❌                              | Sanctum personal access tokens + settings UI + audit                                          |

## Database entities

- `users` + new columns: `two_factor_secret` (text, encrypted), `two_factor_recovery_codes`
  (text, encrypted JSON), `two_factor_confirmed_at` (timestamp).
- `audit_logs` (platform-wide foundation, extended by AUDIT in Stage 2): uuid PK, nullable
  `tenant_id` (FK later — orgs don't exist yet), nullable `actor_id` → users, `action` (indexed),
  nullable `resource_type`/`resource_id`, `context` JSON, `ip_address`, `user_agent`, `created_at`.
  Append-only: no updates, no soft deletes.
- `personal_access_tokens` (Sanctum standard).

## API endpoints (web, session-authenticated unless noted)

- Existing kit auth routes (unchanged paths) + throttling.
- `GET/POST /two-factor/challenge` (guest with pending-2FA session; throttle 5/min).
- `POST /settings/two-factor` enable → returns secret + otpauth URI + QR; `POST .../confirm`
  (code) → activates + returns recovery codes; `DELETE /settings/two-factor` (password confirm)
  disable; `POST /settings/two-factor/recovery-codes` regenerate (password confirm).
- `GET /settings/api-tokens` list; `POST` create (name) → plaintext token shown once;
  `DELETE /settings/api-tokens/{id}` revoke.
- `DELETE /settings/sessions` log out other browser sessions (password required).
- `GET /api/user` (auth:sanctum) — canonical token-authenticated endpoint.

## UI pages

- `settings/two-factor` (status card, enable→QR+confirm, recovery codes, disable) — new.
- `settings/api-tokens` (create dialog, one-time token reveal, list with revoke) — new.
- `auth/two-factor-challenge` (code or recovery code) — new.
- `settings/password` gains "Log out other browser sessions" section.
- Settings nav gains Two-factor auth + API tokens entries.

## Business rules

- 2FA challenge: after valid credentials, users with confirmed 2FA are NOT logged in until a valid
  TOTP (±1 window) or unused recovery code is provided; pending state lives in session for ≤10 min.
- Recovery codes: 8 codes, single-use, regenerable, displayed only at generation time.
- 2FA disable and recovery-code regeneration require recent password confirmation.
- API tokens: plaintext shown exactly once; names required; revocation immediate.
- Email verification required for dashboard/settings (app surface), not for logout/verification routes.

## Error cases

Invalid/expired challenge codes (throttled), reused recovery code, weak password with clear
message, expired pending-2FA session → back to login, revoked token → 401 on API.

## Audit events (action names)

`auth.registered`, `auth.email_verified`, `auth.login`, `auth.logout`, `auth.login_failed`,
`auth.lockout`, `auth.password_reset`, `auth.password_changed`, `auth.two_factor_enabled`,
`auth.two_factor_disabled`, `auth.recovery_codes_regenerated`, `auth.api_token_created`,
`auth.api_token_revoked`, `auth.other_sessions_revoked`.

## Automated tests

Kit suites (updated for password policy) + new: password policy rejection; verified-middleware
redirect; lockout after 5 failures + audit rows; audit events for login/logout/failed/reset;
2FA enroll→confirm→challenge→recovery-code flows (real TOTP via google2fa); 2FA disable;
API token create/list/revoke + one-time reveal + audit + `/api/user` access & post-revoke 401;
logout-other-sessions.

## Manual QA

Register→verify→login→enable 2FA→logout→login w/ challenge→recovery code; API token lifecycle;
responsive check of new pages (desktop/tablet/mobile); error paths (bad codes, weak passwords).

## Acceptance criteria (gate)

Per docs/architecture/11-acceptance-criteria.md AUTH row: signup→verify→login→reset E2E green;
lockout + rate limits proven by tests; sessions revocable; MFA enrollable end-to-end; all audit
events recorded; full quality gate green.
