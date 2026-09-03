# Module Completion Report — MSP Branding, Phase 22

**Date:** 3 September 2026 · **Module:** MSP Branding · **Register:** 32/37 Tested (86%), was 20/37 (54%)

## What shipped

**Positioning evidence workbench**
([BrandPositioningService](../../app/Services/Strategy/BrandPositioningService.php), on
/strategy/brand) — the Strategy-module discipline applied to brand: compute what the
records evidence, guard what they cannot:

- **Discovery (BRAND-001)** — which discovery questions the profile actually answers,
  honest in both directions.
- **Competitor messaging (BRAND-002)** — rivals' own claims from the Phase 14 content
  snapshots (their page titles ARE their messaging); nothing captured means "run Check
  content", never invention.
- **Differentiators (BRAND-004)** — each stated differentiator checked against our
  published pages AND competitors' captured titles: one a rival also claims is flagged
  *a table stake, not a differentiator* (test-pinned both ways).
- **ICP alignment (BRAND-008)** — the stated ICP versus the observed win profile per
  attribute, guarded when there are no wins ("alignment against nothing would be
  fiction").
- **Positioning evidence (BRAND-009..012)** — premium as numbers (avg won value, median
  MRR), vertical/service/geographic as published-page coverage of the active taxonomy.

**Style guide deliverable (BRAND-020/021/022)** — the captured palette, typography,
imagery direction, tagline and tone render as a native PDF
(`strategy/brand/style-guide.pdf`, the Phase 21 writer) — the platform turns identity
decisions into the guideline document; choosing them remains design work.

**Website visual identity (BRAND-025)** — the public site wears the brand: the palette
primary already drove the site accent, and the masthead now renders the brand logo when
one is set (verified in the rendered public page).

The suite's menu smoke test caught a real 500 during the gate — the style guide crashed
for a tenant with no brand profile yet — fixed to render "(not captured yet)" lines.

## Honest scoping — 5 rows stay Planned

**BRAND-019/023/024/026/027** (logo creation, graphic style, iconography, social and
presentation artwork) are creative production: a designer produces them, the platform
stores the results.

## Gate evidence

- Pest: **922 passed / 4,202 assertions** (+5:
  [BrandPositioningTest](../../tests/Feature/Qa/BrandPositioningTest.php)).
- Pint clean · PHPStan 0 (BrandProfile array casts now properly typed) ·
  Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
