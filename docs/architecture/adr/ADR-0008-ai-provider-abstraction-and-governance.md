# ADR-0008 — AI provider abstraction with cost, limit and human-confirmation governance

**Status:** Accepted (2026-08-14) · Enables Stage 12 (AI: AIPF, AISA, AIVIS) and Master Prompt §16, §38.

## Context

Stage 12 puts a language model behind sales work (qualification, drafting, summarizing, scoring,
recommendations) and behind AI-search visibility monitoring. Three problems have to be solved before any
feature is written:

1. **No credentials here.** Real OpenAI/Anthropic calls cannot run in this environment, and §38 forbids
   presenting fabricated model output as a working, tested feature.
2. **AI costs real money per call**, unlike every previous provider in this codebase. An unmetered or
   runaway feature is a direct financial liability for the tenant and for us, so cost must be attributed
   per tenant, per user and per feature — and be capped by plan.
3. **AI acting on its own is the real risk.** A model that can send an email, change a CRM record or
   book a meeting without a human saying yes is a system that can do damage at machine speed on
   probabilistic output. This is a product-safety decision, not only an engineering one.

Prior stages established the provider pattern (ADR-0003…0007): narrow interface, tested fixture driver,
real credential-gated driver, config-selected manager. Stage 12 keeps that and adds a governance layer,
because unlike a rank lookup, a model call both costs money and can propose consequential actions.

## Decision

### 1. One narrow interface, never a vendor SDK in feature code

`AiProvider` → `complete(AiRequest): AiCompletion` where `AiCompletion` carries the text plus prompt and
completion token counts and the model name. Drivers are selected by `config('ai.driver')`:

- **`fixture` (default, fully working & tested)** — deterministic completions derived from a hash of the
  rendered prompt, returning realistic token counts. The entire pipeline — prompt rendering and
  versioning, retries, cost accounting, usage limits, the confirmation workflow, every agent feature and
  the visibility dashboard — runs for real against it, in dev and in tests.
- **`openai` / `anthropic`** — real drivers over the vendor HTTP APIs. Real code, but with no keys here
  they are **not run in tests**; register status _Implemented (untested — requires credentials)_, never
  "Tested."

**No feature calls a provider directly.** Every call goes through `AiGateway`, which is the single
choke point for prompts, limits, retries, cost and audit. This is what makes the governance
non-bypassable rather than a convention.

### 2. Prompts are versioned data, not string literals

`ai_prompt_templates` stores `(key, version, system, template, is_active)`. `PromptRegistry` renders a
template by key with variables, always resolving the active version. Publishing creates a new version
rather than mutating the old one, so a prompt change is reviewable and reversible and every recorded AI
request can be traced to the exact prompt version that produced it.

### 3. Cost and usage are recorded on every call and capped by plan

Each call writes an `ai_requests` row: feature, provider, model, prompt/completion tokens, estimated
cost (minor units, from a configured per-million-token price), duration, status and error. Cost is
therefore attributable per tenant, per user and per feature. `Limit::AiCredits` — which has existed
unenforced since Stage 3 — is enforced here through the existing `UsageMeter`: the gateway refuses the
call when the tenant is out of credits, so overspend is impossible rather than merely visible.

### 4. Sensitive AI actions require explicit human confirmation

AI output that only informs a human (a summary, a draft, a recommendation, a score) is returned
directly. AI output that would **change data or reach a third party** — sending an email, updating a CRM
record, booking a meeting — is never executed by the model. It is recorded in `ai_actions` as a
_proposal_ with status `pending`, and a user holding `ai.actions.approve` must confirm it before
`execute()` will run; rejection is terminal. Execution is idempotent by status, and every propose /
confirm / reject / execute transition is audited with the acting user.

This boundary is deliberate and absolute: there is no configuration flag that lets a tenant turn on
autonomous execution of sensitive actions. Convenience does not justify letting probabilistic output
take irreversible action unattended.

### 5. Failures are contained and honest

The gateway retries only transient provider failures, with a bounded attempt count, and records the
failure on the request row. A failed AI call surfaces an error state to the user; it never silently
substitutes fabricated content, and a failed call is not billed as usage.

## Consequences

**Positive.** Feature code stays provider-agnostic and testable; switching or adding a model is a driver
plus config. Cost is attributable and hard-capped before it is spent. The blast radius of a bad model
output is bounded by the confirmation gate. Prompt changes are versioned and auditable.

**Negative / accepted.** Every AI feature carries gateway indirection even for trivial calls. The
confirmation queue adds a step for users who would rather the agent "just did it" — accepted
deliberately. Real driver behaviour (streaming, rate limits, tool use, model-specific quirks) remains
unverified until credentials exist, and the register says so per feature rather than implying coverage.

**Revisit when** credentials are available (verify the live drivers, then re-status those rows), when
streaming or tool-calling is needed, or when a per-feature model routing policy becomes worthwhile.
