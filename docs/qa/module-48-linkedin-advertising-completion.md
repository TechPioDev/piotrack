# Module Completion Report — LinkedIn Advertising

**Phase 37 · 2026-09-06 · Register: LIAD-002, LIAD-013, LIAD-014, LIAD-015, LIAD-016, LIAD-017 → Tested (module 17/17, 100%)**

## Scope

The six open rows were the workflow halves that connect machinery earlier phases already
shipped: the LinkedIn matched-audience email CSV (P16), the company-list CSV (P26 ABM),
the sponsored-post bridge (P31), and Stage 8's campaign/targeting structure (which closed
LIAD-003..012). Live Marketing API delivery, audience push, and form sync stay behind
ADR-0006 and are claimed nowhere.

## What shipped — `LinkedInAdsService` + `LinkedInAdsController`

### Content promotion & case-study advertising (LIAD-016/017)

`promoteContent(piece)` — a content piece becomes a draft LinkedIn sponsored-content
campaign with creative built from the piece itself (title → headline at the 70-char
sponsored-content limit, excerpt → intro at 150, URL as destination). Idempotent per
piece via `targeting.content_piece_id`. Case studies promote with a **conversions**
objective — they are bottom-funnel proof — and "Case study:" naming; everything else
promotes for awareness. "Promote on LinkedIn" button on the content piece page.

### Retargeting (LIAD-013)

`attachAudience(campaign, audience)` — LinkedIn-only, tenant-checked; stamps
`targeting.audience_id/audience_name`. The audience's matched-audience CSV export
(tested in P16) is the Campaign Manager upload; the UI and the brief both say so.

### ABM campaigns (LIAD-014)

`abmCampaign(tier)` — ensures the tier committee audience through the P26 machinery
(tier list sync + audience rebuild, converted contacts excluded) and creates the draft
LinkedIn campaign targeting it. Idempotent per tier; one-click from the campaigns page.

### Lead-gen forms (LIAD-015)

`importLeads(csv)` — imports Campaign Manager's own lead export format (First Name /
Last Name / Email Address / Job Title / Campaign Name headers mapped, aliases tolerated).
Contacts matched by email; `lead_source = linkedin` and `lifecycle_stage = lead` are
**set-once** — a lead whose first touch was elsewhere keeps its origin, blanks are
filled; malformed rows are skipped and counted. Uploaded through a dialog on the
campaigns page. Live form sync remains API-gated, stated in the dialog copy.

### Sponsored content close (LIAD-002)

The workflow is now complete without the API: post→campaign (P31) and content→campaign
(P37) bridges produce the drafts, and the **Campaign Manager brief** export hands over
setup: campaign settings, every targeting facet from the `targeting` JSON, the attached
matched audience with its upload instruction and member count, and each creative.
LinkedIn has no bulk-editor import, so a transcription brief is the honest handoff —
the brief is LinkedIn-only and refuses other platforms.

## Gate results

| Check | Result |
| --- | --- |
| `vendor/bin/pint --test` | pass |
| `phpstan analyse` (1G) | 0 errors |
| `npm run format:check` / `npx eslint .` / `npm run types` | pass |
| `npm run build` | pass |
| `npm run test` (Vitest) | 55 passed |
| `php vendor/bin/pest` | **1,000 passed, 4,936 assertions** — the suite crossed 1,000 tests |

New: `tests/Feature/Qa/LinkedInAdsCloseoutTest.php` (5 tests) — promotion creative +
idempotency + case-study objective; audience attach with platform guard and cross-tenant
refusal; ABM tier campaign with audience membership and idempotency; leads import
created/updated/skipped counts with set-once source semantics + endpoint wiring; brief
content (settings, facets, audience, creatives) + non-LinkedIn refusal.

## Register effect

6 rows → Tested. LinkedIn Advertising **17/17 (100%)** — 32nd complete module. Global:
**1,043/1,190 buildable Tested (87.6%)**.
