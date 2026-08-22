# Module Specification — Website Chat / Conversations (CHAT)

> Spec-first per Master Prompt §58–59 and CLAUDE.md. Built in phases; each phase is a
> working, gated, deployed vertical slice. The module is COMPLETE only when the whole
> chain (widget → qualify → lead → CRM → score → route → meeting → attribution → inbox
> → analytics → security → tests) passes.

## Purpose

Give each tenant a professional, configurable **website chat + conversational lead
qualification** product they can embed on their own website. It converts an anonymous
visitor into a Known Visitor → Qualified Lead → CRM Contact → Sales Opportunity →
Meeting → Customer → Attributed Revenue — without needing HubSpot or another chat
platform. This is a first-class, multi-tenant SaaS capability, configurable separately
per tenant.

Not a static "question → three answers" popup: tenants design their own conversations in
a flow builder, style their own widget, route leads their own way, and see their own
analytics.

## Users & roles

- **Marketing / Sales managers & users** — create and manage widgets, flows, routing,
  and work the conversation inbox.
- **Sales representatives / agents** — work the inbox, take live chats, book meetings.
- **Analysts / viewers** — read-only inbox + analytics.
- **Owner/Admin** — everything (auto-inherit via `$all` / `$adminExceptDelete`).

Permissions (added to `app/Authorization/Permission.php`; auto-registered as Gate
abilities; grouped as `$chatAll` in `RolePermissions.php`):

- `chat.view` — see the module (inbox, widgets, analytics) read-only.
- `chat.inbox.handle` — reply in conversations, take live chats, add notes, change status.
- `chat.conversations.assign` — assign/route conversations to agents.
- `chat.widget.manage` — create/edit/publish widgets, flows, routing, settings.
- `chat.settings.manage` — module-level settings (business hours, consent, domains).

## Feature IDs

New register block `CHAT-###` (declared in `scripts/build_feature_register.py`, then
regenerated into `docs/register/feature-register.csv`). One row per capability
(widget builder, flow builder, node types, qualification, lead capture, dedupe, scoring,
routing, meetings, consent, inbox, live chat, handoff, analytics, funnel, drop-off,
attribution, targeting, install, domain security, tenant isolation, mobile, a11y,
performance, failure handling). Status advances per phase.

## User stories

- As a marketing manager, I create a "Managed IT Website Qualification" widget, design its
  conversation, and copy an install snippet onto our website.
- As a website visitor, I open the chat, pick "Cybersecurity", answer a few questions,
  leave my details, and book a consultation — in one smooth conversation.
- As a sales rep, I get a Hot Lead alert, open the conversation in the inbox, see the full
  transcript + lead score + CRM link, and follow up.
- As an owner, I see how many visitors the chat converts into meetings and revenue, and
  which questions people drop off at.

## Subscription requirements

- New plan feature `Feature::Chat = 'chat'` (`app/Billing/Feature.php`), added to the
  Growth/Professional/Agency plans in `PlanCatalog.php` (Enterprise auto-includes all);
  re-seed via `php artisan billing:sync-plans`.
- Route group gated with `entitlement:chat`. Public widget endpoints check the owning
  tenant's entitlement server-side before serving/ingesting.
- (Later) optional metered limits (e.g. conversations/month) via `app/Billing/Limit.php`.

## Database entities

All tenant-scoped via `BelongsToTenant` (`organization_id` first in `$fillable`,
immutable, auto-stamped). Migrations start at `2026_08_26_100001_...`. Money (none
expected here) would be minor-units integers. Tenant-first composite indexes.

- **chat_widgets** — `organization_id`, `name`, `description`, `status`
  (`draft|active|paused`), `public_key` (unique, non-secret id used by the embed script),
  `flow` (json — the conversation graph: nodes + edges), `theme` (json — logo, avatar,
  accent, position, title, launcher), `targeting` (json — pages include/exclude, devices,
  visitors), `business_hours` (json), `consent` (json — required?, message, privacy URL),
  `routing` (json — strategy + assignments), `settings` (json — teaser, language, live-chat
  mode), `allowed_domains` (json). Indexes: `[organization_id, status]`, unique
  `[public_key]`.
- **chat_conversations** — `organization_id`, `chat_widget_id` (FK cascade), `status`
  (`new|open|waiting|assigned|qualified|converted|closed|spam`), `visitor_id` (anonymous
  cookie id), `assignee_id` (users, nullOnDelete), `contact_id`/`lead_id` (nullable FKs to
  CRM), `lead_score` (int), `answers` (json — collected field values), `attribution` (json
  — source/page/utm/campaign/keyword/referrer), `last_message_at`. Indexes:
  `[organization_id, status]`, `[organization_id, chat_widget_id]`,
  `[organization_id, assignee_id]`.
- **chat_messages** — `organization_id`, `chat_conversation_id` (FK cascade), `role`
  (`visitor|bot|agent|note|system`), `author_id` (nullable user), `body`, `meta` (json —
  choice options, node id, field key). Index `[organization_id, chat_conversation_id]`.
- **chat_events** — `organization_id`, `chat_widget_id`, `chat_conversation_id`
  (nullable), `type` (`impression|open|start|complete|lead|qualified|meeting|dropoff`),
  `node_id` (nullable), `meta` (json). Feeds funnel + question-drop-off analytics. Indexes
  `[organization_id, chat_widget_id, type]`, `[organization_id, type, created_at]`.

Models expose typed relationships (`widget`, `conversation`, `messages`, `events`,
`assignee`, `contact`, `lead`). Factories set `organization_id => Organization::factory()`
and build parents in `configure()`.

## API endpoints

**Authenticated admin** (`routes/chat.php`, group
`['auth','verified','organization','entitlement:chat']`, prefix `chat`, name `chat.`,
per-route `can:`):

- `GET  chat` (inbox) — `can:chat.view`
- `GET  chat/conversations/{conversation}` — `can:chat.view`
- `POST chat/conversations/{conversation}/reply` — `can:chat.inbox.handle`
- `POST chat/conversations/{conversation}/note` — `can:chat.inbox.handle`
- `PATCH chat/conversations/{conversation}` (status/assignee) — `can:chat.inbox.handle` /
  `can:chat.conversations.assign`
- `GET/POST/PATCH/DELETE chat/widgets...` — `can:chat.widget.manage` (read via `chat.view`)
- `GET  chat/analytics` — `can:chat.view`
- `GET  chat/settings` / `PATCH chat/settings` — `can:chat.settings.manage`

**Public, unauthenticated, CSRF-exempt, CORS-scoped** (mirroring the existing public
form/booking pattern; tenant + widget resolved from `public_key`, never a session):

- `GET  wc/{publicKey}/config` — returns the widget's runtime config (flow, theme, consent,
  business-hours state). Server checks widget `active` + tenant entitlement + domain
  allow-list (Origin) before responding.
- `POST wc/{publicKey}/events` — record impression/open/dropoff (throttled).
- `POST wc/{publicKey}/conversations` — start a conversation (returns conversation token).
- `POST wc/{publicKey}/conversations/{token}/messages` — advance the flow / submit answers;
  server runs qualification, capture, dedupe, scoring, routing, alert, attribution.
- `GET  wc/{publicKey}/availability` + `POST .../book` — meeting slots + booking (reuse the
  booking service). Honeypot + rate limiting; CSRF-exempt via the `wc/*` prefix in
  `bootstrap/app.php`.

The embed script itself is a **static, self-contained `widget.js`** (separate Vite build
entry) served from the app origin; it reads its `data-widget` public key, fetches config,
and renders the widget in an isolated container/shadow root on the customer's site.

## UI pages & components

Sidebar group **"Website Chat"** (distinct from AI → Conversations). Sub-items: Inbox,
Widgets, Flow Builder, Routing, Meetings, Analytics, Settings.

- **Inbox** — conversation list (filters: All/Unassigned/Mine/Open/Closed) + conversation
  view (transcript, composer, internal notes) + a lead-details rail (score, company, owner,
  Open in CRM). Reuses `PageHeader`, `Table`, `InitialAvatar`, `Badge`, `EmptyState`.
- **Widgets** — list + create/edit (config: appearance, targeting, business hours, consent,
  routing, install snippet). Reuses `PageHeader`, `Table`, `EmptyState`, form primitives.
- **Flow Builder** (Phase 2) — visual node editor.
- **Analytics** — impressions/opens/conversations/leads/meetings, funnel, question drop-off.
- Runtime widget + launcher are new self-contained components (not Inertia pages).

## Integrations

- **CRM** (internal) — create/update Contact (+Company, Lead) with duplicate detection by
  email/phone/domain; reuse existing lead-scoring and lead-source/attribution handling.
- **Booking** (internal) — reuse the existing booking/availability service for in-chat
  scheduling and round-robin assignment.
- **Notifications** — sales alert on Hot lead (reuse existing alert mechanism); later Slack/
  Teams/email channels.
- External chat platforms are explicitly NOT required; this replaces them.

## Notifications

Events: new hot lead, visitor requesting a live agent, unanswered conversation, meeting
booked. Channels: in-app first; email/Slack/Teams later. Per-tenant configurable.

## Background jobs

- Attribution + scoring run inline on message ingest (fast); heavy analytics rollups and
  outbound notifications are queued. Idempotent conversation/lead creation (dedupe) so
  retries don't duplicate.

## Business rules & validation

- Flow execution is **server-authoritative**: the server decides the next node, validates
  each answer (email/phone/required), and computes score — the client cannot fake
  qualification. Widget config is validated on save (valid graph, no orphan nodes).
- Consent: if required, no PII is stored until the visitor accepts; consent copy/URL are
  per-tenant (never hard-coded).
- Existing-customer branch never auto-creates a new sales lead; it routes to support.
- Security-incident branch flags HIGH priority + alert; the bot never gives remediation
  advice to anonymous visitors.
- Progressive profiling: don't re-ask known fields for a returning/known visitor.

## Error cases

- Backend/config unavailable → the embed fails **gracefully** (shows a configured fallback
  contact message) and never breaks the host website.
- Unknown/paused widget, disallowed origin, or un-entitled tenant → config endpoint returns
  a safe "unavailable" response, not tenant data.
- Validation errors are shown inline in the conversation; network errors retry politely.

## Audit requirements

Record assignment changes, status changes, lead-score changes, CRM actions, and meeting
bookings on the conversation timeline (and via the existing audit logger where applicable).

## Analytics events

`impression, open, start, complete, lead, qualified, meeting, dropoff(node)` →
`chat_events`, powering engagement/lead/conversion metrics, the funnel, and per-question
drop-off.

## Automated tests

- **Tenant isolation**: one org can never read/write another's widgets, conversations,
  messages, leads, analytics (public and admin paths).
- **Authorization**: each route enforces its `can:` permission; entitlement gate blocks
  un-planned tenants.
- **Flow execution**: submitting the sample MSP flow creates one Contact+Lead (no
  duplicate on re-submit), computes the expected score, sets attribution, routes to the
  expected agent, and books a meeting.
- **Public endpoint security**: CSRF-exempt but origin/domain-checked; honeypot + throttle;
  paused/unknown widget returns no data.
- **Frontend** (Vitest): widget renders a flow, choice/inputs work, consent gate blocks
  capture until accepted, mobile layout, a11y (labels/focus/close).

## Manual QA checklist

Launcher/header/avatar/typography match the design system; choice buttons legible; consent
readable + privacy link works; close/minimize works; transcript scrolls; desktop + tablet +
mobile (near-full-screen) layouts; all states (teaser, open, qualifying, validation,
consent, agent on/off, booking, success, closed, error, reopened, returning). Target ≥ 9/10.

## Acceptance criteria (module gate)

Widget → Builder → Qualification → Lead Capture → CRM → Scoring → Routing → Appointment →
Consent → Inbox → Analytics → Attribution → Responsive UI → Security → Tests all pass; the
§51 realistic live example (Michael Rodriguez / Precision Manufacturing / Cyber+CMMC) runs
end to end; full quality gate green; Module Completion Report in `docs/qa/`; register
updated. Only then marked COMPLETE.

## Phasing

- **P0** foundation: spec, permissions, entitlement, tables/models/factories, register rows.
- **P1** core vertical slice: embeddable widget + public endpoints + default MSP flow →
  capture → CRM dedupe → score → route → alert → attribution → meeting offer; admin Widgets
  list/config + Inbox; isolation/authz/flow tests; QA report; register update.
- **P2** visual flow builder + templates + all node types.
- **P3** live chat + human handoff + presence + notes/@mentions + statuses + realtime inbox.
- **P4** analytics depth (funnel, drop-off, A/B), notification channels, targeting, install
  methods, domain hardening, mobile/a11y/perf pass.
