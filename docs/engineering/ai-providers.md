# AI providers — how it works, how to configure it, which to pick

Every AI feature in Piotrack — the chat widget's answers, the sales agent,
summaries, drafting — is powered by a large language model from an outside
provider. This document explains the whole path in plain terms: what happens on
each step, what "training" actually means here, how to configure a provider,
and an honest comparison of the options.

## How to configure it

**Platform → AI Provider** in the sidebar (platform staff only). Pick a
provider, paste its API key, optionally set a model, save, then press **Test
this provider** — it makes one tiny real call and reports the model, the
latency and the reply, so "is my key working" is answered on that screen rather
than by a visitor.

Details worth knowing:

- Keys are **encrypted at rest** and **write-only**: the page shows only the
  last four characters, the audit trail records that a key changed but never
  its value, and tenant admins cannot reach the screen at all.
- Settings saved here **override the environment**, so switching provider or
  rotating a key needs no deploy. The `.env` values (`AI_DRIVER`,
  `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GEMINI_API_KEY`) remain the fallback.
- The **fixture** provider is the shipped default: deterministic placeholder
  text, free, offline. It proves the plumbing and is clearly labelled — it is
  the right setting until a real key exists and the wrong one afterwards.

## What happens when a visitor asks the chat a question — every step

Worked example. A visitor on a customer's website opens the chat, picks
_"I just have a question"_, and types **"Do you support Microsoft 365?"**

1. **The widget sends only the text.** The browser POSTs the question to
   `/wc/{widget-key}/conversations/{token}/messages`. No AI happens in the
   browser; the widget renders whatever the server returns.
2. **The engine decides whether AI is even involved.** The conversation's
   current step is an `ai` node. Guards run first: previews get a canned
   answer and spend nothing; a conversation that has already asked five
   questions is handed to a person instead.
3. **The gateway is the only door to a provider** (`AiGateway`). Before any
   model is called it checks the tenant's plan has AI credits left — an
   out-of-credit tenant never reaches a provider, so there is no surprise
   spend.
4. **The prompt is assembled — this is the "training" step, and it happens per
   request.** The stored `chat.answer` prompt template is filled in with the
   company's real facts:

    > _System:_ You answer visitor questions on an IT services company website.
    > Use only the company facts provided. Never invent pricing, response
    > times, guarantees or commitments… \
    > _Prompt:_ Company: Northwind IT Services. Services offered: Managed IT,
    > Cybersecurity, Microsoft 365, Backup & DR… Visitor asks: Do you support
    > Microsoft 365? Answer in under 80 words.

5. **The provider generates the answer.** The prompt goes over HTTPS to
   whichever provider is configured; the model writes a reply token by token.
   A timeout, a rate limit or a 5xx is retried a bounded number of times.
6. **Cost is recorded, credits are spent, the call is audited.** The gateway
   stores tokens used and estimated cost per tenant, per feature, per request
   — on success only.
7. **The visitor sees the answer**, then a follow-up choice ("Ask another
   question / I'd like to talk to someone / That's all") that keeps the
   conversation moving toward capture.
8. **If anything on steps 3–5 fails**, the visitor sees one calm sentence —
   _"let me take your details and a person will come straight back to you"_ —
   and the flow continues into qualification. A visitor never sees an error.

## "How is it trained?" — the honest answer

**We do not train anything, and neither do you.** The models are _pre-trained_
by their vendors on vast general text corpora, at a cost of millions of
dollars, long before we call them. Three different techniques get confused
under the word "training":

| Technique                 | What it is                                                                              | Who does it                     | Used here?                                         |
| ------------------------- | --------------------------------------------------------------------------------------- | ------------------------------- | -------------------------------------------------- |
| **Pre-training**          | Building the model itself from huge corpora                                             | Anthropic / OpenAI / Google     | Yes — that is what you are buying with the API key |
| **Fine-tuning**           | Adjusting a model's weights on your own examples                                        | You, at real cost, per provider | No — see below                                     |
| **Grounding / prompting** | Handing the model your facts _inside each request_ and instructing it to use only those | **Piotrack, automatically**     | **Yes — this is the mechanism**                    |

Piotrack uses grounding. Every request carries the tenant's own facts (company
name, service lines) plus strict instructions: answer only from these, never
invent pricing or commitments, keep it short. The model never remembers the
conversation afterwards and your data does not become part of the model —
each request stands alone.

Why not fine-tuning? For this workload it is the wrong tool: expensive, slow
to update (retrain every time a fact changes), and no better at "answer from
these facts" than grounding is. Grounding updates the moment your data does,
costs nothing extra, and keeps every answer traceable to inputs you control.
Prompt templates are versioned in the app (`PromptRegistry`) — publishing a
new version never mutates the old one, so behaviour changes are deliberate
and reversible.

## Which provider is best?

Honest note first: Piotrack's development assistant is Anthropic's Claude, so
read the Anthropic recommendation with that in mind. The comparison below is
still a fair one, and the abstraction makes switching a two-minute job — pick
one, measure, move if you disagree.

|                            | Anthropic (Claude)                                                               | OpenAI (GPT)                                  | Google (Gemini)                                 |
| -------------------------- | -------------------------------------------------------------------------------- | --------------------------------------------- | ----------------------------------------------- |
| Default model here         | `claude-sonnet-5`                                                                | `gpt-4o-mini`                                 | `gemini-2.5-flash`                              |
| Character                  | Strong instruction-following, low fabrication rate                               | Broadest ecosystem, very capable small models | Aggressive pricing on the flash tier            |
| Fits this product because… | Answers must not invent commitments — refusal discipline matters more than flair | Cheap, fast, everywhere; a safe default       | Cheapest per answer if the stack is Google-side |
| Key from                   | console.anthropic.com                                                            | platform.openai.com                           | aistudio.google.com                             |

**Recommendation for the chat widget:** a _small fast_ model, whichever vendor
you pick — `claude-haiku-4-5-20251001`, `gpt-4o-mini` or `gemini-2.5-flash`.
Widget answers are 80-word grounded replies; a frontier-tier model costs
5–20× more per answer and a visitor cannot tell the difference on this task.
Spend the difference on volume. If you only want one opinion: start with
**Claude Haiku 4.5** — this workload's whole risk is an invented commitment on
a marketing site, and refusal discipline is the thing Claude is strongest at.
Set it as the model on the Anthropic provider; the price table in
`config/ai.php` already carries its rate so cost tracking stays honest.

All three APIs are usage-billed (fractions of a cent per chat answer at the
small tier). The gateway meters every call per tenant against plan credits, so
platform spend is capped regardless of visitor volume.

## Operational notes

- **The server needs outbound HTTPS** to the provider's API. The current
  internal server has no outbound internet (SonicWall policy), so a saved key
  will fail its Test there until outbound 443 is allowed to the provider's
  host — the same constraint that made certificate issuance an off-box job.
- Switching provider changes _who answers_; it never changes metering,
  auditing, credit caps or the failure behaviour. Those live in the gateway.
- The Test button calls the provider directly, deliberately outside the
  gateway: a connectivity check should not consume a tenant's credits or
  appear in their usage.
