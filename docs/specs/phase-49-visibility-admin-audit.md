# Phase 49 — AI Visibility + Audit Logging + Platform Admin close-out (AIVIS-004/005/006, AUDIT-004, ADMIN-002)

**Goal: AI Visibility Dashboard 14/17 → 17/17, Audit Logging 5/6 → 6/6, Platform Administration 5/6 → 6/6 (three modules to 100%).**

## Insights

- **AIVIS**: the blocker note is "no wired API path for this engine yet". The module's own
  Tested bar (ChatGPT, Gemini) is: a real driver class that goes live the moment credentials
  exist, Http::fake-verified mechanics, `[Simulated]` labeling otherwise. Three wired paths now
  exist: **Perplexity** has a public OpenAI-compatible API (api.perplexity.ai); **Google AI
  Overview** is returned by SerpApi's google engine (`ai_overview` block) — riding the SerpApi
  key the rank driver already uses; **Copilot** has no public consumer API (stated plainly) —
  the driver targets the Microsoft-supported programmatic surface behind Copilot (an Azure
  OpenAI deployment, or an OpenAI-compatible gateway to the M365 Copilot Chat API) via a
  configurable endpoint + key, and stays honestly simulated until both are configured.
- **AUDIT-004**: stale note — deal create/update/delete/stage, contact/company/lead CRUD,
  campaign create/delete and three `data.exported` events already log. The remaining gap is
  campaign **update** and **send**; close it and pin the whole register-named set with a test.
- **ADMIN-002**: plans + entitlements editing shipped in P40 (the matrix editor). Remaining:
  coupon management UI (model + redemption service exist since Stage 3) and manual payment
  actions — invoice retry through the tested PaymentProvider seam (Manual/Stripe both
  implement `payInvoice`).

## Build

- `PerplexityAiSearchProvider` (key: `seo.perplexity.key` / platform console, model `sonar`),
  `SerpApiAiOverviewProvider` (rides `seo.serpapi.key`), `CopilotAiSearchProvider`
  (`seo.copilot.endpoint|key|model`, OpenAI-compatible). `SeoProviderManager::aiProviderNameFor`
  routes each engine to its driver when credentials exist; `engineStatuses` flips live/simulated
  automatically, so the existing UI labeling keeps working unchanged.
- `CampaignController::update/send` += audit events (`campaign.updated`, `campaign.sent`).
- `SubscriptionService::retryInvoice()`; PlatformController coupons list/create/toggle +
  unpaid-invoice list/retry; routes; `platform/plans.tsx` sections.
- `tests/Feature/Qa/VisibilityAdminAuditCloseoutTest.php` (~5 tests).

## Honesty lines

- Engines without credentials keep reading **simulated** everywhere; a driver never fabricates
  an answer when its credentials are missing (unavailable result, exactly like the OpenAI driver).
- The Copilot driver's docblock and the register note name its real surface precisely — no
  pretending consumer Copilot has an API.
- Live Stripe payment actions remain the tested provider seam's live half (ADR-0004);
  the manual provider proves the mechanics end to end.
