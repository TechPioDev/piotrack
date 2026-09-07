# Phase 39 — Vertical Marketing closeout

**Register targets:** VERT-014, VERT-015, VERT-016, VERT-017, VERT-018, VERT-019
(module 70% → 100%).

## Position

The six open rows share one recorded gap: the machinery (content hub, ad campaigns, email
campaigns, workflows, target accounts) is built and tested, and the vertical taxonomy is
the reporting axis — but coverage is counted by **name matching** because no record
carries an explicit vertical foreign key. Phase 39 ships the hard bindings and the
messaging framework, upgrading the coverage report from string matching to real joins.

- **VERT-014 Vertical content** — `content_pieces.vertical_id`, bindable in the editor;
  coverage counts bound-or-title-matched pieces.
- **VERT-015 Vertical ads** — `ad_campaigns.vertical_id`, bindable at creation next to
  the service line; coverage gains an `ads` count.
- **VERT-016 Vertical messaging** — `verticals.messaging` (value proposition, pain
  points, differentiators) editable per vertical on the taxonomy page, alongside the
  existing compliance notes (VERT-020) it complements.
- **VERT-017 Vertical case studies** — case studies are content pieces; the binding plus
  a `case_studies` coverage count (bound, `content_type = case_study`).
- **VERT-018 Vertical email sequences** — `campaigns.vertical_id` + `workflows.vertical_id`,
  bindable at creation; coverage gains a `sequences` count.
- **VERT-019 Vertical-specific ABM** — `target_accounts.vertical_id`, bindable at
  creation; coverage gains an `accounts` count.

## Build

1. Migration `add_vertical_bindings`: nullable `vertical_id` FKs (nullOnDelete) on
   content_pieces, ad_campaigns, campaigns, workflows, target_accounts; `verticals.messaging` json.
2. Model fillables (+ Vertical `messaging` array cast + property).
3. `TaxonomyService::verticalCoverage` — pages/published unchanged; content and
   campaigns become bound-or-name-matched; new hard-bound counts: ads, case_studies,
   sequences (workflows + email campaigns), accounts. Keywords stay name-matched
   (keywords have no vertical axis — stated honestly).
4. `SiteController::updateVertical` (PATCH web/taxonomy/verticals/{vertical},
   `can:web.taxonomy.manage`): messaging fields + compliance_notes.
5. Vertical selects on five create/edit surfaces: content piece form, advertising
   campaign dialog, email campaign form, workflow dialog, target account dialog —
   each controller passing a `verticals` prop.
6. taxonomy.tsx: new coverage columns + per-vertical messaging editor.
7. Tests `tests/Feature/Qa/VerticalMarketingCloseoutTest.php` (4): bindings persist
   through each endpoint; coverage counts from hard joins (name-match fallback still
   working); messaging round-trip + permission; case-study/sequence/account counts.

## Out of scope

Nothing vendor-gated here; the module closes fully.
