# Phase 11 — Technical SEO close-out: bounded site crawler

Register target: TSEO-002/003/004/010/013/014/016/017/018/021/022 (11 rows).
Stay Planned honestly: TSEO-019 (Core Web Vitals — needs Chrome UX/PageSpeed field data),
TSEO-023 (Search Console — GSC OAuth), TSEO-024/025 (penalties) and TSEO-026 (backlinks —
external link data).

## Design

`SiteCrawler` (app/Services/Seo/SiteCrawler.php) extends the single-URL auditor's
philosophy — PHP DOM + Laravel Http, no crawler SaaS (ADR-0005) — into a bounded
same-host BFS crawl (default 20 pages):

- Every URL passes the SSRF `UrlGuard` before fetch; redirects are recorded, not
  auto-followed, and only same-host targets are enqueued.
- `robots.txt` is fetched and parsed (User-agent: * Disallow prefixes, Sitemap lines);
  `/sitemap.xml` is fetched and its `<loc>` URLs join the queue so sitemap-only pages
  are discoverable (that is how orphans are found).
- Per page: status, redirect target, depth, title, meta description, canonical,
  noindex, body-text hash, HTML bytes, script/img counts, internal links.

The report is deterministic sections, each item naming its page(s):

| Section | Row | Finds |
| --- | --- | --- |
| indexation | TSEO-003 | noindex pages, cross-URL canonicals |
| robots | TSEO-014 | missing file, blanket disallow, missing Sitemap line |
| crawlability | TSEO-004 | crawled URLs blocked by robots.txt |
| sitemap | TSEO-013 | missing sitemap, crawled pages absent from it |
| links | TSEO-010 | orphans (zero inlinks), dead ends (zero outlinks) |
| duplicates | TSEO-016 | duplicate titles/descriptions, identical content hashes |
| broken | TSEO-017 | internal links to 4xx/5xx/unfetchable targets, with sources |
| redirects | TSEO-018 | redirect map, chains ≥2 hops, loops |
| speed | TSEO-021 | heuristic only (HTML weight, script/img counts) — CWV stays blocked |
| architecture | TSEO-022 | depth histogram, pages deeper than 3 clicks |

Only verified facts are claimed: a sitemap URL the budget never fetched is reported as
"not crawled", never as broken. `issues_count` = flagged items across sections.

Storage: new tenant-scoped `site_crawls` table (start_url, pages_crawled, issues_count,
report json). Surface: crawl form + list on /seo/audits, report at
/seo/audits/crawl/{id}. Permissions: seo.view / seo.audits.manage (existing).

## Tests (tests/Feature/Qa/TechnicalSeoCrawlTest.php)

Http::fake on a public-IP-literal fake site (established pattern) with a redirect chain,
a broken link, a noindex page, duplicate titles, a robots-blocked path and a sitemap
carrying an orphan: (1) BFS same-host bounded crawl; (2) each report section flags its
planted defect and nothing else; (3) HTTP endpoints persist + render, permission-gated,
tenant-isolated; (4) SSRF-guarded start URL refused; (5) page budget respected.
