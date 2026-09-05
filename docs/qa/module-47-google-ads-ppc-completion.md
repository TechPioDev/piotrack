# Module Completion Report — Google Ads / PPC

**Phase 36 · 2026-09-06 · Register: PPC-001, PPC-002, PPC-010, PPC-013, PPC-014, PPC-016, PPC-017, PPC-018, PPC-020 → Tested (module 25/25, 100%)**

## Scope

The nine open rows split three ways: two stale deferrals (landing pages and call tracking,
both pointing at machinery that shipped in Stages 6/11), five genuinely missing management
capabilities (audit, bids, AI bidding, copywriting, extensions), and the two headline rows
(Google + Microsoft search ads) blocked only on the *delivery* half that ADR-0006 keeps
behind the AdProvider seam.

## What shipped

### Bulk-editor export (PPC-001/002)

`AdExportService::editorCsv(campaign, google|microsoft)` produces one flat editor sheet —
campaign row with daily budget, ad-group rows with max CPC from `bid_amount`, keyword rows
with match types and `Negative Keyword` typing, ad rows (headline/description/final URL),
and extension rows. A tenant can upload this with Google Ads Editor / the Microsoft Ads
bulk tool today, which closes the no-API delivery story honestly; live API sync remains
the tested fixture-provider seam and is claimed nowhere.

### Account audit (PPC-010)

`PpcAuditor::audit()` over stored structure + 30-day metrics: campaigns with no ad groups,
groups with no ads or (on search platforms) no keywords, ads without destination URLs,
missing negatives, missing extensions, sub-1% CTR (≥500 impressions, numbers cited), and
active-with-zero-impressions. Findings render on the campaigns page with severity badges
and deep links. The UI states plainly that auditing a live external account additionally
needs the platform API.

### Bid management + AI-assisted bidding (PPC-013/014)

`BidAdvisor::recommendations()` — rule-based on the campaign's own metrics (low CTR, spend
without conversions, over/under-pacing vs daily budget), every message citing its number,
all behind a 20-click data floor (below it: "insufficient data", not guesses).
`BidAdvisor::aiAdvice()` — on-demand advisory through the governed AiGateway (`ads.bidding`
versioned prompt) fed only the recorded metrics + current bids; refuses below the same
floor **without** calling the model. Both advisory-only; the UI says so.

### Ad copywriting (PPC-016)

`AdCopywriter::draft(group)` — gateway `ads.copy` prompt fed the campaign's service line,
objective, and the group's non-negative keywords; parsed HEADLINE/DESCRIPTION lines are
truncated to the 30/90-char search limits server-side and land as a **draft** ad, never
active, never pushed.

### Extensions & assets (PPC-017)

`ad_extensions` table + CRUD on the campaign page: sitelinks (URL required), callouts,
structured snippets, call extensions (phone required). Included in the editor export and
checked by the audit.

### Bridges (PPC-018/020)

- Landing pages: builder + public `/p/{slug}` shipped in Stage 6; one-click draft landing
  page from a campaign closes the PPC row.
- Call tracking: `call_tracking_numbers.ad_campaign_id` links a number to a campaign
  (source/campaign stamped on link); the campaign page reports total/qualified/converted
  calls through its numbers. Live number provisioning stays telephony-provider-gated.

## Gate results

| Check | Result |
| --- | --- |
| `vendor/bin/pint --test` | pass |
| `phpstan analyse` (1G) | 0 errors |
| `npm run format:check` / `npx eslint .` / `npm run types` | pass |
| `npm run build` | pass |
| `npm run test` (Vitest) | 55 passed |
| `php vendor/bin/pest` | **995 passed, 4,884 assertions** |

New: `tests/Feature/Qa/PpcCloseoutTest.php` (6 tests) — export content in both formats +
unknown-format refusal; audit findings citing numbers with a healthy campaign staying
clean; bid rules with the data floor (gateway never called below it) + AI advisory prompt
capture; copywriter draft with limits enforced against an over-length model reply; call
attribution incl. cross-tenant number refusal; extensions CRUD incl. cross-tenant 404 and
the landing-page bridge.

## Honesty notes

Live Google Ads / Microsoft Ads API delivery, external account import/audit, automated
bid execution, and telephony number provisioning remain provider-gated (ADR-0006), stated
in the register notes and in the UI copy where relevant.

## Register effect

9 rows → Tested. Google Ads / PPC **25/25 (100%)** — 31st complete module. Global:
**1,037/1,190 buildable Tested (87.1%)**.
