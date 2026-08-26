# ADR-0002 — Custom code-defined RBAC over spatie/laravel-permission

**Status:** Accepted (2026-08-13) · Supersedes the RBAC note in
[09-technology-stack.md](../09-technology-stack.md) and refines [02-roles-and-permissions.md](../02-roles-and-permissions.md).

## Context

Piotrack is multi-tenant: a user can belong to several organizations and hold a **different role in
each** (Master Prompt §5). Authorization checks must always resolve against the user's role in the
_currently active organization_. ADR-0001 tentatively named `spatie/laravel-permission`.

Spatie's package is global by default. Its "teams" feature can scope roles to a team id (which we
would map to `organization_id`), but it requires setting a team context before every check, keeps a
global permission cache that is awkward per-request in a multi-tenant app, and stores role→permission
wiring in the database where we want it defined and reviewed in code.

## Decision

Build a **small, code-defined RBAC** owned by the app:

- **Permission registry in code** — `app/Authorization/Permission.php` enumerates every permission
  using `resource.action` naming (`members.invite`, `organization.delete`, …). Code is the single
  source of truth (Master Prompt §5, §11 "permission registry is code-defined").
- **Roles in code** — `app/Authorization/Role.php` (5 platform + 9 organization roles) each mapping
  to a set of permissions via `app/Authorization/RolePermissions.php`.
- **Membership carries the role** — the `organization_user` pivot stores the user's role _in that
  organization_. This is the natural home for per-org roles.
- **Enforcement through Laravel's Gate** — a `Gate::before` grants platform Super Admins everything;
  otherwise each permission is checked against the user's role in the resolved current organization.
  Controllers use `can:` middleware / `$this->authorize()`; the frontend receives the resolved
  permission list for UX gating only (never as the security boundary).

Roles remain **data-extensible later**: a `roles`/`role_permissions` table can be layered in without
changing call sites, because checks go through the Gate and the registry — but system roles ship in
code now for reviewability and zero-config correctness.

## Consequences

- Full control over the multi-org, role-per-membership model; no global-cache foot-guns.
- Permission list is greppable and reviewed in PRs; it grows per module (each module adds its
  `resource.action` permissions to the registry).
- We maintain the (small) engine ourselves instead of leaning on a maintained package — acceptable
  given the tight tenancy coupling and the modest surface.
- Frontend gating and backend enforcement read from the same registry, keeping them in sync.
