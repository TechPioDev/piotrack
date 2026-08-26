# 02 — User Roles & Authorization Architecture

## Role model

Two scopes, per Master Prompt §5.

### Platform level (internal staff — never tenant users)

| Role                   | Purpose                                                           |
| ---------------------- | ----------------------------------------------------------------- |
| Super Administrator    | Full platform control, security-sensitive operations              |
| Platform Administrator | Tenants, plans, entitlements, feature flags, system health        |
| Support Administrator  | Support tickets, limited tenant visibility, audited impersonation |
| Finance Administrator  | Subscriptions, payments, coupons, invoices, refunds               |
| Read-only Support      | View-only diagnostics                                             |

### Organization level (per tenant)

| Role                       | Typical use                                          |
| -------------------------- | ---------------------------------------------------- |
| Organization Owner         | Billing + everything; created at signup              |
| Organization Administrator | Users, settings, integrations                        |
| Marketing Manager          | Campaigns, content, SEO, automation management       |
| Sales Manager              | Pipeline, lead routing, sales reporting              |
| Sales Representative       | Own leads/deals, activities, meetings                |
| Marketing User             | Execute marketing tasks, no admin rights             |
| Analyst                    | Read + report/export across marketing and sales data |
| Billing Administrator      | Billing portal only                                  |
| Viewer                     | Read-only                                            |

## Authorization engine

- **RBAC with granular permissions.** Roles are bundles of permission strings named
  `domain.resource.action` (e.g. `crm.contact.read`, `crm.contact.delete`, `campaigns.manage`,
  `billing.manage`, `users.invite`, `reports.export`, `integrations.manage`).
- **Permission registry** is code-defined (single source file per domain), synced to DB, and
  drives both role editing UI and policy checks. No permission literals scattered in UI components.
- **Backend enforcement is the security boundary** — policy middleware on every endpoint validates
  (a) authenticated user, (b) tenant context matches the resource, (c) permission grant. Frontend
  visibility gating is UX convenience only (Master Prompt §5).
- **Extensibility:** roles are DB rows (`roles`, `role_permissions`), so custom org roles can be
  enabled later without schema change. Record-level rules (e.g. Sales Rep sees own leads) are
  policy checks layered on top of permission checks.
- **Auditing:** every role/permission change emits an audit event (AUDIT module).

## Impersonation

Support impersonation (ADMIN-006) requires an explicit platform permission, banner indication in
the UI, full audit trail, automatic expiry, and never exposes credentials.

## Testing requirements

Every module ships with authorization tests: allowed role succeeds, forbidden role gets 403,
cross-tenant access gets 404/403, and UI hides what the role cannot do (Master Prompt §33).
