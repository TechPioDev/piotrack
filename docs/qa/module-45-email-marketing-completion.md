# Module Completion Report — Email Marketing, Phase 34

**Date:** 5 September 2026 · **Module:** Email Marketing · **Register:** 20/20 Tested (**100%**), was 13/20 (65%)

## What shipped

**Segmentation (EMAIL-012) — with a real bug fix.** Campaign send resolved its
audience from static list memberships only: a dynamic list's criteria members were
silently skipped. Send now resolves through `ListService::members()`, so criteria
segments (lifecycle stage, source, min score) receive campaigns exactly as the list
screen shows them.

**A/B testing (EMAIL-015)** — the old note claimed schema-readiness that didn't
exist; both halves are now real: `campaigns.subject_b` arms a deterministic
recipient split, each half's subject renders through merge tags, and results report
per-variant recipients/opens/open-rate and the leader — the tenant's own measured
numbers.

**Dynamic content (EMAIL-013)** — conditional blocks join merge tags:
`{{#if field}}…{{else}}…{{/if}}` and `{{#if field=value}}…{{/if}}` over the merge
fields plus lifecycle_stage/lead_source, rendered per contact before substitution.

**Behavioral triggers (EMAIL-014)** — the `email_engagement` workflow trigger was
registered but nothing fired it. The first campaign click now fires it (tenant
context set from the recipient on the public tracking route), enrolling matching
workflows exactly once, best-effort so tracking never fails because a workflow did.

**Conversion attribution (EMAIL-019)** — per-campaign conversions: recipient
contacts whose deals closed won *after* the send, as customers + revenue on the
campaign results. A pre-send win never counts.

**Sequences + event campaigns (EMAIL-003/009)** — sequences close on the workflow
engine (per-step delays + send-email actions ARE the sequence runner; a two-step
delayed plain-text sequence is pinned end-to-end), and the event flow composes
tested parts: event-typed campaign → booking-page registration (P18) → attendee
reminders (P30).

## Gate evidence

- Pest: **986 passed / 4,785 assertions** (+6:
  [EmailMarketingCloseoutTest](../../tests/Feature/Qa/EmailMarketingCloseoutTest.php));
  EmailCampaignTest and MarketingAutomationTest still green.
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
