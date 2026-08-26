# Module Specification — Chat v2: Business Conversation & Lead-Conversion System (CHAT2)

> Response to the "AI Business Chat & Lead Management Enhancement" brief
> (2026-08-26). Per that brief's own §13, the existing system was reviewed
> first — and that review is the headline finding: **most of the brief is
> already built and tested.** This spec maps every requested capability to
> what exists, names the genuine gaps, and orders the work.

## Purpose

Turn the shipped Website Chat module (43 register rows, 41 Tested) from a
qualification bot with AI answers into the complete conversation-to-revenue
system the brief describes — without rewriting anything that already works.

## The honest inventory — brief section by section

| Brief section        | Already built & tested                                                                                                                                  | Genuine gap                                                                                                                                                                        |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| §1 Chat experience   | Shadow-DOM widget, teaser, launcher, **typing indicator**, history restore on reopen, transient-failure retry, mobile input types, minimize/reopen      | Suggested questions, timestamps, restart button, message delivery states                                                                                                           |
| §2 AI chat           | `ai` node via AiGateway (grounded prompt, credit caps, 5-turn cap, graceful fallback), qualification, capture, human handoff                            | Upfront "Ask AI / Talk to a human" choice; **AI summary at handoff**; per-bot AI personality                                                                                       |
| §3 Built-in bots     | Template system + 6 templates (MSP, cyber, CMMC, booking, support routing, after-hours)                                                                 | The vertical library (~8 more templates on the same system)                                                                                                                        |
| §4 Lead capture flow | The entire flow: visitor → qualification → capture → dedupe → score → CRM contact+lead → routing → hot-lead alert → attribution. Progressive profiling. | Budget/preferred-contact/preferred-time as standard template steps; natural-language extraction (see risks)                                                                        |
| §5 Lead dashboard    | Chat inbox (statuses, filters, transcript, score, source, answers, assignee) + CRM leads + activities                                                   | AI summary displayed on the conversation; "follow-up required" view; follow-up date field surfaced                                                                                 |
| §6 Handoff           | ChatHandoffService: live takeover, full transcript, internal notes, @mentions, assign, status workflow                                                  | AI-generated summary pinned for the agent at takeover                                                                                                                              |
| §7 Calendar          | Booking pages, in-chat slot picking, double-book prevention, working hours, duration, round-robin                                                       | External calendar sync (Google/Outlook) — **blocked by infrastructure**, see below. Holidays + buffer time on the internal engine. "Collect preferred time" fallback template step |
| §8 Teams             | Teams (Stage 2), `assign` flow node, round-robin least-loaded owner                                                                                     | Rule-based team routing (by service/score) beyond the assign node                                                                                                                  |
| §9 Notifications     | Notification centre + preference matrix; hot-lead, visitor-waiting, mention notifications; queued email                                                 | "Missed chat" digest                                                                                                                                                               |
| §10 Admin config     | Widget settings (appearance/consent/hours/targeting/mode), flow builder, scoring rules, retention policies (privacy module), **AI provider console**    | One "chat setup" landing page linking the pieces; per-widget AI instructions field                                                                                                 |
| §11 Widget           | Modern, branded accent/title/company, teaser, booking, capture, history                                                                                 | Avatar image, suggested-question chips, timestamps, file attachment (see risks)                                                                                                    |
| §12 Simplicity       | The visitor path is already: open → answer → captured → booked/handed off                                                                               | —                                                                                                                                                                                  |

## The flows (deliverable §14)

**Current = improved skeleton.** The brief's target flow already runs live:

```
Visitor → widget (teaser, consent) → qualification (branching, scored)
       → AI Q&A on request (grounded, capped, safe-failing)
       → capture (dedupe, progressive profiling)
       → CRM contact + lead (+score, source, UTM attribution)
       → routing (round-robin / assign node) → hot-lead alert
       → in-chat booking (live slots) OR handoff to live agent
```

**Handoff flow (to change):** today the agent gets the transcript. After P1
they get transcript + a pinned AI summary ("Dana from a 40-seat clinic wants
M365 migration, budget confirmed, asked twice about HIPAA") generated at
takeover via the existing `sales.summarize` prompt.

**Admin flow (to change):** today configuration is spread across Widgets →
Settings, the flow builder, scoring rules and the AI console. After P3 a
"Chat setup" page fronts them with the template picker first.

## Database changes

Small, additive only:

- `chat_conversations.summary` (nullable text) + `summary_requested_at` —
  the AI handoff summary, generated once, shown in inbox and at takeover.
- `chat_widgets.settings` gains keys (no migration): `suggested_questions`
  (list), `avatar` (url/initial), `ai_instructions` (per-widget system
  addendum), `mode` upfront-choice flag.
- Booking availability JSON gains `holidays: []` and `buffer_minutes` —
  read by `ChatBookingSlots`, no schema change.
- New flow templates are code (`ChatFlowTemplates`), not schema.

## API changes

- `GET /wc/{key}/config` → add `suggested_questions`, `avatar`.
- Widget poll/message contracts unchanged (old cached widgets keep working —
  the same constraint the booking/ai nodes already honour).
- Inbox: `POST chat/conversations/{id}/summarize` (permission-gated,
  idempotent, uses AiGateway → fixture-honest until a key).
- No new public surface.

## Security considerations

- AI summaries carry PII → stored on the tenant-scoped conversation row,
  never in logs; generation audited via the existing gateway.
- File attachment from anonymous visitors is **deliberately excluded** from
  scope: unauthenticated upload to a tenant's storage is a malware/abuse
  vector that needs AV scanning and quota design of its own. Revisit as its
  own module gate if genuinely demanded.
- Free-text entity extraction ("collect info naturally") writes to the CRM
  only via the existing validated capture path — the AI proposes, the
  engine validates (email/phone rules), never a direct AI→CRM write. This
  keeps the AIPF-006 discipline: AI output is untrusted input.
- Calendar OAuth tokens (P4) are per-tenant credentials → encrypted at
  rest like integration creds (INTG pattern), never platform-wide.

## Dependencies & blockers (name them before building)

1. **Real AI answers, summaries and personalities are fixture-quality until
   the server can reach an AI provider** — outbound 443 is in the SonicWall
   request. Everything P1–P3 still ships and tests against the fixture per
   the Stage-12 honesty rule.
2. **Google/Outlook calendar sync needs outbound internet + OAuth apps +
   verified redirect URLs on a public HTTPS domain** — i.e. it is behind
   BOTH the firewall change and the public site going live. Scheduled last
   for that reason, with the internal availability engine (already live)
   as the no-integration path the brief asks for.
3. Live email notifications need SMTP credentials (standing item).

## Recommended implementation order

- **P1 — Conversation intelligence (highest value per line of code)**
  AI handoff summary (wire existing summarize into ChatHandoffService +
  inbox display + takeover banner); upfront "Ask AI / Talk to a human /
  Just browsing" entry template step; suggested-question chips in the
  widget fed from config.
- **P2 — The vertical bot library**
  ~8 new templates on the existing system (Sales, Support, Appointment,
  FAQ, Real-estate, Healthcare-appointment, E-commerce, Professional
  services), each with lead questions, scoring, escalation and an FAQ
  section; template picker becomes the first thing a new widget shows.
- **P3 — Widget & inbox polish**
  Timestamps, restart, avatar, delivery tick; follow-up-required inbox
  view; missed-chat notification; holidays + buffer in slot engine;
  "collect preferred time" template step.
- **P4 — External calendar sync (blocked until public + outbound)**
  Google/Microsoft OAuth via the INTG connector framework; availability
  merge; event creation on booking.

Each phase lands with tests, register rows, and a deploy — same discipline
as everything so far. P1–P3 have no external dependencies and can start now.

## Explicitly out of scope (and why)

- Anonymous file upload (abuse surface — own module if demanded).
- Restaurant/food-ordering and education verticals from the brief's list:
  ordering is a commerce workflow, not a chat template; both fall outside
  the MSP-adjacent ICP. The template system accepts them later if wanted.
- Rewriting the widget in a framework: it is 17KB, dependency-free,
  isolated, and now battle-tested — its smallness is a feature.
