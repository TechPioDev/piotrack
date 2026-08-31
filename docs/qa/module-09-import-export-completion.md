# Module Completion Report — Import/Export Breadth (Module 09)

Date: 2026-08-28 · Coverage tier. No new tables (reuses `import_jobs`).

## What shipped

1. **Imports for companies, leads and deals** on the same pipeline contract contacts have —
   header-alias mapping, per-row validation and dedupe (companies by name, leads by email),
   upload → preview → commit wizard (shared page), ImportJob history with per-row error reports.
   Deals link contacts by email, resolve stage by name in the default pipeline (first stage
   fallback), and store money in minor units like every form. Route `{entity}` is allow-listed;
   `crm.import` gates all of it.
2. **Streamed CSV exports for companies, leads and deals** joining contacts — chunked, and every
   cell through the `Csv` formula-injection guard (pinned by test with an `=SUM(...)` company name).
3. Import/Export buttons on the three index pages, permission-gated.

## Defect caused and fixed during this module

My new test file **overwrote the pre-existing `ImportExportTest.php`** (7 master-QA tests:
contact export columns, formula-trigger defusal, tenant sealing, audit, contact import pipeline).
The suite stayed green while the count silently dropped 803-expected → 796 — the only symptom.
Caught by reconciling counts, restored via git, new tests moved to `EntityImportExportTest.php`.
Recorded in auto-memory: glob before writing any new file; a green suite with a dropped count
means lost coverage.

## Gate

- Pest **803 passed (3,304 assertions)** — the 7 restored originals + `EntityImportExportTest`
  (5 tests, 33 assertions: dedupe + error reports per entity, deal money/link/stage semantics,
  guarded exports, viewer forbidden, unknown entity 404, tenant stamping).
- Vitest 43, Pint, PHPStan, Prettier, ESLint, tsc clean; assets built; no migration.

## Register

IMEX-001 and IMEX-003 stay **Partially Implemented** — honestly: keywords/competitors bulk import
and Excel export remain — with notes now naming exactly what exists and what's left.
Totals unchanged at **740 Tested / 295 Partial / 147 Planned** of 1,191 (substance up, no status inflation).

## Out of scope, unchanged

Keywords/competitors bulk import, Excel exports, PDF reports (IMEX-004), scheduled exports.
