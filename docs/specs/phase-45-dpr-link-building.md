# Phase 45 — Digital PR + Link Building closeout

**Register targets:** DPR-003, DPR-004, DPR-009 (Digital PR 76.9% → 100%);
LINK-001, LINK-002, LINK-003 (Link Building 76.9% → 100%).

## Link Building position

The rows "need external link data" — exactly the shape the platform's provider seams
exist for (rank/AI-search/SMS/payments all closed this way, fixture-tested):

- A **`LinkDataProvider`** contract joins the SeoProviderManager (`seo.link_provider`,
  fixture default). The fixture driver returns a deterministic per-domain link set;
  a live driver (Ahrefs / GSC) is credentials + one class. The UI labels fixture data
  as simulated, exactly like the AI-visibility engines.
- **LINK-001 Backlink audit** — provider links for the tenant's own domain (referring
  domains, DA distribution, anchors) PLUS the platform's first-party verified links
  (outreach placements + citations), which are real regardless of driver.
- **LINK-002 Toxic-link identification** — heuristic flags over the provider set
  (spam TLDs, very low DA, exact-match commercial anchors), each flag naming its
  reason, and a **Google-format disavow.txt export** — the actual work product.
- **LINK-003 Competitor backlink analysis** — provider links for each tracked
  competitor's domain vs ours: gap domains (link to them, not us) with one-click
  add-as-outreach-prospect.

## Digital PR position

- **DPR-003 Expert commentary** — pairs the LLMO expert profiles (real credentials,
  `knows_about`) with an outreach prospect: a deterministic pitch draft rendered from
  the expert's actual record, stored on the prospect for the rep to send. New
  `expert_commentary` campaign type.
- **DPR-004 Industry publications** — a curated starter list of real MSP-industry
  publications (CRN, ChannelE2E, MSSP Alert, ChannelPro, MSP Success…) seedable into a
  digital-PR campaign as prospects — the P17 directory-checklist precedent.
- **DPR-009 Research stories** — data journalism from the tenant's OWN aggregates: a
  draft content piece compiling their real numbers (contacts, win rate, review average,
  ad CTR) with an explicit provenance line; nothing invented, nothing external claimed.

## Build

1. Migration: `outreach_prospects.pitch` (text, nullable).
2. LinkDataProvider contract + fixture + manager/config/binding.
3. `BacklinkAuditService` (audit, toxic flags + disavowTxt, competitorGaps).
4. `LinkController` + `seo/links` page + disavow download + gap→prospect endpoint.
5. Outreach: `expert_commentary` type, publication seeding, expert pitch endpoint;
   `ResearchStoryBuilder` + endpoint + buttons.
6. Tests `tests/Feature/Qa/DprLinkCloseoutTest.php` (~5).

## Out of scope (unchanged)

Live Ahrefs/GSC credentials (seam tested with the fixture, like every provider).
