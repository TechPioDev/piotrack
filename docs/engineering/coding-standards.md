# Coding Standards

Enforced automatically — the CI quality job fails on violations.

## PHP

- **Style**: Laravel Pint, `laravel` preset (`pint.json`). No manual style debates.
- **Static analysis**: PHPStan (Larastan) level 6, paths `app`, `database`, `routes`,
  `bootstrap/app.php`. Fix root causes; no `@phpstan-ignore` or baseline entries without a
  reviewed justification comment.
- **Structure**: Laravel conventions now; business domains move into `app/Domains/<Domain>/`
  starting Stage 2 (tenancy) — controllers stay thin, business logic lives in domain
  services/actions, queries in scoped models.
- Form Requests for validation; Policies for authorization; Events/Jobs for side effects.

## TypeScript / React

- `tsc --noEmit` must pass (strict config from the starter kit).
- ESLint + Prettier (with Tailwind + import-organize plugins) — `npm run lint` / `npm run format`.
- Pages under `resources/js/pages` (Inertia), shared UI in `resources/js/components`; prefer the
  design-system components (DSGN module) over one-off styling.
- Inertia form shapes are declared as `type` aliases (not `interface`) to satisfy `FormDataType`.

## Tests

- Pest for new tests; suites under `tests/Feature` (HTTP/API, DB via `RefreshDatabase`) and
  `tests/Unit`. Platform-level suites live in `tests/Feature/Platform`.
- Every module ships authorization + tenant-isolation tests (from Stage 2 onward).
- Every bug fix adds a regression test (Master Prompt §37).

## General

- No hard-coded URLs, credentials, price IDs, tenant IDs — environment/config only.
- No secrets in the repo, frontend bundles, or logs.
- Database changes only via migrations; keep them Postgres-compatible and reversible where practical.
- Commits: imperative subject, body explains why; reference feature IDs (e.g. `DEVX-004`) when
  a change advances a register row.
