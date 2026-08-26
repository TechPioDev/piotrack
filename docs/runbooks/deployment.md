# Deployment Runbook — DRAFT (DEVX-005)

> Status: infrastructure not yet provisioned. This documents the intended pipeline so hosting
> decisions slot in without redesign. Update when staging/production exist.

## Environments (Master Prompt §47)

| Env        | Purpose                                | Data                                     |
| ---------- | -------------------------------------- | ---------------------------------------- |
| Local      | Development                            | sqlite / docker-compose Postgres         |
| Staging    | Pre-production validation, smoke tests | Synthetic only — never customer data     |
| Production | Customers                              | PostgreSQL 16 managed instance with PITR |

## Pipeline (extends .github/workflows/ci.yml per §51)

CI gates (already live) → build artifacts → deploy staging → smoke test (`/up`, `/health`,
signup E2E) → manual approval → deploy production → post-deploy smoke test.

## Deploy steps (per release)

1. Tag release; set `APP_VERSION` to tag/SHA (surfaced at `/health`).
2. `php artisan down --render=...` only if a breaking migration requires it (prefer zero-downtime).
3. Run migrations (`php artisan migrate --force`) — backward-compatible migrations required while
   old code may still serve traffic.
4. Cache config/routes/views; restart queue workers (Horizon from Stage 4).
5. Verify `/health` returns `ok` with the new version.

## Rollback

- Application: redeploy previous release artifact (previous tag).
- Migrations: only roll back with an explicit, tested `down()`; otherwise roll forward with a fix.
- Always verify `/health` + smoke tests after rollback and record the incident.

## Backups & recovery (BCK module, Stage 14 hardening)

Managed Postgres with PITR; file storage backups; restore procedure must be rehearsed and
documented here before GA.
