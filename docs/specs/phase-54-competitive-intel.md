# Phase 54 — Competitive Intelligence close-out (CINT-002/003/004/006/007/009)

**Goal: Competitive Intelligence 13/13 (100%) — the 65th complete module.**

## The insight

All six notes say "provider-gated — not faked". Since they were written, the platform built
the exact seams those providers plug into, and closed forty-plus rows on that standard:

- **CINT-004 backlinks** — `LinkDataProvider` (P45) already serves competitor domains
  (competitorGaps queries it today); the close is the per-competitor summary surface.
- **CINT-006 Maps ranking** — `RankProvider::localPack()` (P52) takes any business name;
  competitors get the same map positions the tenant's own ANLY-012 card uses.
- **CINT-007 reviews** — `ReviewProvider::fetch(source, identifier)` (ADR-0007, P17) already
  fetches by arbitrary identifier; a competitor's name IS an identifier.
- **CINT-009 social performance** — `SocialListeningProvider` (P46) measures mentions per
  network with the transparent sentiment heuristic; competitor names are listening terms.
- **CINT-002/003 PPC/ad monitoring** — one NEW seam, `AdLibraryProvider`: ad-transparency
  data is genuinely public (Meta Ad Library is a real API; Google's Transparency Center is
  served by SerpApi on the key the rank driver already uses) — fixture deterministic and
  labeled simulated; live = token + one class.

## Build

- `app/Analytics/Contracts/AdLibraryProvider.php` + `FixtureAdLibraryProvider` +
  `config/competitive.php` + binding.
- `CompetitorIntelService` (app/Services/Analytics/): per tracked competitor — ads
  (count/active/sample), backlink summary (links, referring domains, avg DA), map positions
  on tracked located keywords, review summary (avg/count/latest), social mentions by network
  with sentiment counts. Every panel carries its driver name.
- CompetitorController index props += `intel`; competitors page gains the intel panels with
  simulated labels on fixture drivers.
- `tests/Feature/Qa/CompetitiveIntelCloseoutTest.php` (~5 tests).

## Honesty lines

- Every number carries its driver; fixtures are labeled simulated in the UI — the module's
  own AIVM/rank standard, never market findings.
- First-party comparisons (head-to-head, share of voice, snapshots) remain the tested
  first-party half; provider panels never overwrite them.
