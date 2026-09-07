# Module Completion Report — Vertical Marketing

**Phase 39 · 2026-09-06 · Register: VERT-014, VERT-015, VERT-016, VERT-017, VERT-018, VERT-019 → Tested (module 20/20, 100%)**

## Scope

The six open rows shared one recorded gap: the machinery (content hub, ad campaigns, email
campaigns, workflows, target accounts) was built and tested, the vertical taxonomy was the
reporting axis, but coverage was counted by **name matching** because no record carried an
explicit vertical foreign key. Phase 39 shipped the hard bindings and the messaging
framework, upgrading the coverage report from string matching to real joins.

## What shipped

### Hard bindings (VERT-014/015/017/018/019)

One migration adds nullable, tenant-checked `vertical_id` foreign keys to
`content_pieces`, `ad_campaigns`, `campaigns`, `workflows`, and `target_accounts`. Each
is settable from its own surface — the content editor, the advertising campaign dialog
(beside the P29 service-line select), the email campaign form, the workflow dialog, and
the target-account dialog — with a "Vertical (optional)" select fed by the tenant's
active verticals. Cross-tenant vertical ids are refused by validation.

### Coverage report upgrade

`TaxonomyService::verticalCoverage` now joins on the bindings: `content` and `campaigns`
count bound-or-name-matched records (the fallback keeps pre-binding records visible),
and four new hard-joined counts appear on the taxonomy page — **ads**, **case_studies**
(bound pieces with `content_type = case_study`), **sequences** (bound workflows + bound
email campaigns), and **accounts**. Keywords stay name-matched: a keyword phrase has no
vertical axis to bind, stated in the service comment.

### Messaging framework (VERT-016)

`verticals.messaging` (value proposition, pain points, differentiators) is editable per
vertical on the taxonomy page — beside the compliance notes (VERT-020) it complements —
behind `web.taxonomy.manage`, and travels on the coverage report so every page, campaign
and sequence targeting the vertical draws on the same framing.

## Gate results

| Check | Result |
| --- | --- |
| `vendor/bin/pint --test` | pass (after auto-fix) |
| `phpstan analyse` (1G) | 0 errors |
| `npm run format:check` / `npx eslint .` / `npm run types` | pass |
| `npm run build` | pass |
| `npm run test` (Vitest) | 55 passed |
| `php vendor/bin/pest` | **1,007 passed, 5,006 assertions** — the suite crossed 5,000 assertions |

New: `tests/Feature/Qa/VerticalMarketingCloseoutTest.php` (3 tests) — every binding
persisted through its real endpoint plus the cross-tenant refusal; coverage counted from
hard joins on records that do NOT carry the vertical's name (with the name-match fallback
proven separately) and the new columns on the taxonomy page; the messaging round-trip
with the permission gate holding.

## Register effect

6 rows → Tested. Vertical Marketing **20/20 (100%)** — 34th complete module. Global:
**1,055/1,190 buildable Tested (88.7%)**.
