# ADR-0007 — Content distribution & reputation providers with working fixture drivers

**Status:** Accepted (2026-08-14) · Enables Stage 9 (Content & Authority: CONT, SOC, VID, POD, REP, DPR, LINK) and Master Prompt §16, §38.

## Context

Stage 9 authors content and builds authority. Two capabilities need third parties: **social publishing +
engagement metrics** (LinkedIn/Facebook/X/YouTube APIs) and **review sync** (Google/Clutch/G2). Each
requires OAuth apps and API keys this environment does not have, and Master Prompt §38 forbids
presenting fabricated post reach or reviews as real — we must separate Tested from credential-gated.

Everything else in the stage — the content hub (articles/pages/videos/podcasts) with its editorial
workflow, the content calendar, SEO/CTA/internal-link optimization scoring, review + review-request
management, rating/sentiment aggregation, authority assets, and PR/link outreach pipelines — is
computed from our own data and is built for real.

## Decision

**The content hub, editorial workflow, optimization scoring, reputation records + aggregation, and
outreach pipelines live in our own tables and are fully tested.** Sentiment is derived from the review
rating; the content optimization score is a pure heuristic over the piece's own fields.

**Social publishing/metrics and review sync go through two narrow interfaces — never a vendor SDK:**

- `SocialProvider` → `publish(SocialPost)` (returns external id) and `metrics(SocialPost)`
  (`SocialMetrics`: impressions, likes, comments, shares).
- `ReviewProvider` → `fetch(string $source, string $identifier)` (returns a list of reviews to import).

Drivers are selected by config (`CONTENT_SOCIAL_PROVIDER`, `CONTENT_REVIEW_PROVIDER`):

1. **`fixture` (default, fully working & tested)** — deterministic results derived from a hash of the
   inputs. The whole pipeline — scheduling, status lifecycle, metric snapshots, review import, rating

    - sentiment aggregation — runs for real against it. It is the driver used in dev and tests, and a
      legitimate "manual / bring-your-own-numbers" mode (posts can be marked published by hand; reviews
      entered manually).

2. **`linkedin`/`meta`/`x`/`youtube` (social) and `google`/`clutch` (reviews)** — real drivers over
   the vendor APIs. Real code, but with no credentials here they are **not run in tests**; register
   status _Implemented (untested — requires credentials)_, never "Tested." Activated by config + keys,
   connected through the **INTG** connector framework (OAuth connectors surfaced as "coming soon").

Managers resolve the active driver (mirrors the Payment/Messaging/SEO/Ad managers).

## Consequences

- The authorable, computable half of Stage 9 — content + editorial + calendar + optimization scoring +
  reviews + aggregation + outreach — is real and tested with no social/review account.
- Turning on live publishing + review sync is configuration + credentials + INTG OAuth, not a rewrite (§16).
- We stay honest per §38: content, workflow, scoring, aggregation and fixture-driven social/review flows
  are Tested; live publishing/metrics/review sync is labelled untested until credentials exist.
