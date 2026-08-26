# 08 — Testing Strategy

Testing is compulsory per module — never deferred to the end (Master Prompt §31–§37).

## Layers

| Layer          | Scope                                                                                                                                  | Tooling expectation                   |
| -------------- | -------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------- |
| Unit           | Business rules, validation, calculations, permission logic, services                                                                   | Fast, no I/O                          |
| API            | Every endpoint: success, validation failure, authn failure, authz failure, tenant isolation, 404, duplicate/idempotency, invalid input | HTTP-level tests with seeded fixtures |
| Integration    | DB behavior, queues/jobs, third-party connectors (recorded fixtures), billing webhooks, auth flows                                     | Test DB + fakes/sandboxes             |
| UI             | Components, forms, error states, navigation, permission-gated rendering                                                                | Component test runner                 |
| E2E            | Critical cross-module workflows in a browser                                                                                           | Playwright-class suite                |
| Non-functional | Performance (page load, API latency, large tables, N+1 detection), accessibility, responsive checks                                    | Per module gate                       |

## Required E2E workflows (Master Prompt §34)

1. **SaaS signup**: visitor → plan → register → verify email → payment → tenant created → owner created → dashboard.
2. **Team invitation**: admin invites → user accepts → account in correct tenant with correct role.
3. **Lead workflow**: lead captured → CRM contact → score → sales alert → assignment → meeting →
   opportunity → closed-won → revenue attribution visible.
4. **Subscription upgrade**: owner → billing → higher plan → payment → subscription + entitlements updated.
5. **Failed payment**: webhook → billing status → customer notification → retry/grace → admin visibility.

Grows as modules land (e.g. workflow automation run, AI visibility check, report export).

## Module completion gate (Master Prompt §32)

Plan → DB → backend → frontend → permissions → validation → error handling → automated tests →
manual QA → responsive QA → security check → regression → Feature Register update → **Module
Completion Report** → next module. A blocking failure stops progression.

## Manual QA checklist (every module)

- Visual: alignment, spacing, typography, icons, tables, forms, charts, modals, empty states.
- Interaction: full CRUD, search/filter/sort/pagination, cancel/confirm paths.
- Error paths: invalid/missing/oversized values, duplicates, unauthorized actions, disconnected
  integrations, network failure.
- Responsive: large desktop, desktop, laptop, tablet, mobile.

## Evidence & regression

Module Completion Reports live in `docs/qa/` (feature IDs + PASS/FAIL, automated counts, manual
results, security checks, outstanding defects, verdict). Every bug fix adds a regression test
(Master Prompt §37). CI runs the full suite on every merge; deployment blocks on failure (§50–51).

## No fake implementations (Master Prompt §38)

Register statuses distinguish Mocked / Partially Implemented / Implemented / Tested / Production
Ready. Demo seeds and mocks are clearly separated from production code paths.
