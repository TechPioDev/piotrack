# Module Completion Report — Stage 2: Tenant Architecture (TEN · RBAC · AUDIT)

Date: 2026-08-13
Spec: [docs/specs/stage-2-tenancy.md](../specs/stage-2-tenancy.md) · ADR: [ADR-0002](../architecture/adr/ADR-0002-rbac-approach.md)
Scope: TEN-001…010, RBAC-001…006, AUDIT-001…006

## Features

| ID        | Feature                                             | Status                                                                                       |
| --------- | --------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| TEN-001   | Organization creation                               | Tested                                                                                       |
| TEN-002   | Organization profile & settings                     | Tested                                                                                       |
| TEN-003   | Organization deletion with safeguards               | Tested                                                                                       |
| TEN-004   | Row-level tenant scoping                            | Tested                                                                                       |
| TEN-005   | Tenant context resolution middleware                | Tested                                                                                       |
| TEN-006   | Automated cross-tenant access tests                 | Tested                                                                                       |
| TEN-007   | User membership within organizations                | Tested                                                                                       |
| TEN-008   | Team invitations (send/accept/expire/revoke/resend) | Tested                                                                                       |
| TEN-009   | Teams / groups                                      | Tested                                                                                       |
| TEN-010   | User deactivation and removal                       | Tested                                                                                       |
| RBAC-001  | Platform roles                                      | Tested (Super Admin bypass; others wired for Stage 13)                                       |
| RBAC-002  | Organization roles                                  | Tested                                                                                       |
| RBAC-003  | Granular permission registry                        | Tested                                                                                       |
| RBAC-004  | Backend authorization enforcement                   | Tested                                                                                       |
| RBAC-005  | Frontend permission-aware visibility                | Tested                                                                                       |
| RBAC-006  | Permission-change audit events                      | Tested                                                                                       |
| AUDIT-001 | Central audit log                                   | Tested                                                                                       |
| AUDIT-002 | Security event coverage                             | Tested                                                                                       |
| AUDIT-003 | Admin event coverage                                | Tested                                                                                       |
| AUDIT-004 | Data event coverage                                 | Partially Implemented (tenancy entities done; CRM/campaign events arrive with those modules) |
| AUDIT-006 | Audit log viewer                                    | Tested (org-scoped; platform-level view in Stage 13)                                         |

AUDIT-005 (billing/subscription events) remains Planned — billing arrives in Stage 3.

## Architecture delivered

- **Tenancy model**: `organizations`, `organization_user` (role + status per membership), `teams`,
  `team_user`, `invitations`; `users.current_organization_id` + `users.platform_role`. A user can
  belong to multiple organizations with a different role in each.
- **Isolation**: `BelongsToTenant` trait (auto-stamped, immutable `organization_id`, global scope);
  `CurrentOrganization` request service; `SetCurrentOrganization` middleware ordered **before**
  route-model binding via the middleware priority list; `EnsureHasOrganization` onboarding guard.
- **RBAC (ADR-0002)**: code-defined `Permission`/`Role`/`RolePermissions`; Gate `before` for
  platform Super Admin bypass; per-permission gates resolved against the current organization;
  `can:` middleware on every tenant endpoint; permission list shared to the frontend for UX gating.
- **Audit**: `audit_logs.tenant_id` renamed to `organization_id` (+FK); `AuditLogger` auto-stamps
  the current organization; 15 tenancy audit actions; org-scoped viewer with filters + pagination.

## Automated test results

- **Pest: 102/102 PASS** (341 assertions). New Stage 2 suites (42 tests): tenant isolation,
  RBAC matrix, organization lifecycle, membership/invitations, teams.
- Pint PASS · PHPStan L6: 0 errors · Prettier PASS · ESLint PASS · tsc PASS.
- Two Stage 1 tests updated (dashboard now requires an organization).

## Manual QA (browser, http://localhost:8734)

- **Onboarding**: an org-less verified user is redirected to "Create your organization"; creating
  one makes them Owner and lands them on the dashboard.
- **Organization settings**: name prefilled and editable (Owner); danger-zone deletion with typed
  confirmation present.
- **Members**: Owner listed with role; invite form creates a pending invitation (email sent).
- **Audit viewer**: shows `organization.created` and `member.invited`, scoped to the org with actor
  and resource.
- **RBAC (Viewer)**: nav reduced to Organization + account items (no Members/Teams/Audit); org page
  shows no Save/Delete controls. Backend confirmed: `/settings/members` → 403, `/settings/audit-log`
  → 403, `/settings/organization` → 200.

## Defects discovered & fixed

1. **Cross-tenant route-model binding leak (critical)** — `SubstituteBindings` ran before the
   appended `SetCurrentOrganization`, so `{team}`/`{invitation}` were resolved with no tenant scope
   active; an owner of tenant A could delete tenant B's team. Caught by the isolation suite. Fixed
   by inserting `SetCurrentOrganization` before `SubstituteBindings` in the middleware priority list;
   regression covered by "blocks route-model binding across tenants" tests.
2. **Deactivated members unmanageable** — the membership guard required _active_ status, so a
   deactivated member returned 404 on reactivate. Added a status-agnostic `isMemberOf()` for member
   management.

## Deferred (tracked, not dropped)

- Platform admin area (platform roles beyond Super Admin, platform-wide audit view, impersonation):
  Stage 13 (ADMIN).
- Data-event audit coverage for CRM/campaign entities (AUDIT-004): lands with those modules.
- Billing/subscription audit events (AUDIT-005) and per-org entitlement-driven limits: Stage 3.
- Ownership transfer UI: allowed via role change today (multiple Owners permitted, last-Owner
  protected); a dedicated transfer flow can follow.

## Completion

**APPROVED — Stage 2 gate passed.** Next: Stage 3 — Commercial Foundation (BILL, ENTL): plans,
checkout, subscriptions, invoices, billing portal, entitlements, usage.
