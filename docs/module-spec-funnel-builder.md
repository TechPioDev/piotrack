# Module Specification — Funnel Builder (FUNL, "Module 05")

> Approved 2026-08-28 (first competitive spearhead in the Phase 1 order). Funnel Management sits at
> 17% — 19 of 24 rows Planned — while Jumpfactor's E4 story is exactly this: a structured
> full-funnel machine. We already own every asset the funnel needs (content, forms, landing pages,
> booking pages, ads, retargeting, workflows, campaigns, social, site pages); what's missing is the
> layer that composes them into an executable, measured funnel.

## Purpose

Turn a funnel from a named list of lifecycle stages into an executable map: each stage carries the
**real platform assets** that work that stage, an empty stage is flagged as a gap, and conversion
between stages is measured **cumulatively** from contacts' lifecycle positions so a rate can never
exceed 100% (at-or-beyond denominators — the Phase-1 funnel lesson applied).

## Users & roles

`marketing.funnels.view` to view (existing); `marketing.campaigns.manage` to create/attach/detach
(matches the existing funnel store/destroy gating). Tenant isolation on every asset reference.

## Feature IDs

FUNL-001…011, 013…018, 022 → Tested via typed asset attachment (mapping below). FUNL-019 (ROI
calculation) stays **Planned** — no calculator exists and none is faked. FUNL-020 unchanged.
FUNL-012/021/023/024 already Tested; email/workflow attachment deepens 012.

| Register row | Attachable asset type |
|---|---|
| SEO (001) | `site_page` (typed SEO pages incl. service/location) |
| Blogs/Thought leadership/Guides/E-books/Webinars/Case studies/Video (002,006,007,008,009,010,004,018) | `content` (typed content pieces: article, guide, ebook, webinar, case_study, video…) |
| Social (003) | `social` |
| Awareness ads (005) | `ad_campaign` |
| Retargeting (011) | `retargeting` |
| Email nurturing (012) | `email_campaign`, `workflow` |
| Assessments (013) | `form` |
| Consultation/Pricing/Demo/Meeting (014,015,016,017,022) | `booking_page` |
| Landing pages (supporting) | `landing_page` |

## Database entities

New table `funnel_assets`: id, organization_id, funnel_stage_id (FK, cascade), `asset_type`
(string, allow-listed), `asset_id`, timestamps; unique (funnel_stage_id, asset_type, asset_id).
`FunnelAsset` model uses BelongsToTenant. Postgres-compatible migration.

## API endpoints

- `GET marketing/funnels/{funnel}` (new show, `marketing.funnels.view`): stages ordered with
  live counts, **cumulative conversion** (contacts at-or-beyond stage i+1 ÷ at-or-beyond stage i),
  attached assets (type, id, name, url into its module), `gap` flag per stage, and attachable
  options per type (id+name, capped) for the pickers.
- `POST marketing/funnels/{funnel}/stages/{stage}/assets` (`marketing.campaigns.manage`):
  {asset_type ∈ allow-list, asset_id} — the id must exist **in this tenant** in the type's table
  (per-type validation map); stage must belong to the funnel (404 otherwise).
- `DELETE marketing/funnels/{funnel}/stages/{stage}/assets/{asset}` — same gate; 404 across
  funnels/tenants.
- `FunnelService` gains `detail()` (stages+conversion+assets), `attach()`, `detach()`,
  `attachableOptions()` with a single TYPES map as the source of truth.

## UI

- `funnels/index`: each funnel links to its new **show** page; existing create dialog untouched.
- `funnels/show` (new): funnel bars (Module 01 `BarList`) with counts and stage-to-stage
  conversion labels, TOFU/MOFU/BOFU stage cards listing attached assets (each linking to its
  module) with a **Gap** badge on asset-less stages, attach picker (type select → record select),
  detach per asset. An "MSP Growth Funnel" template button pre-fills the create dialog with the
  standard six stages mapped to lifecycle stages (client-side constant; server store unchanged).

## Testing

Pest `FunnelBuilderTest`: cumulative conversion math (seeded contacts across lifecycle stages; every
rate ≤100%; at-or-beyond denominators), attach/detach across **all ten asset types** (loop, real
rows), cross-tenant asset id rejected, unknown type rejected, stage-not-in-funnel 404, gap flag,
viewer can view but not attach, options scoped to tenant. Vitest: suites stay green.

## Out of scope

ROI calculator (FUNL-019), funnel-level revenue attribution joins, drag-reorder of stages,
per-asset performance metrics on the funnel page (each module already reports its own).
