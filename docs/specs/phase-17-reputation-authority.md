# Phase 17 — Reputation & Authority close-out

Register target: REP-005/006/007/012/013/014/015/016/017/019 (10 rows). Every one of
these rows' notes already named its close: video testimonials via the review flow,
directory profiles as tracked records, media/PR/quotes/backlinks via outreach → assets,
proof-first landing pages via Marketing.

## Design

**Earned media pipeline (REP-012/013/014/015/017)** — outreach already tracks prospects
to a won placement; the gap was that a placement vanished into the prospect row.
`markPlacement()` now takes a `placement_kind` (article | press | expert_quote |
backlink) and creates the matching `AuthorityAsset` (name, issuer = domain, url,
achieved today) — idempotent on URL. Earned articles, press, expert quotes and
authority backlinks become first-class authority records automatically; media mentions
use the existing `mention` type on the same surface.

**Video testimonials (REP-005)** — `reviews.video_url`: a testimonial can carry its
video link, recorded through the same review flow and shown with the review.

**Directory profiles (REP-006/007)** — `directory_profile` authority assets (issuer =
Clutch, G2, UpCity…, url = the profile) with a `details` json holding tenant-entered
description/services/review count, and a deterministic per-profile optimization
checklist that names each missing piece. Live directory metrics need vendor APIs and
are not faked — the notes say exactly that.

**Thought leadership (REP-016)** — `thought_leadership` asset type (guest podcast,
speaking slot, column) on the same assets surface, fed manually or by the outreach
pipeline.

**Proof-first landing pages (REP-019)** — `ReputationService::createProofPage()`
assembles a draft LandingPage from real records only: 4★+ reviews with text, client
logos, published case studies. With zero proof on file it refuses with "collect proof
first" — a proof page with invented proof would be worse than none.

New asset types widen the existing Rule::in / UI list:
video_testimonial, directory_profile, article, press, expert_quote,
thought_leadership, backlink.

## Tests (tests/Feature/Qa/ReputationAuthorityTest.php)

1. Placement kinds create the right typed asset, idempotently.
2. Video testimonial records and surfaces its video URL.
3. Directory checklist flags missing description/services/reviews, passes complete.
4. Proof page embeds real reviews/logos/case studies; refuses with zero proof.
5. Permission gating + tenant isolation on the new endpoint.
