# Phase 22 — MSP Branding close-out: positioning evidence, style guide, site theming

Register target: BRAND-001/002/004/008/009/010/011/012 (positioning analyses),
BRAND-020/021/022 (identity fields → a real deliverable), BRAND-025 (website visual
identity) — 12 rows.

Stay Planned honestly: BRAND-019/023/024/026/027 — logo creation, graphic style,
iconography and social/presentation artwork are creative production; the platform
stores the results, a designer produces them.

## Design

**BrandPositioningService** — the Strategy-module play (P5): compute what the records
evidence, guard what they cannot:

- `discovery()` (BRAND-001) — which discovery questions the brand profile actually
  answers (positioning, USP, differentiators, narrative, tone, ICP rules, palette),
  each unanswered one naming where to answer it.
- `competitorMessaging()` (BRAND-002) — competitors' own public messaging from the
  Phase 14 content snapshots (their page titles ARE their claims), listed per
  competitor; no snapshots → "check content first", never invented.
- `differentiators()` (BRAND-004) — each stated differentiator checked two ways:
  does it appear on OUR published site pages, and does it appear in competitors'
  captured titles (then it is a table stake, not a differentiator). Deterministic
  lowercase matching.
- `icpAlignment()` (BRAND-008) — stated ICP (the Phase 19 scoring rules) against the
  observed win profile (icpProfile): match/mismatch per attribute, guarded when there
  are no wins.
- `positioningEvidence()` (BRAND-009..012) — per axis, what the records show: premium
  (avg won value + median MRR, peer percentile when the benchmark cohort allows),
  vertical/service (published-page coverage of active taxonomy), geographic (branches
  with published location pages).

**Style guide (BRAND-020/021/022)** — the captured palette/typography/imagery become a
real deliverable: a style-guide section on /strategy/brand (palette swatches, type
choices, imagery direction, tagline, logo) plus a native PDF download
(`strategy/brand/style-guide.pdf`, the Phase 21 Pdf writer). Choosing the identity
remains design work; the platform now turns the choices into the guideline document.

**Website visual identity (BRAND-025)** — the public site already themes its accent
from the brand palette; the masthead now carries the brand logo when `logo_url` is
set. The tenant's site visibly runs on their brand profile.

## Tests (tests/Feature/Qa/BrandPositioningTest.php)

1. Discovery checklist honest both directions.
2. Competitor messaging from snapshots; differentiator overlap flagged exactly.
3. ICP alignment match/mismatch + zero-wins guard.
4. Positioning evidence math (coverage, premium numbers).
5. Style-guide PDF carries the palette/typography; public page renders logo + accent.
