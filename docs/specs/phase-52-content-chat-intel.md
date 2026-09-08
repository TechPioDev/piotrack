# Phase 52 — Content Marketing + Website Chat + Buyer Intent + AI Sales Agent + Analytics close-out
(CONT-033/034/035/036, CHAT-041, INTENT-002, AISA-006, ANLY-012)

**Goal: five modules to 100% — Content Marketing 40/40, Website Chat 46/46, Buyer Intent 16/16,
AI Sales Agent 16/16, Analytics Dashboard 36/36.**

## Insights

- **CONT-033..036**: "human execution guided by the score" predates the platform's own tools.
  Refresh and expansion become COMPUTED queues from records (age, thin word count, real
  ranking drops on the piece's target keyword, P44-mined questions no published piece
  answers) — each entry citing its numbers. Conversion/technical copywriting get the P36
  ads.copy treatment: deterministic craft checks (transparent heuristics) plus AI DRAFTS
  through the tested gateway that never auto-publish. The writing stays human; the platform
  contributes measurement and drafts.
- **CHAT-041**: the P23 experiment pattern applied to the chat teaser — a B variant served
  sticky by visitor-key hash, the variant stamped on every conversation it starts, and
  per-variant conversation/lead stats. Conversion copy for chat, A/B-tested for real.
- **INTENT-002**: reverse-IP identification is the P51 enrichment seam's second method —
  `identifyCompany(ip)` (fixture deterministic, private ranges and no-match honestly null;
  live Clearbit Reveal/6sense = the same one class). Needs `visitors.last_ip` (stored under
  the same consent gate as the rest of the pixel). First-party truth (the contact's real
  company) always outranks provider guesses in the UI.
- **AISA-006**: the agent's research profile gains a provider-labeled enrichment block from
  the same seam — beside the site-evidence block, with an explicit "no enrichment data"
  when the provider knows nothing. Never fabricated, exactly as the note demanded.
- **ANLY-012**: map-pack positions are SERP data — `RankProvider::localPack()` joins the
  contract (fixture deterministic; the SerpApi driver parses `local_results` on the SAME
  key the rank driver uses — the P49 AI-Overview move). Analytics gains the map-rankings
  card, provider-labeled.

## Build

- Migration `2026_09_09_140001_add_visitor_ip.php` (`visitors.last_ip`, nullable).
- `PromptRegistry` += `content.copy`; `ContentCraftService` (refreshQueue, expansionQueue,
  craftChecks, draftCopy); ContentPieceController index/show props + draft route; pieces UI.
- Chat: `settings.teaser_b` (validation + settings page), config/start variant hashing,
  widget config call carries the visitor key, per-variant stats in ChatAnalyticsController.
- `EnrichmentProvider::identifyCompany` + fixture; VisitorTracker stores the request IP;
  visitors page shows provider IDs labeled by driver.
- `AiSalesAgent::researchLead` enrichment block.
- `RankProvider::localPack` in contract + both drivers; analytics map-rankings card.
- `tests/Feature/Qa/ContentChatIntelCloseoutTest.php` (~6 tests).

## Honesty lines

- Drafts never publish anything; craft checks are named heuristics.
- Provider company IDs are labeled with the driver and never overwrite first-party truth.
- localPack without a key or without a match returns null — no invented map positions.
