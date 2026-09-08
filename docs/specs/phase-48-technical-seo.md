# Phase 48 — Technical SEO close-out (TSEO-019/023/024/025/026)

**Goal: Technical SEO 22/27 → 27/27 (100%).**

## The stale-deferral insight

All five notes date from before ADR-0005's provider-seam discipline matured. Since then the
platform closed rank tracking, AI search, SMS, link data (P45) and social listening (P46) the
same way: a contract + deterministic fixture (labeled simulated in the UI) + a manager entry,
with the live driver being credentials plus one class. GSC and PageSpeed are exactly that shape
of dependency. Penalty auditing additionally no longer needs external data at all for most of
its signals: toxic-link share (P45 seam + first-party verified links), real ranking-drop history,
thin/duplicate content over published records.

## Register rows

| Row | Close |
| --- | --- |
| TSEO-019 CWV optimization | `CwvLabAuditor` — pure lab analysis of a fetched page (the TechnicalSeoAuditor pattern, SSRF-guarded): render-blocking CSS/JS, lazy hero images, unsized images/iframes, script weight — every check mapped to the metric it moves (LCP/CLS/INP) with a named fix. Field data through a new `WebVitalsProvider` seam (fixture labeled; live PSI = key + one class). |
| TSEO-023 Search Console monitoring | New `SearchConsoleProvider` seam: search performance (queries/clicks/impressions/position), index coverage with issue types, manual actions. Fixture deterministic + labeled; live GSC = OAuth credentials + one class (ADR-0005). |
| TSEO-024 Penalty auditing | `PenaltyAuditService::audit()` — five signals, each citing its numbers: manual actions (seam), toxic backlink share (P45 audit), simultaneous ranking drops from REAL KeywordRanking history, thin published content, duplicate titles/descriptions across published pages. |
| TSEO-025 Penalty recovery | `PenaltyAuditService::recoveryPlan()` — steps derived only from triggered findings, each naming its evidence and the in-platform tool that executes it (disavow export, content editor, Search Console). A clean audit yields an explicit "no recovery needed", never invented work. |
| TSEO-026 Backlink-profile auditing | `BacklinkAuditService::profile()` on the P45 foundation — referring domains, average DA, toxic share, anchor-text distribution (branded / commercial / other), top sources; surfaced on the Links page. |

## Build

- Contracts `SearchConsoleProvider`, `WebVitalsProvider` + fixtures (crc32-seeded; the GSC
  fixture returns a manual action only for hosts containing "penalized" — self-describing).
- `config/seo.php` += `search_console_provider`, `vitals_provider`; `SeoProviderManager::searchConsole()/vitals()`;
  AppServiceProvider bindings.
- `CwvLabAuditor`, `PenaltyAuditService`, `BacklinkAuditService::profile()`.
- `SearchHealthController` + `GET seo/health` (`?cwv_url=` runs the guarded lab+field audit inline).
- `resources/js/pages/seo/health.tsx`, profile card on `seo/links.tsx`, sidebar "Search Health".
- `tests/Feature/Qa/TechnicalSeoCloseoutTest.php` (~5 tests).

## Honesty lines

- Fixture drivers are labeled simulated in the UI; first-party signals (rankings, content,
  verified links) are real regardless of driver.
- The penalty audit reports "ok" rows as ok — findings only when a threshold is actually crossed,
  each citing count and source.
- CWV lab checks are lab checks and say so; field data is the provider's and carries its name.
