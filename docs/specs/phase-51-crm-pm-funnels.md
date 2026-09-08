# Phase 51 — CRM + Funnel Management + Project Management close-out
(CRM-024/025/027, FUNL-019/020, PROJ-015/016)

**Goal: CRM 30/30, Funnel Management 24/24, Project Management 16/16 — three modules to 100%.**

## Insights

- **CRM-024**: `marketing_owner_id` has been on deals since Stage 6; only the assignment UI is
  missing — the P47 service-line select pattern closes it.
- **CRM-025**: round-robin (least-loaded) routing already ships and is LSCR-019-tested. The
  missing half is the RULE layer in front of it: match lead_source / email domain /
  lifecycle → a named owner, first match wins, round-robin stays the fallback.
- **CRM-027**: "needs a data provider" is the seam shape closed eight times already —
  `EnrichmentProvider` contract + deterministic fixture (labeled simulated), live
  Clearbit/Apollo = credentials + one class. Enrichment fills ONLY empty fields (the
  AdLeadImporter set-once precedent) and audits its provenance.
- **FUNL-019 ROI**: fully first-party — the funnel's own FORM assets capture contacts
  (FormSubmission.contact_id), their won deals are the revenue side, and the funnel's bound
  AD-CAMPAIGN assets carry real recorded AdMetric spend as the cost side. No spend on record
  = no ratio, never infinity (the P41 guard).
- **FUNL-020**: the "full scoring model" the note deferred to shipped in P9 (LSCR, HOT/WARM
  bands). The row closes by surfacing it in funnel context: score bands over the funnel's
  actually-captured contacts.
- **PROJ-015/016**: engagements already schedule/track the meetings; the platform's missing
  contribution is the DATA PACK — a period-bounded review packet PDF generated fresh from
  real records (P42 portal-report precedent): month for a QBR, quarter for a strategy
  review. The meeting stays human-delivered.

## Build

- Migration `2026_09_09_120001_create_assignment_rules.php` (org, position, field, value, user).
- `AssignmentRule` model; `LeadCaptureService::routeToOwner(Contact)` checks rules first;
  rules CRUD on the scoring page (they live beside scoring, where routing already lives).
- Deal marketing-owner select (create dialog + card + show display), validation.
- `app/Crm/Contracts/EnrichmentProvider` + `FixtureEnrichmentProvider` + `config/crm.php`
  + binding + `ContactController::enrich` + button on the contact page.
- `FunnelService::roi()` + score bands on the funnel show page.
- `ReviewPacketService` + `strategy.engagements.packet` PDF route + download links on the
  engagement rows (qbr / strategy_review).
- `tests/Feature/Qa/CrmPmFunnelCloseoutTest.php` (~6 tests).

## Honesty lines

- Enrichment never overwrites operator data (set-once), and fixture output is labeled
  simulated with the driver name recorded in the audit trail.
- Funnel ROI: both sides are records — captured contacts' won deals vs bound campaigns'
  recorded spend; missing cost basis yields an explicit null, not a number.
- The review packet is period-bounded facts with a provenance line; the review itself is
  human consulting, exactly as the register says.
