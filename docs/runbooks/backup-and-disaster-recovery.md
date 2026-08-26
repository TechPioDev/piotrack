# Runbook — Backups & Disaster Recovery (BCK)

**Status: procedures documented and the verification tooling is built and tested. The backup schedule
itself is provider-side and has NOT been exercised from this environment — no production infrastructure
has been provisioned.** Nothing in this document should be read as "backups are known to work here."
They become real when the platform account exists and §Verification below has been run for the first time.

## What runs where

| Concern                                             | Owner                                              | State                                                        |
| --------------------------------------------------- | -------------------------------------------------- | ------------------------------------------------------------ |
| Automated database backups + point-in-time recovery | Managed Postgres (Laravel Cloud)                   | Configured in the platform console — **not provisioned yet** |
| File storage backups + retention                    | Object storage provider versioning/lifecycle       | Not provisioned yet                                          |
| Restore verification                                | `php artisan backup:verify` (this repo)            | **Built and tested**                                         |
| Application-level retention/erasure                 | `privacy:prune-expired-data`, `DataPrivacyService` | **Built and tested**                                         |

The application deliberately does not implement its own database backup loop. A managed Postgres with
PITR is more reliable than an app-scheduled `pg_dump`, and re-implementing it would produce a second,
weaker copy that someone would eventually trust.

## Targets (to be agreed with the owner before go-live)

- **RPO** (max acceptable data loss): 5 minutes — achievable with PITR/WAL on the managed instance.
- **RTO** (max acceptable downtime): 1 hour for a full restore of the primary database.
- **Retention**: 30 days of daily backups + PITR window; file storage versioning 30 days.

These are proposals. They are not met until the infrastructure exists and a restore has been timed.

## Backup configuration (to perform at provisioning)

1. In the Laravel Cloud database settings, enable automated daily backups and confirm the PITR window
   covers the retention target above.
2. Enable object-storage versioning on the files bucket and set a lifecycle rule matching retention.
3. Record where backups live and who can access them — least privilege: the restore role should be
   separate from the application's database role (SEC-007).
4. Store no backup credentials in this repository; they belong in the platform's secret store (SEC-004).

## Restore procedure

1. **Declare the incident** and note the target recovery timestamp.
2. Provision a **new** database instance from the backup/PITR timestamp. Never restore in place over a
   live database — if the restore is wrong you have then lost the original too.
3. Point a maintenance instance of the app at the restored database:
    ```bash
    php artisan backup:verify --connection=restored
    ```
    This checks connectivity, that every critical table exists, that migrations are recorded, and that
    core tables hold data. It exits non-zero if the restore is an empty or partial shell.
4. Run `php artisan migrate --pretend` against the restore to confirm no migrations are outstanding
   (a restore from before a deploy will show pending migrations — apply them deliberately).
5. Spot-check tenant isolation on the restore: pick two organizations and confirm each sees only its own
   records.
6. Cut traffic over, then keep the previous instance untouched until the restore is confirmed healthy.

## Verification (BCK-004) — the part that is easy to skip

A backup that has never been restored is unverified. Schedule a **quarterly restore drill**:

1. Restore the most recent backup into a throwaway instance.
2. Run `php artisan backup:verify --connection=<restored>` — it must exit 0.
3. Record in `docs/qa/` the date, the backup timestamp, the measured restore duration (this is the real
   RTO, not the target) and any deviation.
4. Destroy the throwaway instance.

Until the first drill is recorded, BCK-003 and BCK-004 remain _Partially Implemented_ in the register:
the tooling and procedure exist, the evidence does not.

## What is explicitly not covered yet

- Cross-region replication / regional failover.
- Automated restore drills in CI (a drill needs real infrastructure).
- Backup of third-party state held by providers (Stripe, ad platforms) — those are systems of record in
  their own right and are re-syncable rather than restorable from our backups.
