# Module Specification — AI (AIPF / AISA / AIVIS)

> Stage 12. Completed against the register at the module gate (Master Prompt §58–59).

## Purpose

Put a language model behind the sales desk and behind AI-search visibility — safely. **AIPF** is the
platform layer every AI feature runs through (provider abstraction, versioned prompts, cost tracking,
plan limits, error handling, human confirmation). **AISA** is the AI sales agent (qualification, chat,
summaries, drafting, research, scoring, next-best-action). **AIVIS** is the AI visibility dashboard
(prompt library monitored across AI engines, share of voice, frequencies, competitor comparison,
dimension breakdowns, trend, alerts).

## Users & roles

Permissions (`ai.*`):

- `ai.view` — read AI surfaces (usage/cost, conversations, visibility dashboard, action queue).
- `ai.agent.use` — invoke agent features (qualify, draft, summarize, score, chat).
- `ai.actions.approve` — confirm or reject proposed sensitive AI actions.
- `ai.prompts.manage` — publish/activate prompt template versions and manage the AIVIS prompt library.

Owner/Admin/MarketingManager/SalesManager get all. SalesRep + MarketingUser get view + agent.use.
Analyst gets view + agent.use. Viewer gets view only. **Approval is deliberately not granted to the
roles that merely use the agent** where a separation is meaningful (SalesRep proposes, manager approves).

## Feature IDs

AIPF-001…006, AISA-001…016, AIVIS-001…017 (39 features).

## Subscription requirements

New `Feature::Ai` (Professional, Agency, Enterprise) gates AISA + AIPF surfaces; the existing
`Feature::AiVisibility` continues to gate AIVIS. **`Limit::AiCredits` — declared unenforced since
Stage 3 — becomes enforced here**: Professional 5,000 / Agency 20,000 / Enterprise unlimited credits per
period, metered through the existing `UsageMeter`. One gateway call consumes one credit; the call is
refused when the tenant is out.

## Database entities (new, all tenant-scoped)

- `ai_prompt_templates` — key, version, system, template, is_active, description. Unique (org, key,
  version); one active version per key.
- `ai_requests` — feature, provider, model, prompt_tokens, completion_tokens, estimated_cost (minor
  units), duration_ms, status (succeeded/failed), error, user_id, prompt_template_id?.
- `ai_actions` — type, summary, payload (json), status (pending/confirmed/rejected/executed), subject
  (polymorphic-ish subject_type/subject_id), proposed_by, confirmed_by, confirmed_at, executed_at, result.
- `ai_conversations` — contact_id?, channel (web/chat), status, started_at, summary?.
- `ai_messages` — ai_conversation_id, role (user/assistant), body, ai_request_id?.
- `ai_prompts` (AIVIS library) — text, category, service?, city?, vertical?, is_active.
- `ai_visibility_checks` (existing, extended) — + ai_prompt_id?, + recommended (bool).

## Services

- **`AiGateway` (AIPF)** — the single entry point for every model call: resolves the active prompt
  template, renders variables, checks `Feature::Ai` entitlement + `Limit::AiCredits`, calls the provider
  with bounded retry on transient failures, records an `ai_requests` row with token counts + estimated
  cost, increments usage on success only, audits, and returns the completion. Throws when out of credits.
- **`PromptRegistry` (AIPF-001)** — render(key, vars), publish(key, template) → new version,
  activate(key, version), history(key). Publishing never mutates an existing version.
- **`AiActionService` (AIPF-006)** — propose(type, summary, payload, subject) → pending action;
  confirm(action, user) → confirmed; reject(action, user); execute(action) → runs the handler **only**
  when confirmed, idempotent by status. Sensitive types (`send_email`, `update_crm`, `book_meeting`)
  can never be executed from `pending`.
- **`AiSalesAgent` (AISA)** — qualify(contact), reply(conversation, message), summarizeConversation(),
  summarizeCall(call), draftEmail(contact, purpose), followUp(contact), handleObjection(text),
  nextBestAction(contact), researchLead(contact) / researchAccount(company), scoreLead(contact),
  scoreOpportunity(deal), proposeCrmUpdate(contact, changes) → `ai_actions`, proposeBooking(contact) →
  `ai_actions`, draftProposal(deal). Everything that writes or reaches a third party goes through
  `AiActionService`; everything else returns text to the human.
- **`AiVisibilityDashboard` (AIVIS)** — runLibrary(brand, engines) executes the active prompt library
  through the existing Stage 7 `AiSearchProvider`; shareOfVoice(), frequencies() (mention / citation /
  recommendation), competitorComparison(), byDimension(service|city|vertical), trend(days),
  alerts(threshold) (visibility change since the prior window).

## API endpoints

`/ai/*` (Inertia), all `entitlement:ai` (AIVIS surfaces `entitlement:ai_visibility`), tenant-scoped:
dashboard/usage (`ai.view`), agent actions (`ai.agent.use`), conversations + reply, action queue with
confirm/reject (`ai.actions.approve`), prompt templates publish/activate (`ai.prompts.manage`),
visibility dashboard + prompt library CRUD + run.

## UI pages & components

Sidebar **AI** group: AI overview (usage + cost + recent requests), Sales agent (run features on a
contact), Conversations, **Action queue** (pending proposals with confirm/reject and the exact payload
shown), Prompt templates (versions + activate), AI visibility (dashboard + prompt library).

## Integrations

OpenAI/Anthropic via config + keys (INTG). When unconfigured the platform runs on the fixture driver and
the UI states plainly that a fixture model is in use — never implying live model output.

## Background jobs

`ai:run-visibility-checks` (scheduled) runs each tenant's active prompt library across configured
engines and records checks + alerts. Idempotent per prompt/engine/day.

## Business rules & validation

Cost in minor units from configured per-million-token pricing. A failed call is recorded but **not**
billed as usage. One active prompt-template version per key. Sensitive actions require confirmation by a
user with `ai.actions.approve`; a rejected action is terminal; execute is idempotent. AI scores are
advisory and stored separately from the deterministic Stage 10 `lead_score` — they never silently
overwrite it.

## Audit requirements

`ai.request.completed` / `ai.request.failed`, `ai.prompt.published` / `ai.prompt.activated`,
`ai.action.proposed` / `.confirmed` / `.rejected` / `.executed` (with actor), `ai.visibility.checked`.

## Automated tests

Gateway: records tokens + cost, enforces `AiCredits` (refuses over limit, does not bill failures),
retries transient failure then succeeds, audits. PromptRegistry: publish creates a new version, activate
switches, render substitutes variables, one active version per key. **Confirmation: a sensitive action
never executes while pending; executes only after confirm; reject is terminal; execute is idempotent;
approval requires the permission.** Agent: qualification/draft/summary/score paths on the fixture
driver; CRM update + booking arrive as pending actions rather than direct writes. Visibility: library
run records checks, share of voice, frequencies, competitor comparison, dimension breakdown, trend,
change alert. RBAC (view vs use vs approve vs prompts), `ai` entitlement gating (Growth blocked /
Professional allowed), tenant isolation.

## Acceptance criteria

- Every model call goes through `AiGateway`; no feature touches a provider directly.
- Cost + tokens recorded per tenant/user/feature; `AiCredits` hard-caps spend before it happens.
- **No sensitive AI action can execute without an explicit human confirmation**, proven by tests.
- Prompts are versioned; each request is traceable to a prompt version.
- Fixture driver fully tested; live drivers honestly marked _Implemented (untested — requires
  credentials)_. Full gate green; honest register; Module Completion Report + §65 cycle report.
