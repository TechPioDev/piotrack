# Module Completion Report — Website Chat / Conversations (CHAT)

**Date:** 2026-08-22
**Module:** Website Chat / Conversations
**Phases delivered:** 1 (core vertical slice), 2 (flow builder), 3 (live human chat), 4 (analytics, targeting, settings)
**Verdict:** All four phases **PASSED**. The module is **COMPLETE**, with two capabilities
honestly recorded as partial (see the closing section).

> Phase 2, 3 and 4 results are in the appendices at the end of this report.

---

## What Phase 1 delivers

A visitor on a tenant's own website can open a chat, be qualified through a branching
conversation, consent to data use, hand over their details, and be turned into a scored,
routed CRM lead with a sales alert and attribution — and the tenant's team can read and
work that conversation in an inbox. That whole chain is live and tested end to end.

This is deliberately a _working slice_, not a visual mock: the widget, the public API, the
qualification engine, CRM capture, scoring, routing, alerting, attribution and the agent
inbox are all real.

## Features implemented (register IDs)

| ID       | Feature                                       | Status                          |
| -------- | --------------------------------------------- | ------------------------------- |
| CHAT-001 | Embeddable website chat widget                | Tested                          |
| CHAT-002 | Multiple widgets per tenant                   | Tested                          |
| CHAT-003 | Widget appearance and branding                | Partially Implemented           |
| CHAT-004 | Floating launcher and welcome teaser          | Tested                          |
| CHAT-005 | One-line installation snippet                 | Tested                          |
| CHAT-006 | Authorized domain restriction                 | Tested                          |
| CHAT-007 | Configurable conversation flow                | Tested _(completed in Phase 2)_ |
| CHAT-008 | Conditional branching by answer               | Tested                          |
| CHAT-009 | Message, choice and input node types          | Tested                          |
| CHAT-012 | MSP and cybersecurity qualification templates | Tested                          |
| CHAT-013 | Existing-customer support routing             | Tested                          |
| CHAT-014 | High-priority security incident routing       | Tested                          |
| CHAT-015 | Contact capture with configurable fields      | Tested _(completed in Phase 2)_ |
| CHAT-016 | Duplicate detection on capture                | Tested                          |
| CHAT-018 | CRM contact and lead creation                 | Tested                          |
| CHAT-019 | Chat lead scoring                             | Tested                          |
| CHAT-020 | Sales routing and assignment                  | Tested                          |
| CHAT-021 | Hot-lead sales alert                          | Tested                          |
| CHAT-022 | Chat source attribution                       | Tested                          |
| CHAT-023 | In-chat appointment booking                   | Partially Implemented           |
| CHAT-024 | Configurable consent gate                     | Tested                          |
| CHAT-025 | Per-tenant privacy and terms links            | Tested                          |
| CHAT-026 | Agent conversation inbox                      | Tested                          |
| CHAT-027 | Conversation transcript view                  | Tested                          |
| CHAT-028 | Conversation status workflow                  | Tested                          |
| CHAT-029 | Agent replies                                 | Implemented                     |
| CHAT-030 | Internal notes                                | Implemented                     |
| CHAT-038 | Chat engagement analytics                     | Partially Implemented           |

Deferred at the end of Phase 1 and correctly recorded **Planned** at the time: visual flow
builder (CHAT-010) and template library (CHAT-011) — both delivered in Phase 2 — plus
progressive profiling (CHAT-017), @mentions (CHAT-031), live
human chat + handoff + availability + business hours (CHAT-032…035), page/behaviour
targeting (CHAT-036/037), funnel + drop-off + A/B analytics (CHAT-039…041), agent
notifications (CHAT-042).

## Automated testing

| Suite             | Result                                                                |
| ----------------- | --------------------------------------------------------------------- |
| Pest (backend)    | **637 passed**, 2478 assertions — 11 new chat tests, zero regressions |
| Vitest (frontend) | **30 passed**                                                         |
| Pint (PHP style)  | passed                                                                |
| PHPStan           | no errors                                                             |
| ESLint            | clean                                                                 |
| TypeScript        | clean                                                                 |
| Prettier          | clean                                                                 |
| Production build  | app + `piotrack-chat.js` (13.6 kB / 4.8 kB gzip)                      |

New tests (`tests/Feature/Chat/WebsiteChatTest.php`) cover: public config whitelisting,
hidden/paused/unentitled widgets, the consent gate and decline path, the full §51
qualification journey, existing-customer routing, duplicate detection, server-side answer
validation, cross-tenant conversation access, the domain allow-list, honeypot handling, and
tenant scoring rules layered on the chat score.

## Manual testing — live, in-browser

Verified by embedding the widget on a **simulated third-party website** (plain serif page,
unrelated CSS) served at a separate path, then driving the conversation as a visitor.

| Check                                                                                                           | Result                                                 |
| --------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------ |
| Widget mounts on a non-Piotrack page                                                                            | **PASS**                                               |
| Shadow-DOM isolation (host CSS does not leak in)                                                                | **PASS** — host Georgia serif did not reach the widget |
| Launcher renders (60px, brand accent, correct position, aria-label)                                             | **PASS**                                               |
| Consent gate shown with message + working Privacy Policy link                                                   | **PASS**                                               |
| Full 12-step §51 journey (Cyber → CMMC → 51-250 → provider → challenge → contact → meeting)                     | **PASS**                                               |
| Conditional branching (Cybersecurity opened the CMMC-specific question)                                         | **PASS**                                               |
| Booking CTA returned with a real booking URL                                                                    | **PASS**                                               |
| Desktop layout                                                                                                  | **PASS**                                               |
| Tablet layout                                                                                                   | **PASS**                                               |
| Mobile layout (375×812) — near-full-screen, no horizontal overflow                                              | **PASS** (368×796)                                     |
| Inbox lists the conversation with avatar, Hot badge, company, status, owner                                     | **PASS**                                               |
| Conversation view: 25-message transcript, score, captured answers, attribution, reply + note boxes, Open in CRM | **PASS**                                               |

### Backend result of the live journey

```
CONTACT: Michael Rodriguez | source=website_chat | score=105 | stage=lead
LEAD:    Precision Manufacturing Group | source=website_chat | owner_id=1 | score=105
CONV:    status=qualified | chatScore=105 | page=.../widget-test.html
MESSAGES: 25   ALERTS: 1   EVENTS: start,lead,qualified,meeting,complete
```

## Gate results

| Area                | Result                                                                                                       |
| ------------------- | ------------------------------------------------------------------------------------------------------------ |
| Desktop             | **PASS**                                                                                                     |
| Tablet              | **PASS**                                                                                                     |
| Mobile              | **PASS**                                                                                                     |
| Tenant isolation    | **PASS** — global scope + cross-tenant token test; public resolution sets tenant from the widget's own org   |
| CRM integration     | **PASS** — Contact + Lead created, deduplicated by email                                                     |
| Lead scoring        | **PASS** — server-authoritative; tenant rules layered on top                                                 |
| Appointment booking | **PARTIAL** — offer + booking link work; in-widget slot picking deferred (no availability engine exists yet) |
| Attribution         | **PASS** — source, page, referrer, UTM captured and stored                                                   |
| Privacy consent     | **PASS** — per-tenant copy/URL, blocks capture until accepted                                                |
| Authorization       | **PASS** — per-route `can:` on all admin routes; RBAC suite green                                            |
| Entitlement         | **PASS** — `entitlement:chat`; public endpoints 404 for unentitled tenants                                   |
| Performance         | **PASS** — 4.8 kB gzip, async, no host-page dependencies                                                     |
| Failure handling    | **PASS** — outage/blocked origin fails silently; host site unaffected                                        |

**UI quality score: 8.5/10** (self-assessed, structural). The widget is a genuine modern
conversational surface — Shadow-DOM isolated, brand-themed, animated launcher/teaser,
typing indicator, chat bubbles, focus management, mobile full-screen. Held below 9 because
the appearance editor is not built yet (theme is configured via API, not a UI), so a tenant
cannot yet self-serve their branding.

## Known defects and limitations

Two defects were found **by this QA pass and fixed** before sign-off:

1. **Consent gate accepted arbitrary input** — any unrecognised option silently declined and
   closed the conversation. Now rejected with a validation error. (Found by automated test.)
2. **Consent message was invisible** — the gate rendered its buttons but never its text, so a
   visitor saw two buttons and no question. Now emitted into the transcript. (Found by live
   browser testing.)

Open limitations, all recorded honestly in the register as Planned/Partial:

- ~~No visual flow builder~~ — delivered in Phase 2 (see appendix).
- No live human chat, handoff, presence or business hours. **Phase 3.**
- No appearance/targeting/consent editor UI — configurable through the update endpoint only.
  **Phase 4.**
- Booking hands off to the existing public booking page rather than picking slots in-chat;
  the product has no availability engine (pre-existing gap, BOOK-003).
- Analytics events are recorded but the funnel/drop-off/A-B **reports** are not built.
  **Phase 4.**
- Progressive profiling and @mentions not implemented.

## Verdict

**Phase 1 PASSED.** The core business capability — anonymous visitor → qualified,
scored, routed, attributed CRM lead with a bookable meeting — works end to end and is
covered by tests. Per the brief's instruction not to declare the module finished early, the
module remains **INCOMPLETE** until Phases 2–4 land.

---

# Appendix — Phase 2: the conversation flow builder

**Date:** 2026-08-22 · **Verdict:** Phase 2 **PASSED**.

## What Phase 2 delivers

Tenants now design their own conversations in a visual builder instead of living with
the seeded default. No JSON is ever edited.

- **Step list + inspector.** Every step is listed with its type, a plain-English summary
  and a start marker; selecting one opens a typed editor for it. Steps can be added,
  duplicated, deleted and re-pointed; deleting a step clears every connection into it so
  the graph never keeps a dead link.
- **Nine step types**, up from four: Message, Question (multiple choice with per-answer
  score and its own onward connection), Collect answer (text/email/phone/number/company,
  optionally skippable), **Condition** (branch on an earlier answer — is / is not /
  contains / has any value / at least / at most), **Add score**, **Tag**, **Assign** (route
  to a named salesperson), and End (lead / lead+meeting / existing-customer outcomes).
- **Live validation.** Every edit is validated server-side. Errors (a step connected to
  nothing, a pointer to a deleted step, a question with no answers, no start step, a step
  with no text) **block publishing and disable the Publish button**; warnings (unreachable
  steps, no reachable ending) are advisory. Each error links to the offending step.
- **Test conversation.** Runs the _unsaved draft_ through the **real engine** visitors hit —
  not a simulation — so what is tested is what ships. Preview conversations are flagged
  `is_preview` and are excluded from the CRM, alerts, analytics and the inbox.
- **Six templates** (§57): MSP lead qualification, Cybersecurity (with a high-priority
  incident route), CMMC readiness, Book a consultation, Existing-customer routing,
  After-hours capture. All are ordinary editable flows.
- **Draft vs publish.** A draft saves at any time; publishing requires a valid graph and
  takes the widget live.

## Automated testing

| Suite                                           | Result                                                                   |
| ----------------------------------------------- | ------------------------------------------------------------------------ |
| Pest (backend)                                  | **650 passed**, 2524 assertions — 13 new builder tests, zero regressions |
| Vitest (frontend)                               | **30 passed**                                                            |
| Pint · PHPStan · ESLint · TypeScript · Prettier | all clean                                                                |

New tests (`tests/Feature/Chat/ChatFlowBuilderTest.php`): valid graph accepted; every
stranding case rejected; unreachable-step warning; **all six templates validate**; draft
saves but broken publish is refused; valid publish activates the widget; template
application; condition branching both ways; score + tag steps; preview creates no CRM
records; previews excluded from the inbox; builder restricted to `chat.widget.manage`;
cross-tenant flow edit blocked.

## Manual testing — live, in-browser

| Check                                                                            | Result                                   |
| -------------------------------------------------------------------------------- | ---------------------------------------- |
| Builder loads the saved flow (19 steps) with toolbar and inspector               | **PASS**                                 |
| Validation banner reflects real state ("ready to publish")                       | **PASS**                                 |
| Test dialog runs the real engine, shows the CRM disclaimer                       | **PASS**                                 |
| Branching inside the preview (Cybersecurity → security-specific question)        | **PASS**                                 |
| Breaking a connection → Publish **disabled**, banner flips, specific error shown | **PASS**                                 |
| Preview isolation verified in the live database                                  | **PASS** — 1 preview, 0 contacts created |

## Defects found by this pass and fixed

1. **Previews silently ran the wrong conversation.** `validate()` strips undeclared keys, so
   `flow.start` was dropped from the request and the engine fell back to the default flow —
   the tenant would have been testing something other than their draft. Now declared in
   every flow endpoint.
2. **NULL status crash.** A freshly created conversation has no `status` in memory (the
   default is applied by the database), so the engine wrote `status = NULL` and the insert
   violated the NOT NULL constraint. The engine now treats unset as new, and both
   controllers set the status explicitly on create. This affected the public path too.

## Still outstanding (recorded Planned in the register)

Progressive profiling, @mentions, live human chat + handoff + agent availability +
business hours (Phase 3), page/behaviour targeting, funnel + per-question drop-off +
A/B analytics reports, agent notification channels, and the widget appearance editor
(theme is still configured through the API, not a UI) — **Phase 4**.

---

# Appendix — Phase 3: live human chat, handoff and availability

**Date:** 2026-08-22 · **Verdict:** Phase 3 **PASSED**.

## What Phase 3 delivers

A visitor who wants a person now gets one — or is told plainly what happens instead.

- **Agent availability** (§25) — Online / Away / Busy / Offline, chosen by the agent from
  the inbox header, with a live count of who else is on. Presence is verified by a
  60-second heartbeat: a browser closed without signing out would otherwise look "online"
  forever, so a stale agent is automatically treated as away and never handed a visitor.
- **Chat modes** (§23) — `bot`, `bot_then_human`, `live`, configured per widget.
- **Handoff** (§24) — a new **Talk to a human** step. If an agent is online _and_ the
  tenant is inside business hours, the conversation is assigned, marked live, and the
  transcript records "_Name_ joined the conversation."; the widget header changes to
  "_Name_ is here to help". If not, the visitor is told when to expect a reply and the
  conversation carries on collecting their details rather than stopping.
- **Load-aware routing** — the already-assigned agent if they are around, otherwise the
  available agent handling the fewest live chats.
- **Business hours** (§33) — per-widget schedule with timezone and a configurable closed
  message. Nothing configured means always open.
- **Two-way live messaging** — once live the bot stands down entirely; the visitor gets a
  free-text composer and the agent replies from the inbox. Delivered by **polling** (4s
  visitor / 5s agent): this stack has no websocket server, and the product runs on
  isolated networks where one could not be reached anyway.
- **@mentions** (§30) — naming a colleague in an internal note emails them a link to the
  conversation. Internal notes are never sent to the visitor.
- **Reopening a chat** (§54) — closing and reopening the widget restores the transcript
  and resumes the live session.

## Automated testing

| Suite                                           | Result                                                                     |
| ----------------------------------------------- | -------------------------------------------------------------------------- |
| Pest (backend)                                  | **663 passed**, 2568 assertions — 13 new live-chat tests, zero regressions |
| Vitest (frontend)                               | **30 passed**                                                              |
| Pint · PHPStan · ESLint · TypeScript · Prettier | all clean                                                                  |
| Widget bundle                                   | 15.4 kB / **5.2 kB gzip**                                                  |

New tests (`tests/Feature/Chat/ChatLiveHandoffTest.php`): stale presence downgraded to
away; agent sets own status (and invalid status rejected); business-hours open/closed/
no-window; handoff connects and records the system line; graceful fallback with nobody
online; bot-only widget never offers a human; closed-hours message; live two-way messaging
with the bot standing down; **internal notes never reach the visitor**; @mention notifies;
a stray email address is not a mention; presence restricted to `chat.inbox.handle`;
cross-tenant polling blocked.

## Manual testing — live, in-browser (two-sided)

Driven as two real participants: an agent in the app, a visitor on a separate
non-Piotrack page.

| Check                                                                  | Result                                                     |
| ---------------------------------------------------------------------- | ---------------------------------------------------------- |
| Agent sets Online; roster shows "1 agent online"                       | **PASS**                                                   |
| Visitor opens widget → connected, "Dana Whitfield is joining you now." | **PASS**                                                   |
| Widget header switches to "Dana Whitfield is here to help"             | **PASS**                                                   |
| Free-text composer replaces scripted buttons                           | **PASS**                                                   |
| Visitor message reaches the inbox                                      | **PASS**                                                   |
| Inbox row shows Assigned + owner + "just now"                          | **PASS**                                                   |
| Transcript shows "… joined the conversation."                          | **PASS**                                                   |
| Agent reply reaches the visitor by polling                             | **PASS**                                                   |
| Live badge in the conversation header                                  | **PASS**                                                   |
| Close + reopen restores the transcript and composer                    | **PASS**                                                   |
| No duplicated messages across poll cycles                              | **PASS**                                                   |
| Graceful fallback when the agent went stale                            | **PASS** — correct "reply within one business day" message |

## Defects found by this pass and fixed

1. **Live chat restarted the flow.** Going live never set a cursor, so the next visitor
   message fell through to the "no cursor" branch and re-ran the conversation from the
   beginning under the agent's feet. Live state is now checked before the cursor.
2. **Duplicated messages.** The widget had no message ids, so its first poll replayed
   lines already on screen. The engine now returns the stored id with every message and
   the widget tracks the highest it has rendered.
3. **Reopening showed an empty chat.** Closing and reopening the widget destroyed the
   transcript and never restored it — §54's "reopened conversation" state was broken. It
   now replays the conversation and resumes polling if still live.
4. **Live badge lagged five seconds.** The conversation header waited for the first poll
   instead of using the state the server already sent; `is_live` is now in the page props.

## Still outstanding — Phase 4 only

Progressive profiling, page/behaviour targeting, funnel + per-question drop-off + A/B
analytics reports, notification channels (Slack/Teams/desktop), and the widget appearance
editor. All recorded **Planned** in the register.

---

# Appendix — Phase 4: analytics, targeting and settings

**Date:** 2026-08-22 · **Verdict:** Phase 4 **PASSED**. Module **COMPLETE**.

## What Phase 4 delivers

- **Chat analytics** (§41) — impressions, opens, conversations, leads, qualified leads,
  meetings, open/engagement/completion/lead rates, and revenue traced from a chat
  conversation through its contact to won deals.
- **Conversion funnel** (§42) — views → opens → conversations → leads → qualified →
  meetings, each rung shown as a share of the one above it.
- **Per-question drop-off** (§43) — an unfinished conversation is parked on exactly the
  question that lost it, so the cursor gives a true abandonment count per step. Sorted
  worst-first, because that is what a tenant should fix next.
- **Widget comparison / A/B foundation** (§44) — widgets sharing an `experiment` key are
  variants; counts and lead rates are reported plainly. **No significance or "winner" is
  claimed**, and no such field is even produced — at typical chat volumes that claim would
  be a lie. The test asserts those keys are absent.
- **Settings editor** — the gap that held the widget's UI score at 8.5: appearance (title,
  company, accent with a **live launcher preview**, position), teaser + delay, chat mode,
  fallback contact, experiment/variant, page + behaviour targeting, business hours per day
  with timezone, consent copy and privacy URL, allowed domains, install snippet with
  WordPress / GTM / Webflow instructions.
- **Page and behaviour targeting** (§34, §35) — include/exclude page rules with wildcards,
  device rules, first-time vs returning visitors, delay, scroll depth and exit intent. These
  decide only _when_ the launcher appears, never what a visitor may do, so evaluating them
  in the browser is appropriate.
- **Progressive profiling** (§17) — a returning visitor is matched on their anonymous id
  and never re-asked for details they already gave; the question is skipped, the value kept.
- **Agent notifications** (§32) — when someone asks for a person and nobody is available,
  the team is emailed and notified in-app, because the visitor has been promised a reply.

## Automated testing

| Suite                                           | Result                                                           |
| ----------------------------------------------- | ---------------------------------------------------------------- |
| Pest (backend)                                  | **678 passed**, 2619 assertions — 15 new tests, zero regressions |
| Vitest (frontend)                               | **30 passed**                                                    |
| Pint · PHPStan · ESLint · TypeScript · Prettier | all clean                                                        |
| Widget bundle                                   | 17.1 kB / **5.9 kB gzip**                                        |

New tests (`tests/Feature/Chat/ChatAnalyticsTest.php`) pin the arithmetic: counts come only
from real events; **rates are zero rather than a divide-by-zero** when nothing has happened;
**builder previews never inflate the funnel**; drop-off identifies the losing question and
orders worst-first; the comparison produces no significance/winner field; another tenant's
widget filter falls back to "all"; a Starter plan is refused; progressive profiling skips
known fields but not for a new visitor; targeting publishes to the widget without leaking
routing/flow/domains; invalid chat mode and accent colour are rejected; settings are
admin-only; and every reported number is tenant-scoped.

## Manual testing — live, in-browser

| Check                                                 | Result                                                                     |
| ----------------------------------------------------- | -------------------------------------------------------------------------- |
| Analytics page renders funnel, drop-off and by-widget | **PASS**                                                                   |
| Funnel percentages computed per rung                  | **PASS**                                                                   |
| Range filters (7d/30d/90d/1y) and per-widget filter   | **PASS**                                                                   |
| Settings page: all six sections render                | **PASS**                                                                   |
| Colour picker updates the launcher preview live       | **PASS** — preview turned `rgb(124,58,237)`                                |
| Enabling a day reveals its open/close time inputs     | **PASS**                                                                   |
| Save persists to the database                         | **PASS** — accent, `include: ["/cybersecurity"]`, `mon: ["09:00","17:00"]` |
| Install snippet + WordPress / GTM / Webflow guidance  | **PASS**                                                                   |

## Defect found by this pass and fixed

**The funnel could report an open rate above 100%.** The widget fired an `open` event every
time the panel was toggled, while `impression` fires once per page load — live data showed
"Chat opens 8 (133.3%)" against 6 views, which makes the whole report untrustworthy. Opens
are now counted once per page load, matching the funnel's meaning of "chats opened".

## Final state — what is complete and what is deliberately partial

**Complete and tested (40 of 42 register rows):** widget + launcher + teaser, install and
domain security, the flow builder with nine step types, validation, templates and a real
preview, qualification, capture with dedupe, scoring, routing, alerts, attribution, consent,
inbox, transcripts, agent replies, internal notes, @mentions, live chat, handoff, presence,
business hours, targeting, progressive profiling, analytics, funnel, drop-off, notifications.

**Honestly partial (2 rows):**

- **CHAT-023 In-chat appointment booking** — the conversation offers a meeting and hands
  over a working booking link, but slots are not picked inside the widget. The product has
  no availability engine at all (pre-existing gap, BOOK-003); building one is a booking-module
  change, not a chat one.
- **CHAT-041 Chat A/B testing** — variants are configurable and compared side by side, but
  no statistical significance is calculated. This is deliberate and matches the brief's
  instruction not to invent significance.

**Module verdict: COMPLETE.** The capability the brief asked for — turning an anonymous
website visitor into a known visitor, qualified lead, CRM contact, sales opportunity,
meeting and attributed revenue, configurable per tenant — works end to end and is covered
by 52 module tests inside a 678-test suite.
