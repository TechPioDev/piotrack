# Module Completion Report — Funnel Builder (Module 05)

Date: 2026-08-28 · Spec: `docs/module-spec-funnel-builder.md` · First competitive spearhead:
Funnel Management was 17% (19 of 24 rows Planned) against Jumpfactor's core E4 story.

## What shipped

1. **Funnel show page** — the funnel as an executable map: contacts-by-stage bars with
   **cumulative conversion** between stages (at-or-beyond denominators, so a rate can never exceed
   100% — the Phase-1 funnel lesson, now pinned by test with data that would read 300% naively),
   stage cards in TOF/MOFU/BOF/post colors, and a **gap warning** on every stage that has nothing
   working it.
2. **Asset attachment** — ten allow-listed types connect real platform records to stages:
   content pieces, social posts, ad campaigns, retargeting audiences, email campaigns, workflows,
   landing pages, forms, booking pages, site pages. One `FunnelService::TYPES` map is the single
   source for validation, picker options (tenant-scoped, capped) and links into each module.
   New `funnel_assets` table (unique per stage+type+id, cascade, tenant-stamped).
3. **Guardrails** — unknown type → validation error; another tenant's asset id → validation error
   (the type's own tenant-scoped model simply cannot see it); a stage not in the funnel → 404;
   read-only roles see the map but cannot compose; deleted assets vanish from the map rather than
   rendering broken.
4. **MSP Growth Funnel template** — one click pre-fills the create dialog with the standard six
   stages mapped to lifecycle stages; funnels on the index now link to their map.

## Gate

- Pest **775 passed (3,162 assertions)** — `FunnelBuilderTest` (7 tests, 49 assertions) including
  a loop attaching **all ten types** with real rows.
- Vitest **43**, Pint, PHPStan, Prettier, ESLint, tsc clean; assets rebuilt; migration run.
- Live verification on the seeded org: template funnel created through the real endpoint; map
  renders Interest 11 → Evaluation 2 → Sales-ready 5 with conversions 100/42.1/75/16.7/100 (all
  ≤100%); six gap warnings; live attach of a real content piece flipped the Interest gap off.

## Register

FUNL-001…011, 013…018, 022 (18 rows) → **Tested** via typed attachment; FUNL-019 (ROI calculator)
honestly stays **Planned** — none exists and none was faked; FUNL-012 note extended.
**Funnel Management: 17% → 88%.** Totals: **727 Tested / 304 Partial / 151 Planned** of 1,191.

## Out of scope, unchanged

ROI calculator, drag-reorder, per-asset performance metrics on the map, funnel-level revenue joins.
