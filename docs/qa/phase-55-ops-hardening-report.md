# Phase 55 Report — Ops Hardening

**2026-09-09 · Register: JOBS-002, JOBS-003, OBS-004, DEVX-005 → Tested (4 rows).**

**No module completes this phase** — that is deliberate and stated. The remaining rows in
these modules are infrastructure only the operator can provision, and their register notes
keep naming the dependency:

| Module | Now | Still open on |
| --- | --- | --- |
| Background Jobs & Queues | 3/4 (75%) | JOBS-004: Horizon dashboard (Redis + worker install on the server) |
| Observability & Operations | 3/4 (75%) | OBS-002: metrics backend (latency/error-rate dashboards) |
| Engineering Foundation & Delivery | 5/8 (62.5%) | DEVX-003 staging box, DEVX-004 staging smoke gate, DEVX-008 API docs tail |
| Backups & DR | 1/4 (unchanged) | server backup config + one real restore drill |

## What shipped

- **JOBS-002 retries/dead-letter** — pinned, not just present: a reflection sweep fails the
  suite if any `app/Jobs` class ships without a retry budget, and a deliberately failing
  job is pushed through the REAL database queue worker and asserted into `failed_jobs`
  with its exception recorded.
- **JOBS-003 idempotency** — `SendCampaignJob` is now `ShouldBeUnique` per campaign:
  duplicate dispatches collapse to one queued job (pinned via the unique lock), and the
  already-sent no-op guard is pinned so a retry never re-sends.
- **OBS-004 admin alerting** — the checks behind `/health` moved into `HealthCheckService`
  (endpoint response unchanged) so the new `HealthAlerter` and the endpoint can never
  disagree on what healthy means. `system:health-alert` runs every five minutes on the
  scheduler; platform admins are notified on failure **transitions** with the failing
  checks named, identical repeats are deduped, and recovery sends exactly one all-clear.
  Tenant users are never alerted.
- **DEVX-005 deploy with rollback** — the row's own note said "Tested after first live
  exercised release." That condition is now met with overwhelming evidence: roughly
  twenty live production releases have run through `scripts/release.sh`, including two
  REAL automatic rollbacks during the 8 September MySQL incident, both of which held
  `/health` at 200. A contract-pinning test locks the script's guarantees — strict mode,
  the pre-extract snapshot stage, the health gate, the auto-rollback path, sha256'd
  version history, and the `--rollback` / `--history` modes — so a future edit cannot
  silently drop one.

## Gate results

| Check | Result |
| --- | --- |
| `vendor/bin/pint --test` | pass |
| `phpstan analyse` (1G) | 0 errors (verified raw) |
| `npm run format:check` / `npx eslint .` / `npm run types` | pass |
| `npm run build` | pass |
| `npm run test` (Vitest) | 55 passed |
| `php vendor/bin/pest` | **1,084 passed, 5,682 assertions** |

New: `tests/Feature/Qa/OpsHardeningCloseoutTest.php` (4 tests, 54 assertions, first-run
pass) — including the genuine dead-letter round trip through `queue:work` and the
transition-only alerting state machine (alert → dedupe → new-state alert → single
all-clear → silence).

## Register effect

4 rows → Tested. Global: **1,158/1,190 buildable Tested (97.3%)**; modules stay 65/75.
