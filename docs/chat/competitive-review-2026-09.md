# Website Chat — competitive review, September 2026

What our chat does today, what the products owners compare us with do, and what is worth
building next. Sources are the vendors' own product, help-centre and pricing pages, read in
September 2026; where a vendor's own pages contradicted each other, that is said rather than
smoothed over. Our side is read from the code, not from the register.

Related: `docs/module-spec-website-chat.md`, `docs/specs/chat-builder-v2.md`,
`docs/qa/module-completion-website-chat.md`, register rows `CHAT-001..063`.

## 1. What we have (from the code, 2026-09-23)

**Builder.** A tree canvas computed from the saved graph: drag a step onto a line, or pick it
and click where it goes; "+" on every line; inline editing of text and replies on the cards;
undo/redo 50 deep; zoom, fit, mini map, fold a question's paths, focus mode, hideable panels;
per-step problems on the card and a publish gate; a test dialog that runs the draft through the
real engine; 16 templates in 6 business types; 18 blocks in 5 groups.

**Steps the engine runs (11).** message · choice (quick replies, per-answer score and priority) ·
input (text, email, phone, number, company — validated server-side, required/optional,
progressive profiling skips what is already known) · condition (6 operators) · score · tag ·
assign · handoff (bot / bot-then-human / live, business hours, agent presence) · booking (real
availability, books through the booking module) · ai (answers typed questions, capped at 5 turns,
falls back to capture) · end (lead / meeting / support ticket).

**Widget.** Shadow DOM, one script tag, accent colour, logo, position, teaser with A/B variant,
page/device/first-time/scroll/exit-intent rules including query strings, consent gate, picture
attachments with browser-side compression and an in-page viewer, transcript restored on reopen,
5-second polling, mobile full-screen, reduced motion, ARIA roles throughout, white-label on
Agency/Enterprise.

**Team side.** Inbox with filters, transcript, replies, internal notes with @mentions, assignment,
presence (online/away/busy/offline, round-robin by load), AI takeover summary, attachments,
"emailed" markers, support-ticket link, CRM link, captured answers and attribution.

**After the chat.** Contact + Lead deduped on email, lead scoring (chat score vs rule engine),
alerts, round-robin owner, support tickets carrying the whole transcript, meeting booking, and
chat analytics: funnel, drop-off by question, per-widget, teaser A/B, and revenue attributed from
won deals.

## 2. The field

| | What it is | Why it matters to us |
| --- | --- | --- |
| **Crisp** | Flat-price inbox + Workflows builder + Hugo AI agent | Closest comparison for the builder itself |
| **Tidio / Lyro** | SMB live chat + Flows + Lyro AI agent | Closest comparison on packaging and AI pricing |
| **Landbot** | AI-agent + live-chat platform with a flow builder | The closest functional analogue overall |
| **Typebot** | Pure flow builder, bring-your-own LLM, no human layer | The best craft reference for the canvas |
| **Tawk.to** | Free live chat, paid AI and white-label add-ons | The price floor; no flow builder at all |
| **ManyChat** | Instagram/WhatsApp/TikTok DM automation | **No website chat**; only editor ideas transfer |
| **HubSpot** | CRM with free live chat and a rule-based chatbot | What a buyer coming from a CRM expects |
| **Intercom / Fin** | Messenger + Fin AI agent — **bought by Salesforce, 10 Sep 2026** | The deepest reporting and AI pricing model |
| **Zendesk** | Support suite, AI agents, mid-migration to a new bot builder | Where support-led buyers come from |
| **LiveChat / Text** | SMB live chat, merged with ChatBot.com | The nearest thing to honest SMB pricing |
| **Drift** | **Switched off 31 January 2027** | Its customers must replace their widget |
| **Qualified** | ABM chat on Salesforce, ~$40k+/yr | Out of reach for our buyers, and US-only data |

## 3. Steps you can put in a flow

| Step | Us | Crisp | Tidio | Landbot | Typebot |
| --- | --- | --- | --- | --- | --- |
| Message | ✅ | ✅ | ✅ | ✅ (several bubbles per block) | ✅ |
| Quick replies / buttons | ✅ | ✅ | ✅ (3 kinds) | ✅ | ✅ |
| Question with validation | ✅ email/phone/number/text | ⚠️ undocumented | ✅ success/failure paths | ✅ regex, min/max, required | ✅ + one global error handler |
| Multi-question form | ❌ | ❌ | ✅ | ✅ | ❌ |
| Save to a variable | ⚠️ answers only | ✅ | ✅ | ✅ typed | ✅ |
| Merge fields in text ("Hi {{name}}") | ✅ *(new)* | ✅ | ✅ | ✅ | ✅ |
| Condition | ✅ 6 operators | ✅ 26 kinds | ✅ 13 kinds | ✅ | ✅ 12 operators + regex |
| Calculation / formula | ❌ | ❌ | ⚠️ limited | ✅ live preview editor | ✅ inline JS |
| Delay / typing | ✅ *(new)* | ✅ | ✅ | ✅ | ✅ |
| Image / video / carousel | ❌ | ✅ | ✅ cards | ✅ | ✅ |
| Ask for a file | ❌ (visitor can attach) | ❌ | ✅ | ✅ | ✅ |
| Rating / CSAT | ✅ *(new)* | ✅ | ⚠️ inbox only | ✅ | ✅ |
| Webhook / API call | ❌ | ✅ + branch on response | ✅ | ✅ | ✅ + pause-until-callback |
| Jump / go to | ✅ (go-to in settings) | ⚠️ workflow only | ✅ | ✅ | ✅ |
| Reusable sub-flow | ❌ | ✅ | ✅ | ✅ Bricks, shared library | ✅ link + return |
| A/B split inside a flow | ⚠️ teaser only | ❌ | ⚠️ random | ✅ | ✅ |
| Human handoff | ✅ hours + presence | ✅ | ✅ (ends the flow) | ✅ both ways | ❌ |
| Book a meeting | ✅ real availability | ❌ | ❌ | ⚠️ Calendly | ⚠️ Cal.com |
| Lead score | ✅ | ❌ | ⚠️ pattern | ✅ block (paid tiers) | ❌ |
| Support ticket | ✅ | ⚠️ inbox state | ⚠️ tickets | ❌ | ❌ |
| Payment | ❌ | ❌ | ❌ | ✅ Stripe | ✅ |
| Send an email | ❌ | ❌ | ✅ | ✅ | ✅ |

**Read:** we are mid-table on breadth and we own the bottom-of-funnel steps nobody else has in
one product (booking on real availability, lead score, support ticket, CRM contact). The gaps
that show in a demo are: **merge fields, delay/typing, media, rating, webhook, ask-for-a-file**.

## 4. AI

| | Grounded in your content | Flow written by AI | Agent-side AI | Priced |
| --- | --- | --- | --- | --- |
| **Us** | ❌ company name + service list only | ❌ | ✅ takeover summary | in plan credits |
| Crisp (Hugo) | ✅ crawl, KB, files, Q&A | ❌ | ✅ Copilot | ~$0.05–0.10 per conversation, no rollover |
| Tidio (Lyro) | ✅ crawl (60 pages), PDF/CSV, harvested from solved chats | ❌ | ✅ Copilot on every plan | ~$0.70 per conversation |
| Landbot | ✅ 200k chars, PDF/DOCX, auto-refreshing URLs | ✅ prompt → draft flow | ❌ | €0.10 per AI chat (2× a normal chat) |
| Typebot | ❌ bring your own | ❌ | ❌ | your own API key |
| Tawk.to | ✅ FAQ, help centre, site, files | ❌ | ✅ smart reply | $29 / 1,000 messages |

**Read:** this is our weakest area against the market. Every paid competitor grounds its AI in
the customer's own content; ours answers from the company name and a list of service lines. We
already have a knowledge base in the product (`KbArticle`, used by the support desk), so the
plumbing exists. Landbot is also the only one that writes a flow from a prompt — a strong demo
feature we could match with our own AI gateway.

## 5. Team side and channels

- **Saved replies:** everyone has them (Crisp, Tidio macros, Landbot shortcuts, Tawk.to
  shortcuts). ✅ shipped 2026-09-23.
- **Rating at the end of a chat:** Crisp, Tidio, Landbot, Typebot. ✅ shipped 2026-09-23.
- **Mobile app for agents:** Crisp, Tidio, Tawk.to, ManyChat, Landbot (unclear). We have a
  responsive web inbox only.
- **Live typing preview** (seeing what the visitor types before they send): Crisp, Tidio. Not us.
- **Channels beyond the website:** everyone except Typebot sells WhatsApp/Messenger/Instagram;
  Tidio and Tawk.to include them on free plans. We are website-only.
- **Reassignment in the inbox:** our API supports it, the screen has no control for it.
- **Transcript export:** Tidio and Typebot export CSV. We do not.

## 5b. The MSP market specifically

Most of the chat our buyers actually meet is not Intercom — it is an agency stack:

- **Jumpfactor's own site loads GoHighLevel** (`api.leadconnectorhq.com`), so their "Nexus CRM" is
  almost certainly white-labelled HighLevel. HighLevel gives an MSP a widget in three modes
  (capture / live chat / AI), a Conversation AI in off / suggest / auto-pilot, SMS, email,
  Facebook, Instagram, WhatsApp and Google Business, appointment booking, at
  $50–$97 per month per location.
- **MSP Process** is the closest true competitor: AI plus human agents, 2–4 routing questions
  splitting sales from support, and **a round trip into the PSA in under two seconds —
  ConnectWise, Datto, HaloPSA, Autotask, Syncro** — plus paging to Teams, Slack, SMS and phone,
  and iOS/Android apps. No public pricing.
- **MSP Sites** ($199–$399/month) puts the live booking agent, visitor identification and
  PSA/RMM integration in its top tier only.
- **ProntoChat** ($20 per qualified exclusive lead) and **Blazeo/ApexChat** ($18–35 per lead)
  sell managed human agents, white-labelled for agencies.

**What that means for us.** Our competitors in this vertical are not judged on node palettes.
They are judged on: does the chat reach the PSA, does a human answer out of hours, and does the
owner get paged on their phone. We have the conversation and the CRM; we do not have the PSA, a
phone app, or paging beyond email.

The canonical MSP qualification script these vendors use — business type → staff count → biggest
IT challenge → current provider → timeline — is exactly what our `msp_qualification` template
already asks, so the conversation itself is competitive.

## 6. Where we are ahead

1. **Editor craft.** None of Crisp, Tidio or Landbot documents zoom, fit, mini map, path folding
   or undo/redo together; we have all of them, plus pick-and-place, inline card editing and a
   focus mode. Typebot is the only comparable canvas, and it has no human layer at all.
2. **Templates by business type.** 16, each validated, each asking for a required email, each
   runnable end to end. Crisp has no browsable gallery; Tawk.to has none; Tidio and Landbot group
   by goal rather than industry.
3. **The whole funnel in one product.** Chat → lead scored → CRM contact → owner → meeting booked
   on real availability → support ticket → revenue attributed from won deals. The chat vendors
   stop at "send it to your CRM"; the CRM vendors charge separately for chat.
4. **Drop-off by question** on the analytics page, which tells an owner which question loses
   people. Only Typebot (Pro) and Landbot (goals) do anything comparable.
5. **Publishing safety.** A broken draft cannot be published, and on a live widget it cannot even
   be saved. Typebot's own docs admit unconnected blocks silently end conversations.

## 7. Gaps worth closing, in order

**Done on 2026-09-23** (CHAT-064..069): both defects below, merge fields, the pause before a
message, the rating and saved replies. The rest of the list stands.

**Tier 0 — defects, not gaps**

1. **A chat lead never leaves the product.** `LeadCaptureService` and `BookingService` fire the
   `lead.captured` / `booking.created` webhooks that customers wire to Zapier, n8n or a PSA;
   `ChatCaptureService` fires none. So a chat lead cannot reach ConnectWise, Autotask or HaloPSA
   by any route, while a form lead can. One dispatch call.
2. **The Add Tag step writes tags nothing reads.** `answers._tags` is never read by the CRM, the
   inbox or analytics. Either carry them onto the contact or drop the step.

**Tier 1 — visible in the first five minutes of a demo**

3. **Merge fields in step text** (`Hi {{first name}}`). Everyone has it; we do not. Small.
4. **Delay and typing between messages.** Every competitor; makes a bot feel human. Small.
5. **AI grounded in the customer's own content.** Point the AI step at the knowledge base
   (`KbArticle` already exists) and the site. Medium, and the single biggest competitive gap.
6. **Saved replies in the inbox.** Table stakes for a team answering chats. Small.
7. **Rating at the end of a chat**, and the score on the analytics page. Small.
8. **Multi-select and copy/paste on the canvas.** Crisp, Tidio and ManyChat all have it; our
   canvas has neither. Medium.

**Tier 2 — asked for in evaluations**

9. **Webhook / API step**, ideally with branch-on-response like Crisp. Medium.
10. **Image and video in a message**, and a carousel of cards. Medium.
11. **Ask for a file** as a step (we accept pictures, we cannot request a document). Small.
12. **Reassign from the inbox screen**; transcript export. Small.
13. **Paging when a visitor is waiting** — Teams, Slack or SMS, not only email. The MSP-specific
    vendors all sell this. Medium.

**Tier 3 — bigger bets**

14. **PSA connectors** (ConnectWise, Autotask, HaloPSA) so a chat ticket lands where an MSP
    actually works. This is the one thing every MSP-specific chat vendor sells and we do not.
15. **AI writes the first draft of a flow** from a description (Landbot's "Build it for me").
16. **Reusable sub-flows** (Landbot Bricks / Typebot link-and-return) with a shared library —
    strong for agencies running many widgets.
17. **Version history** with restore. Landbot has it; Typebot only has restore-published.
18. **WhatsApp** as a second channel, using the same flow engine.
19. **Multi-language** widget copy. We store `settings.language` and never read it.

## 8. Interaction ideas worth copying

- **Typebot's Invalid Reply Event** — one handler for every failed answer, instead of a retry
  message per question.
- **Landbot's AI interactive components** — the AI step renders real buttons and typed inputs
  mid-answer and saves the result to a field.
- **Landbot's formula editor** — live result preview beside a browsable function list.
- **Mouse vs trackpad navigation mode** (both Landbot and Typebot ship it).
- **Several bubbles inside one card** (Landbot) — keeps a long conversation's canvas small.
- **Drop a step onto a connector** (Crisp's splicing, Landbot's goals-on-an-edge). We already do
  this for gaps; extending it to the lines inside branches would match them.
- **Export/import a flow as JSON** (Crisp, Typebot) — portability and support.
- **Export the canvas as a picture** (Landbot) — sending a flow to a client for approval is an
  agency-shaped job we should expect.
- **Consistent green/red outputs** on every branching step (Landbot).

## 8b. What the enterprise platforms treat as table stakes in 2026

From the seven above, the things every serious vendor now ships — a new entrant is measured
against these:

1. **An AI agent grounded in the customer's own content**, with a website crawler, file upload,
   scheduled re-sync and answers that cite their source. HubSpot crawls 5,000 URLs per domain;
   Qualified 5,000 pages; ChatBot.com 5,000 sources. **We do not crawl anything.**
2. **Outcome-based AI pricing with a published definition of "resolved"** — $0.50 (HubSpot) to
   $2.00 (Salesforce) per resolution, and a published list of what you are *not* billed for.
   Intercom refunds a resolution if the customer comes back, and guarantees up to $1M.
3. **Draft / publish / version history with rollback on the flow itself.** We have draft and
   publish; we have no history. HubSpot is criticised for exactly this.
4. **Testing beyond a preview pane** — multi-turn simulations, batch tests with traces, a
   confidence-scored answer scorecard fed by real unanswered questions.
5. **Natural-language authoring** of agent behaviour with explicit must / must-not guardrails.
6. **An API/webhook step that isn't a separate product** (HubSpot makes you buy Data Hub for it).
7. **Omnichannel in one inbox** — WhatsApp, Messenger, Instagram, SMS, email.
8. **Routing a sales team recognises** — round robin, load balanced, skills, capacity, hours.
   We have round-robin by load and presence; skills-based routing we do not.
9. **Analytics that reach revenue**, not just volume. We already do this, and better than
   HubSpot's chatbot report, which gives only started / completed / abandoned.
10. **Published accessibility and AI disclosure.** A WCAG 2.2 AA claim with a VPAT for the widget
    (LiveChat has one; Zendesk publishes per-surface reports; HubSpot, Intercom, Qualified and
    Salesforce publish nothing), and an **AI-disclosure toggle for EU AI Act Article 50**, in
    force since 2 August 2026. Our widget is built to WCAG practice but claims nothing, and has
    no AI-disclosure setting. Both are cheap to close and both are now legal exposure in the EU.

**Two openings worth naming.** Every Drift customer must replace their widget before 31 January
2027 and is being referred to enterprise-priced successors — nobody is serving the small ones.
And Intercom's 30,000-plus mostly-small customers have just been bought by Salesforce.

## 9. Things the research could not settle

Crisp's per-plan channel gating and whether its editor has zoom/undo; Tidio's exact template
count and the $0.50-vs-$0.70 AI rate; Tawk.to's add-on line-up (three of its own pages
disagree); whether Landbot has a mobile agent app. None of these change the conclusions above.

**Licence note:** Typebot moved from AGPL to the Functional Source License, which forbids
embedding its editor in competing commercial software. It is a design reference only — never a
dependency.
