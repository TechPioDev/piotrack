# Phase 55 — Ops hardening (JOBS-002/003, OBS-004, DEVX-005)

**Goal: close the four rows the P54 verification found code-closeable.** This phase raises
the register without completing a module — JOBS-004 (Horizon), OBS-002 (metrics backend),
BCK-001..003 (server backups + drill) and DEVX-003/004 (staging) stay honestly open on
infrastructure only you can provision.

## Rows

| Row | Close |
| --- | --- |
| JOBS-002 retries/dead-letter | The machinery exists (every queued job declares `tries`, failures land in `failed_jobs`). Pin it: a reflection sweep asserting every `app/Jobs` class declares retries, plus a REAL dead-letter run — a throwing job pushed through the actual database queue worker and asserted into `failed_jobs` with its exception. |
| JOBS-003 idempotency | The harmful-duplicate case is campaign sending: `SendCampaignJob` gains `ShouldBeUnique` (unique per campaign) so duplicate dispatches collapse, and its existing already-sent no-op guard is pinned. Scheduler idempotency was already tested. |
| OBS-004 admin alerting | Real alerting on the platform's own health checks: `HealthCheckService` (extracted from /health, response unchanged) feeds `HealthAlerter` — on a NEW failing state it notifies every platform admin naming the failing checks; repeats are deduped; recovery sends the all-clear. `system:health-alert` runs it every five minutes on the scheduler. |
| DEVX-005 deploy w/ rollback | The row's own condition ("tested after first live exercised release") is now met with evidence: ~20 live production releases through scripts/release.sh, including two REAL automatic rollbacks during the 8 Sep MySQL incident that held health 200. A contract-pinning test locks the script's guarantees (strict mode, snapshot stage, health check, auto-rollback path, sha256 history, rollback/history modes) so a future edit can't silently drop them. |

## Honesty lines

- No module completes this phase; the register notes for the still-open rows keep naming
  their infrastructure dependency.
- The health alerter never invents state: it alerts on transitions, not timers, and the
  recovery notice only follows a real prior alert.
