# Phase 25 — AI Sales Agent close-out

Register target (6 rows): AISA-002 (website AI chatbot), AISA-005/007 (lead/account
research), AISA-012/013 (AI lead/opportunity scoring), AISA-014 (sales-call summaries).

Stays honest: AISA-006 (contact enrichment) — verified firmographic/contact data
requires an enrichment data provider; a model must never fabricate it. Stays Planned.

## Design

**AISA-002 — already shipped, note stale.** The public website AI chatbot exists since
the chat module (§26): the flow engine's `ai` node answers typed visitor questions
through AiGateway (grounded on org name + service lines), turn-capped at 5, degrades to
human capture on any AI failure, spends nothing from builder previews, and ships in
DefaultChatFlow (`ai_ask`). ChatBookingAndAiTest covers it end-to-end over the public
`/wc/{key}` HTTP endpoints. Register close on that evidence; no new code.

**AISA-005/007 — research grounded in the prospect's own public website.** The Partial
reason was "summarizes only the data we already hold". New `ProspectSiteReader` fetches
the company's own site (UrlGuard-gated, in-house crawler discipline — same as
CompetitorContentMonitor): title, meta description, headings, short text excerpt.
`researchLead`/`researchAccount` append a provenance-labeled section ("Observed on
their public website <url>, fetched <now>") to the research profile — genuine external
grounding with no vendor. Unfetchable/absent site → the profile states the summary uses
CRM records only. The prompt discipline (never assert unverified facts) is unchanged.
AISA-006-style verified data (email/phone/firmographics) remains out of scope.

**AISA-012/013 — advisory scores get persistence + outcome calibration.** The Partial
reason was "predictive quality cannot be claimed without validation". New `ai_scores`
table records every advisory score (morph to contact/deal, score, reason);
`scoreLead`/`scoreOpportunity` persist history and STILL never touch the deterministic
lead_score. New `ScoreCalibration::report()` back-tests deals: latest advisory score
per since-closed deal, won vs lost buckets (count, average score), separation — guarded
`insufficient_data` under 3 closed deals per bucket ("calibration against nothing would
be fiction"). Surfaced on the AI dashboard next to the driver-honesty chip, so quality
is only ever the tenant's own measured number, never our claim.

**AISA-014 — real transcripts without the call provider.** The column exists
(`calls.transcript`, provider-only until now); summarizeCall already prefers it. New:
paste/attach a transcript to a call (PATCH, manage-gated, 20k chars) and a summarize
endpoint + calls-page UI. Provider auto-transcripts (CALL-004) stay credential-gated.

## Tests (tests/Feature/Qa/AiSalesAgentCloseoutTest.php)

1. ProspectSiteReader reads title/description/headings/excerpt through the SSRF guard;
   refuses private hosts; returns null on fetch failure.
2. Research profile carries the provenance-labeled site section when fetchable
   (gateway-mock capture), and the CRM-only statement when not.
3. Advisory scores persist AiScore history for contacts and deals and never overwrite
   the deterministic lead_score.
4. Calibration: insufficient_data under 3 per bucket; with seeded scored-then-closed
   deals reports won/lost averages and separation.
5. Transcript attach + summarize endpoints: transcript stored, summary written from it,
   permission-checked.
