# ADR-0004 — Messaging provider abstraction (email & SMS) with working log drivers

**Status:** Accepted (2026-08-14) · Enables Stage 6 (Marketing Platform: EMAIL, SMS, AUTO) and Master Prompt §16, §17, §38.

## Context

The marketing platform sends **email** and **SMS** — campaigns, nurture sequences, workflow actions,
alerts. Real delivery requires third-party providers (SendGrid/SES/Mailgun/SMTP for email; Twilio for
SMS) with accounts, API keys, verified sending domains and phone numbers. This environment has **none
of those credentials**, and Master Prompt §38 forbids presenting a fake send as "done" — we must
honestly separate what is Tested from what requires credentials.

We also must not scatter provider SDK calls across campaign, automation and notification code, or a
provider swap becomes a rewrite (same reasoning as ADR-0003 for payments).

## Decision

**Route every outbound message through our own tables and a narrow provider interface — never a
provider SDK directly.** Our `campaigns` / `campaign_recipients` / `outbound_messages` tables are the
source of truth for what was sent, to whom, and its engagement; the provider only performs transport.

Two interfaces, `MailProvider` and `SmsProvider`, each returning a `SentResult` (provider message id,
accepted/failed, error). Drivers are selected by config (`MARKETING_MAIL_PROVIDER`,
`MARKETING_SMS_PROVIDER`):

1. **`log` (default, fully working & tested)** — `LogMailProvider` / `LogSmsProvider` record the
   rendered message (to Laravel's log + an in-memory sink in tests) and return a synthetic accepted
   `SentResult`. This is **not a stub of the pipeline**: recipient resolution, suppression filtering,
   personalization/merge-tag rendering, per-recipient tracking rows, open/click tracking, retries and
   analytics all run for real against the log driver. It is the driver used in development and tests.

2. **`smtp` (email) / `twilio` (SMS)** — real drivers implementing the same interface. They are real
   code but, lacking credentials here, are **not run in tests**; their register status is
   _Implemented (untested — requires credentials)_, never "Tested." Activated by config + credentials.

A `MessagingProviderManager` resolves the active driver so type-hinting `MailProvider` / `SmsProvider`
yields it (mirrors `PaymentProviderManager`). Engagement (opens/clicks/unsubscribes/opt-outs) is
captured by **our own** tracking endpoints (a 1×1 open pixel, click-through redirects, an unsubscribe
page), so analytics do not depend on a provider's webhooks; provider webhooks (bounces/complaints)
plug into the same tables when a real driver is configured.

## Consequences

- The whole marketing execution engine — campaigns, sequences, workflow send actions, tracking,
  suppression/consent, analytics — is real and tested without any email/SMS account.
- Turning on real delivery is configuration + credentials, not a rewrite (§16/§17).
- We stay honest per §38: the log driver's path is Tested; the SMTP/Twilio paths are labelled untested
  until credentials and a sandbox exercise them.
- **Consent is enforced centrally**: a `suppressions` table (email unsubscribes + SMS opt-outs) is
  checked by the dispatch pipeline for every recipient, so no module can bypass unsubscribe/opt-out —
  a compliance requirement (CAN-SPAM/TCPA-oriented) that belongs below the campaign/automation code.
