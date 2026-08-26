# Module Specification — Tenant Architecture (TEN · RBAC · AUDIT)

Stage 2. Spec completed 2026-08-13 before implementation. Depends on Stage 1 (AUTH).
Architecture: [03-multi-tenancy](../architecture/03-multi-tenancy.md),
[02-roles-and-permissions](../architecture/02-roles-and-permissions.md),
[ADR-0002](../architecture/adr/ADR-0002-rbac-approach.md).

## Purpose

The multi-tenant backbone: organizations (tenants), per-org memberships and roles, teams,
invitations, tenant data isolation, an RBAC engine, and a tenant-aware audit trail. Every later
module authorizes and scopes against this.

## Feature IDs

TEN-001…010, RBAC-001…006, AUDIT-001…006.

## Users & roles

Organization roles (per membership): Owner, Admin, Marketing Manager, Sales Manager, Sales
Representative, Marketing User, Analyst, Billing Administrator, Viewer. Platform roles (global
staff): Super Admin, Platform Admin, Support Admin, Finance Admin, Read-only Support — defined now;
the platform admin _area_ is Stage 13, so platform roles are wired into the engine but only Super
Admin's global bypass is exercised here.

## Data model

- `organizations`: id, name, slug (unique), owner_id → users, timestamps, soft deletes.
- `organization_user` (membership): id, organization_id, user_id, role, status
  (`active`/`deactivated`), joined_at, unique(organization_id,user_id).
- `teams`: id, organization_id, name, timestamps, soft deletes. Tenant-scoped.
- `team_user`: team_id, user_id, unique.
- `invitations`: id, organization_id, email, role, token (hashed), invited_by → users,
  expires_at, accepted_at, timestamps. Tenant-scoped.
- `users.current_organization_id` → organizations (nullable): remembered active tenant.
- `audit_logs`: rename `tenant_id` → `organization_id` (+FK, nullable for pre-tenant/platform events).

## RBAC engine (ADR-0002)

- `Permission` registry (resource.action). Stage 2 permissions: `organization.view/update/delete`,
  `members.view/invite/update/remove`, `teams.view/manage`, `roles.view`, `audit.view`,
  `settings.manage`. Grows per module.
- `Role` registry + `RolePermissions` map. Owner = all org permissions (Gate bypass within own org);
  Admin = all except `organization.delete`; managers/analyst/etc. get sensible read/'view' subsets;
  Viewer = `organization.view`.
- `Gate::before`: platform Super Admin → allow all. Each permission resolved against the user's role
  in the **current organization**.

## Tenant isolation

- `CurrentOrganization` request service; `SetCurrentOrganization` middleware resolves from
  `users.current_organization_id` (validated membership) and shares to Inertia.
- `EnsureHasOrganization` middleware: users with no org are sent to `organizations/create`.
- `BelongsToTenant` trait: global scope `organization_id = current`, auto-set on create, immutable
  on update. Applied to `Team`, `Invitation`. `withoutTenantScope()` reserved for platform/admin code.
- Organizations themselves are scoped by membership (a user lists only orgs they belong to).

## API / endpoints (web, session-auth + verified)

- `GET/POST organizations/create` — create first/additional org (no current-org needed).
- `POST organizations/{organization}/switch` — set current org (must be a member).
- `settings/organization` GET/PATCH (`organization.update`); DELETE with typed confirmation
  (`organization.delete`, Owner only).
- `settings/members` GET (`members.view`); `POST members/invitations` (`members.invite`);
  `DELETE members/invitations/{invitation}` revoke; `POST members/invitations/{invitation}/resend`;
  `PATCH members/{user}` role change (`members.update`); `DELETE members/{user}` remove;
  `PATCH members/{user}/deactivate|reactivate`.
- Invitation acceptance: `GET invitations/{token}` (signed) → accept flow; `POST` to accept
  (auth required; if email matches, joins org).
- `settings/teams` GET (`teams.view`); POST/PATCH/DELETE teams (`teams.manage`); add/remove members.
- `settings/audit-log` GET (`audit.view`) — org-scoped viewer with action/actor/date filters.

## Business rules

- Creating an org makes the creator the **Owner** and sets it current.
- An org always has ≥1 Owner: cannot remove/deactivate/downgrade the last Owner.
- Users cannot remove or change their own role in a way that violates the last-Owner rule.
- Invitations: single-use, expire (default 7 days), revocable, resendable; accepting requires the
  invitee to be authenticated with the invited email (else prompted to register/log in).
- Org deletion requires Owner + typing the org name; soft-deletes and clears members' current org.
- `organization_id` is server-set and immutable on tenant-scoped records.

## Audit events (AUDIT-003)

`organization.created/updated/deleted`, `member.invited/invitation_accepted/invitation_revoked/
invitation_resent`, `member.role_changed/removed/deactivated/reactivated`,
`team.created/updated/deleted/member_added/member_removed`. AuditLogger auto-fills
`organization_id` from CurrentOrganization. Viewer (AUDIT-006) is org-scoped + permission-gated.

## Error cases

Non-member accessing an org → 404 (not 403, to avoid existence disclosure). Missing permission → 403. Last-Owner violation → 422 with message. Expired/used/invalid invitation token → explicit
state. Cross-tenant object access → 404.

## Frontend (RBAC-005)

Inertia shares `auth.user`, `auth.currentOrganization`, `auth.organizations`, `auth.permissions`
(list), `auth.role`. `usePermissions().can(permission)` gates UI. Organization switcher in sidebar.
Pages: create-organization, settings/organization, settings/members, settings/teams,
settings/audit-log, invitations/accept. All with empty/loading/error states.

## Tests

- **Tenant isolation (TEN-006)**: member of A cannot read/update/delete B's teams, invitations,
  members, org settings, or audit log — expect 404/403; list endpoints never leak B's rows;
  `BelongsToTenant` scope + immutability unit tests; a dedicated cross-tenant suite.
- **RBAC**: each org permission allowed for roles that should have it, 403 for those that shouldn't;
  Owner bypass; platform Super Admin bypass; frontend permission list correctness.
- **Membership/invitations/teams**: create org → Owner; invite → accept → correct org+role; expire;
  revoke; resend; role change; last-Owner protection; remove; deactivate/reactivate; team CRUD +
  membership.
- **Audit**: tenant events recorded with organization_id + actor; viewer is org-scoped and
  permission-gated. Update Stage 1 audit assertions for the `organization_id` column rename.

## Acceptance criteria (gate)

Per [11-acceptance-criteria](../architecture/11-acceptance-criteria.md) TEN/RBAC row: a user in two
orgs sees the correct data per org; invitation E2E green; every role matrix-tested (allow/deny);
cross-tenant suite green; platform staff bypass works; audit viewer org-scoped. Full quality gate
green; browser QA of create→switch→invite→isolation; Module Completion Report.
