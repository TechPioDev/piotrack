# Phase 37 — LinkedIn Advertising closeout

**Register targets:** LIAD-002, LIAD-013, LIAD-014, LIAD-015, LIAD-016, LIAD-017
(module 64.7% → 100%).

## Honesty position

Stage 8 closed the targeting rows (LIAD-003..012) on the campaign/ad-group `targeting`
facets, and earlier phases shipped the raw materials the open rows need: the LinkedIn
matched-audience email CSV (P16 retargeting export), the LinkedIn company-list CSV
(P26 ABM), and the sponsored-post bridge (P31). Live LinkedIn Marketing API delivery and
form sync stay behind ADR-0006 and are never claimed. Phase 37 ships the missing halves
of the no-API workflow:

- **LIAD-016/017/002 Content promotion, case-study advertising, sponsored content** —
  one-click draft LinkedIn campaign from a content piece: ad group + ad creative built
  from the piece's own title/excerpt/URL, case studies defaulting to a conversions
  objective (BOFU proof) vs awareness for other content. Idempotent per piece. Plus a
  **Campaign Manager brief export** for any LinkedIn campaign: settings, targeting facets,
  linked audiences (with their upload files named), and ad creatives — the sheet a rep
  transcribes into Campaign Manager, since LinkedIn has no bulk-editor import.
- **LIAD-013 Retargeting** — bridge: attach a retargeting audience to a LinkedIn campaign
  (`targeting.audience_id`); the audience's matched-audience CSV (already tested) is the
  upload; the brief names it.
- **LIAD-014 ABM campaigns** — one-click ABM tier campaign: ensures the tier committee
  audience (P26 machinery) and creates the draft LinkedIn campaign targeting it +
  `abm_tier`. Idempotent per tier.
- **LIAD-015 Lead-gen forms** — LinkedIn's Campaign Manager exports lead-gen form leads
  as CSV; Phase 37 ships that import: LinkedIn's export headers mapped, contacts
  created/updated by email with `lead_source` linkedin set-once, form/campaign name
  recorded, malformed rows skipped and counted. Live form sync stays API-gated.

## Build

1. `App\Services\Advertising\LinkedInAdsService`:
   - `promoteContent(ContentPiece): AdCampaign` (idempotent via `targeting->content_piece_id`)
   - `attachAudience(AdCampaign, RetargetingAudience): AdCampaign` (linkedin-only)
   - `abmCampaign(int $tier, AccountService, RetargetingService): AdCampaign` (idempotent)
   - `importLeads(string $path, ?string $campaignName): array{created, updated, skipped}`
   - `brief(AdCampaign): array{filename, rows}` (linkedin-only)
2. `LinkedInAdsController` + routes (ads.* group, `can:ads.campaigns.manage`):
   POST ads/linkedin/promote-content · POST ads/campaigns/{campaign}/audience ·
   POST ads/linkedin/abm · POST ads/linkedin/leads · GET ads/campaigns/{campaign}/brief.
3. UI: campaigns/show.tsx — LinkedIn panel (brief download, audience attach select) for
   linkedin campaigns; campaigns/index.tsx — ABM tier button + leads CSV import dialog;
   content pieces/show.tsx — "Promote on LinkedIn" button.
4. Tests `tests/Feature/Qa/LinkedInAdsCloseoutTest.php` (5): promotion incl. case-study
   objective + idempotency; audience attach + platform guard + cross-tenant refusal;
   ABM tier campaign idempotent with audience built; leads import dedupe/skip counts +
   set-once lead_source; brief content + non-linkedin refusal.

## Out of scope (unchanged)

LinkedIn Marketing API delivery, live lead-gen form sync, live audience push (ADR-0006).
