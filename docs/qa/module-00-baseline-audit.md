# Module 00 — Deep Application Audit, Jumpfactor Comparison & Product Scoreboard

Date: 2026-08-26 · Auditor: automated deep audit (Claude) with the running application as source of truth.
Evidence: live app at `php artisan serve` (sqlite, seeded demo org + fresh signup org), full test-suite runs,
route dump (`artisan route:list --json`), DOM/computed-style measurement on 17 authenticated pages,
public Jumpfactor site research (jumpfactor.net, 5 pages), and `docs/register/feature-register.csv` (1,191 rows).

---

## 1. Executive product assessment

**Where the product stands:** a real, working multi-tenant SaaS with an unusually strong functional core and an
unusually weak visual layer for its maturity. The complete commercial chain — signup → email verification →
organization → team invite → campaign → company → contact → lead → scoring → conversion → pipeline →
closed-won ($4,500 MRR / $54,000 ARR) → analytics → attribution — **runs end-to-end through real HTTP endpoints
with zero breaks** (new permanent test: `tests/Feature/Qa/AcmeJourneyTest.php`, 41 assertions, passing).
Dashboard numbers are **real database aggregates, not mocks** — proven by injecting the Acme journey and watching
funnel/MRR/ARR/source figures change to exactly the injected values.

The platform's weaknesses are concentrated, not diffuse:

1. **Visualization deficit** — module dashboards (Marketing, SEO, Ads, Content, Sales) show KPI tiles with
   **zero charts**. Only 5 of 17 measured pages render any chart.
2. **White-on-white shell** — `body` and `main` are both `rgb(255,255,255)` on every measured page; cards float
   on the same white. This, not text walls, is why pages read as "documentation" (measured `longParas = 0`
   everywhere — the copy is fine; the ground has no depth).
3. **One shared layout defect** — the app shell's `main` (flex-1 without `min-w-0`) lets wide content stretch
   the document: `/crm/deals` measures scrollWidth 2076 vs viewport 1052; `/sales` 1354 vs 1052.
4. **Register truth**: 1,191 features → 700 Tested (59%), 307 Partially Implemented (26%), 172 Planned (14%),
   11 Implemented-untested, 1 N/A. Weighted completeness ≈ **70%**. The bottom quartile (Funnels 17%,
   Local SEO 18%, Competitive Intel 15%, ABM 21%, Buyer Intent 31%) is where Jumpfactor's public positioning
   is strongest.

**Verdict: BASELINE COMPLETE.** The foundation is commercially sound (tenancy, RBAC, billing/entitlements,
audit logs, throttling, 2FA, 741 passing backend tests). The gap to "sellable against Jumpfactor's story" is
(a) the visual/BI layer and (b) four thin modules that happen to be the competitor's loudest claims.

---

## 2. Application feature tree

Computed from the authoritative register (weights: Tested 1.0, Implemented 0.75, Partial 0.4, Planned 0).

```text
PIOTRACK                                          ~70% weighted
│
├── Platform foundation
│   ├── Identity & Authentication          100%   11/11 Tested (2FA, sessions, reset)
│   ├── Tenant & Organization Mgmt         100%   10/10 Tested
│   ├── Roles & Permissions                100%   6/6 Tested
│   ├── AI Platform Infrastructure         100%   gateway, providers, console
│   ├── Audit Logging                       83%
│   ├── Platform Administration             83%
│   ├── Feature Entitlements                71%
│   ├── Billing & Subscriptions             63%   Stripe checkout NEVER live-verified
│   ├── Notification System                 40%
│   ├── Background Jobs & Queues            25%   Horizon not provisioned
│   └── Backups & DR                        25%   no restore drill
│
├── Sales & CRM
│   ├── CRM                                 83%   journey-proven end to end
│   ├── Sales Enablement                    74%
│   ├── Lead Guarantee model                73%
│   ├── Appointment Booking                 35%   in-chat booking works; calendar sync missing
│   ├── Lead Scoring                        42%
│   ├── Buyer Intent Intelligence           31%   ← Jumpfactor's loudest public claim
│   ├── Sales Alerts                        19%
│   └── ABM                                 21%
│
├── Marketing
│   ├── Service-Specific Campaigns         100%
│   ├── Content Marketing                   90%
│   ├── Marketing Automation                83%
│   ├── Email Marketing                     65%   sending unverified (no SMTP creds)
│   ├── Social                              63%
│   ├── Omnichannel                         56%
│   ├── Lead Generation                     35%
│   ├── Funnel Management                   17%   ← core to E4-style methodology
│   └── Marketing Strategy & Research       28%
│
├── SEO & AI visibility
│   ├── MSP Growth Score                   100%
│   ├── AEO                                 74%
│   ├── GEO                                 62%
│   ├── AI Visibility Dashboard             65%   dashboards exist; live LLM citation checks partial
│   ├── Technical SEO                       41%
│   ├── MSP Keyword SEO                     37%
│   ├── LLM Optimization                    28%
│   └── Local SEO                           18%   ← blocked partly on outbound integrations
│
├── Website & Chat
│   ├── Website Chat                        98%   45/46 Tested (booking + AI + summaries)
│   ├── MSP Website Platform                55%
│   └── CRO                                 59%
│
├── Analytics & intelligence
│   ├── Analytics Dashboard                 69%   real DB aggregates (proven)
│   ├── Revenue Attribution                 65%   first/last/multi-touch journeys work
│   ├── Call Tracking                       73%
│   ├── Retargeting                         47%
│   └── Competitive Intelligence            15%
│
└── Operations
    ├── Project Management                  88%
    ├── Client Portal                       72%
    ├── Customer Onboarding                 50%   checklist live on dashboard (observed 3/5)
    ├── Import / Export                     25%   contacts only
    └── Support System                       0%
```

Full 75-module table: run `python` over `docs/register/feature-register.csv` (this audit's numbers were
computed from it; the CSV remains authoritative).

## 3. Feature traceability register

Already exists and is authoritative: `docs/register/feature-register.csv` — **1,191 rows, 75 modules**.
Status distribution verified this audit: 700 Tested · 307 Partially Implemented · 172 Planned ·
11 Implemented · 1 Not Applicable. Statuses were **not** bulk-changed by this audit; the audit added
route-level traceability (§4) and one journey test as new evidence. Suite evidence: **741 Pest tests
(2,961 assertions) + 36 Vitest tests, all passing** at audit time.

## 4. Route register

**393 routes** inventoried → `docs/audit/route-register.csv` (id, method, uri, name, module, access,
permission, verified_by). Summary:

| Module | Routes | Notes |
|---|---:|---|
| Settings | 46 | profile, members, teams, 2FA, tokens, integrations, files, audit |
| Marketing | 35 | lists, forms, landing pages, campaigns, automation, funnels |
| CRM / Content | 28 + 28 | full CRUD + import/export/convert |
| Sales | 27 | scoring, alerts, booking, enablement, intent, accounts |
| Analytics / SEO | 21 + 21 | attribution, growth score, experiments, competitors, calls |
| Website Chat | 20 | all 20 covered by feature tests |
| AI / Ads / Strategy | 19 + 18 + 17 | |
| Website builder | 15 | |
| Projects / Billing / Platform | 11 + 10 + 10 | all 10 billing routes test-covered |
| Public surface | 33 | widget API (throttled), forms, booking, tracking, pages, webhooks, health |
| Auth | 16 | all throttled |

Spot-verified live: unauthenticated `GET /ads`, `/crm/contacts`, `/platform` → **302 to /login** (auth
enforced; an apparent "public /ads" was an artifact of middleware JSON parsing, disproven by direct request).
86 authenticated pages GET-verified by `MenuSmokeTest` (platform pages assert 403 for non-platform users).

## 5. Functional test results (Rule 35 journey + suite)

**Acme Managed IT Services journey — every rung passed:**

| # | Step | Result | Evidence |
|--:|---|---|---|
| 1 | Register Daniel Carter (HTTP POST /register) | PASS | user row created; also verified in-browser with email-verification screen |
| 2 | Email verification | PASS | signed URL from mail log → verified |
| 3 | Create organization | PASS | org + owner membership |
| 4 | Plan activation (enterprise) | PASS (test-mode) | Stripe checkout itself remains UNVERIFIED live |
| 5 | Invite Sarah Mitchell (sales_representative) → accept via emailed token | PASS | seat limits enforced by entitlements |
| 6 | Campaign "Philadelphia CMMC Growth" | PASS | tenant-scoped |
| 7 | Company "Precision Manufacturing Group" | PASS | |
| 8 | Contact Michael Rodriguez (CFO) linked to company | PASS | |
| 9 | Lead created, owner = Sarah | PASS | |
| 10 | Scoring rule (title contains CFO = 40) + recompute | PASS | contact lead_score ≥ 40 |
| 11 | Convert lead → contact/company/deal | PASS | audit-logged |
| 12 | Deal $54,000 value, $4,500 MRR, 12-month term | PASS | ARR auto-annualised to $54,000 (cents-exact) |
| 13 | Walk pipeline stages → Closed Won | PASS | status won, closed_at set |
| 14 | Analytics dashboard | PASS | funnel leads 1 / closed-won 1 / MRR 450000¢ / ARR 5400000¢ / source Website — **live DB values** |
| 15 | Revenue attribution | PASS | campaign credited $54,000; channel Website; first/last/multi-touch journey for Michael |

**No break found.** Known functional defects recorded elsewhere: document-level horizontal overflow
(§9), `/chat/inbox` is not a route (inbox lives at `/chat`; a stale link produced one console 404).

## 6. Jumpfactor competitive gap matrix

Public evidence gathered 2026-08-26 from jumpfactor.net (homepage, /msp-lead-generation, /msp-aeo-geo-services,
/e4/, /msp-directory/). Jumpfactor is an **agency**, not a SaaS — its "product" is services + proprietary
internal tooling. Their site runs **HubSpot Conversations** for chat/booking (verified earlier this session).

| Capability | Our product (verified) | Jumpfactor public evidence | Gap | Opportunity | Priority |
|---|---|---|---|---|---|
| CRM | Working, tested, tenant-isolated | Not offered publicly (references client CRMs) | We lead | MSP-native CRM as product moat | P1 |
| Website chat + booking + AI | 98% tested, self-hosted widget | HubSpot embed on their own site | We lead | Sell as "HubSpot-class chat included" | P1 |
| Lead generation system | Forms/landing/funnels partial (17–35%) | E4 framework, "5–50+ leads/mo", 36K leads claim, guarantee ("you'll see ROI or you don't pay") | High | Funnel builder + lead guarantee tracking (we have Lead Guarantee 73%) | **P0** |
| Real-time visitor ID / intent | Buyer Intent 31% | **Publicly promoted** ("real-time visitor ID", intent-data challenge) | High | Visitor intelligence on first-party data | **P0** |
| AEO/GEO/LLM optimization | Dashboards 62–74%, checks partial | Flagship service: "cited by ChatGPT, Gemini, Perplexity, Copilot, AI Overviews", tracked to MRR | High | **Measurable** AI visibility (scheduled citation checks, evidence links) — as software, self-serve | **P0** |
| Revenue attribution | Working multi-touch to MRR (proven) | Claimed ("every campaign tied to revenue") but not a client-facing product | We lead | Surface it as the headline dashboard | P1 |
| SEO services | Keyword/technical/local 18–41% | 1,000+ page-1 rankings claim; core service | High | Rank tracking + technical audits need integrations | P2 |
| Media network / directory | None | 36-city MSP directory + "get matched" flow | Medium | Marketplace/directory is a long play | P3 |
| Omnipresence / retargeting | 47–56% | "Triple omnipresence", proprietary media network | Medium | | P2 |
| Transparent pricing / self-service | Plans + checkout + entitlements built | Request-pricing only | We lead | Publish pricing; instant onboarding | P1 |
| Multi-tenant SaaS platform | Verified core | N/A (agency) | We lead | The whole category difference | — |

## 7. Competitive scoreboard (0–100)

Ours scored from this audit's evidence; Jumpfactor scored **only on public evidence** (services & claims,
not internal tooling, which is UNKNOWN).

| Category | Our app | Jumpfactor public | Position (Rule 25) |
|---|---:|---:|---|
| Lead generation machinery | 45 | 85 (service claims + guarantee) | BEHIND |
| CRM (as product) | 80 | 10 (not offered) | DIFFERENTIATED |
| Marketing automation | 70 | 55 | AHEAD |
| SEO tooling | 40 | 80 (service) | BEHIND |
| AI visibility (AEO/GEO/LLM) | 50 | 80 (flagship service) | BEHIND |
| Buyer intent / visitor ID | 25 | 70 (publicly promoted) | BEHIND |
| Website conversion (chat/forms/booking) | 75 | 65 (HubSpot-powered) | AHEAD |
| Analytics | 60 | UNKNOWN (internal) | AHEAD (as client-facing product) |
| Revenue attribution | 70 | UNKNOWN (claimed internally) | AHEAD (as product) |
| Chat / conversations | 85 | 60 (HubSpot embed) | AHEAD |
| UX / visual quality | 55 | n/a (no app to compare) | — |
| SaaS platform (tenancy, billing, RBAC) | 80 | n/a | DIFFERENTIATED |

## 8. UI/UX scoreboard

Method: DOM + computed-style measurement on 17 pages (hidden-pane session; visual judgment supplemented by
screenshots taken earlier in this session). 12-criteria scores are per-module medians.

**PRODUCT UI SCORE: 56 / 100**

| Dimension | Score | Evidence |
|---|---:|---|
| Typography | 65 | One family (Instrument Sans), consistent 24px h1 / 16px body; but h1 **absent** on 6/17 pages |
| Visual hierarchy | 55 | PageHeader pattern good where used; missing h1 pages flatten |
| Color system | 45 | Tokens exist (shadcn), but body+main both pure white on every page; status color scarce in lists |
| Icon consistency | 75 | lucide-react only; no emoji in app UI |
| Dashboards | 45 | Main: 8 KPIs + 3 charts + onboarding checklist. Module dashboards: KPI tiles, **0 charts** |
| Data visualization | 30 | Charts on 5/17 pages; Marketing/SEO/Ads/Content/Sales have none |
| Tables | 55 | Functional; contacts = 19 rows + search, but no visible filters/sort/bulk/status pills |
| Forms | 70 | Consistent, validated, accessible labels |
| Navigation | 55 | Grouped sidebar but **58 links**; ⌘K search exists (underleveraged) |
| Responsive | 60 | Dashboard/analytics clean at 375px; deals + sales stretch the document at any width |
| Empty/loading/error states | 60 | Onboarding checklist, builder empty state, form errors observed; not exhaustively audited |
| Information density | 50 | 16px body + thin dashboards (some <700 chars of content) |

**Per-page UI scores (0–10):** dashboard 6.5 · contacts 5.5 · deals 5 (overflow) · marketing 4.5 · seo 4.5 ·
ads 5 · content 5 · sales 4.5 (overflow) · analytics 6 · attribution 5.5 (no h1) · growth-score 5.5 (no h1) ·
chat inbox 6 · ai 5 · projects 5 (no h1) · strategy 5.5 · billing 4.5 (no h1, no usage viz) · members 5 (no h1).
Nothing reaches the 8/10 target.

## 9. Document-style UI problems

Measured `longParas = 0` on all 17 pages — the "documentation look" is **not** text walls. It is:

| Page | Problem | Should become |
|---|---|---|
| Every page | body **and** main `rgb(255,255,255)` — cards indistinguishable from ground | Neutral page background token (e.g. muted 2–3% tint), elevated `bg-card` surfaces |
| /marketing /seo /ads /content /sales | KPI tiles with no trend, no chart, thin content | KPI + sparkline cards over a trend chart + a "needs attention" list |
| /billing | No h1, no plan-usage visualization, 632 chars | Plan card + usage meters (entitlement service already provides the data) |
| /analytics/attribution, /growth-score, /projects, /settings/members, /seo/ai-visibility | Missing h1 | Standard PageHeader |
| /crm/deals, /sales | Board/content stretches document sideways | `min-w-0` on shell main; board scrolls inside its own container |
| /crm/contacts | Plain rows, no lifecycle/status color, no filters | Data-table pattern: filters, sort, bulk, status pills |

## 10. Data-visualization gap report

Backed by real data already in the DB (no fabrication needed):

- **Line/trend**: MRR & lead trends (deals.closed_at + leads.created_at) → main + sales + marketing dashboards.
- **Funnel**: leads→MQL→SQL→meetings→opps→won (metrics already computed on /analytics) → render as funnel, not tiles.
- **Bar**: channel/campaign performance (attribution props exist) → attribution + marketing.
- **Pipeline value by stage**: deals by stage (kanban data exists) → sales dashboard.
- **Score/progress**: Growth Score (exists), SEO health, AI visibility → score cards with deltas.
- **Donut (sparing)**: lead-source mix (already listed as text on dashboard).
- **Usage meters**: entitlements on /billing.
- Heatmaps: defer (no engagement-by-hour data collected yet — do NOT fake).

## 11. Missing feature report

- **P0 Critical**: funnel management (17%), visitor/buyer intent intelligence (31%), measurable AI-visibility
  citation checks, live Stripe verification, support system (0%).
- **P1 High**: local SEO (18%), competitive intelligence (15%), sales alerts (19%), notifications (40%),
  ABM (21%), lead-gen widgets breadth (35%), import/export beyond contacts (25%).
- **P2 Medium**: technical SEO depth (41%), LLMO depth (28%), retargeting (47%), multi-location (42%),
  strategy/research depth (28%), keyword SEO integrations (37%).
- **P3 Future**: MSP directory/media network, marketplace, training/consulting content (46%), podcast (60%).

## 12. Partial feature report (top offenders by claimed-but-thin)

Marketing Strategy & Research (23 partial rows), MSP Website Platform (15), Local SEO (11), MSP Branding (11),
Reputation (10), Lead Generation (10), Buyer Intent (9), LLMO (9), Technical SEO (8), Analytics (8).
Pattern: UI + storage exist; the *external-data* half (rank data, GBP, ad networks, review sources) is stubbed
pending integrations — which are gated on outbound network policy (see server constraint below).

## 13. Broken feature report

Genuinely broken things found this audit:

1. Document-level horizontal overflow on `/crm/deals` (sw 2076 / vw 1052) and `/sales` (1354 / 1052) — shell
   `main` lacks `min-w-0`; wide children widen the page. (Defect, recorded — not fixed per Rule 37.)
2. Six pages render without any `h1` (listed in §9).
3. One stale `/chat/inbox` link target 404s (inbox route is `/chat`).
4. Nothing else failed: 741/741 backend tests, 36/36 front-end tests, journey test green.

## 14. Commercial SaaS gap report

Verified working: multi-tenancy (103/130 tables org-scoped + isolation tests), org signup + email verification
(live-tested), invitations with seat-limit entitlements, RBAC (8 roles), plans/entitlements central service,
billing pages + 10 billing routes test-covered, audit logging, platform super-admin console (+ AI provider
console with encrypted keys), impersonation, API tokens (sanctum) with throttled API.
**Gaps:** Stripe never verified against live/test Stripe account (P0 before selling); no dunning evidence
beyond PaymentFailed notification; trial mechanics present in plans but not audited; support system absent;
onboarding checklist exists but "choose plan / billing details" steps end in manual flows; no status page/SLA
tooling; DR/backup drill never run (memory + register agree).

## 15. Security baseline

- AuthN: Fortify; 2FA enrol/confirm/recovery tested; `REQUIRE_TWO_FACTOR` env gate (live on production server).
- AuthZ: per-route `can:` middleware (see route CSV `permission` column — spot-verified 302/403 behavior;
  platform routes 403 for tenant users in MenuSmokeTest).
- Tenancy: global scopes + `BelongsToTenant`; dedicated `TenantIsolationTest`; 44 feature-test files assert
  cross-tenant denial (`assertForbidden`/`assertNotFound`).
- Rate limiting: all auth routes (6–20/min), whole public surface (widget API 20–240/min, forms 20/min), API `throttle:api`.
- Headers/CSP: per-request nonce CSP (`SecurityHeaders` + tests), trusted proxies configured.
- Secrets: env/config only; platform AI keys encrypted at rest (`platform_settings` cast) with last-4 hints; no
  credentials in repo (register Security rows 5 Tested / 2 partial / 1 planned).
- Not audited today (recorded): file-upload deep validation, dependency CVE scan, external pen test.

## 16. Performance baseline

Dev machine, `artisan serve` + sqlite + production Vite build (numbers are a *relative* baseline, not prod):
TTFB 329–454 ms · DOMContentLoaded 1.75–2.05 s · document transfer ~68–72 KB (analytics, contacts, dashboard).
Console: 1 stale-link 404, zero JS errors across 17 pages. No large-table dataset exists locally to stress
tables. Production (piotrack.com behind Apache) was verified reachable earlier this session; no load test run.

## 17. Responsive baseline

375px: dashboard + analytics fully contained, sidebar collapses. Document-level overflow on deals/sales at
**every** width (root cause §9). Emulation instability in the hidden browser pane prevented the full 5-breakpoint
matrix; remaining breakpoints should be re-checked visually when the pane is open. No modal/form breakage observed.

## 18. Top 20 product improvements (Impact × Advantage × Customer value ÷ Effort)

1. App-shell surface system: neutral page bg + elevated cards + `min-w-0` overflow fix (small effort, transforms every page and kills 2 defects).
2. Charts on the 5 chartless module dashboards from existing real aggregates.
3. Growth Command Center: merge Growth Score + funnel + attribution + alerts into the Rule-23 layout (data already exists).
4. Measurable AI visibility: scheduled LLM citation checks with evidence, trended — productizes Jumpfactor's flagship claim.
5. Visitor intelligence v1: first-party visitor sessions → company resolution → intent feed into CRM/alerts.
6. Funnel builder completion (17% → sellable): steps, conversion tracking, templates.
7. CRM data-table upgrade: filters, sort, bulk actions, lifecycle pills, saved views.
8. Billing page: plan card + usage meters + invoices; then live Stripe verification end-to-end.
9. PageHeader/h1 consistency (6 pages).
10. Sidebar IA: 58 links → grouped domains with landing pages per group + ⌘K promotion.
11. Notifications: in-app center + daily digest (40% → 80%).
12. Lead-gen guarantee tracker: productize Lead Guarantee (73%) into a client-visible commitments dashboard — nobody else shows this.
13. Local SEO: GBP integration + local rank grid (needs outbound network / API keys).
14. Sales alerts on intent + scoring thresholds (19%).
15. Import/export for companies/deals + scheduled exports.
16. Competitive intelligence v1: competitor keyword/AI-visibility deltas (15%).
17. Support module v1: ticket inbox reusing chat infrastructure (0%).
18. ABM v1: account lists + coordinated campaigns (21%).
19. Public API expansion + docs for PSA/RMM ecosystems.
20. Email deliverability: SMTP/provider config UI + SPF/DKIM checker + send verification.

## 19. Recommended development order

1. **Shell & visualization foundation** (items 1, 2, 9, 10) — everything after inherits the look.
2. **Command Center + CRM table UX** (3, 7) — the daily-use surfaces.
3. **Revenue-facing trust**: billing/Stripe verification + notifications (8, 11).
4. **Competitive spearheads**: AI-visibility measurement (4), visitor intelligence (5), funnels (6), guarantee tracker (12) — the "better than Jumpfactor" quartet; each is independent.
5. **Coverage**: local SEO, alerts, import/export, competitive intel (13–16).
6. **New surface area**: support, ABM, API, deliverability (17–20).
Dependencies: 13 and parts of 4 need outbound network/API keys (firewall change request pending on the
production side); 8 needs a Stripe test account; everything in 1–3 is unblocked today.

## 20. Final audit status

**BASELINE COMPLETE.**

Artifacts produced: this report · `docs/audit/route-register.csv` (393 routes) ·
`tests/Feature/Qa/AcmeJourneyTest.php` (permanent Rule-35 regression) · feature-tree/status computations
reproducible from `docs/register/feature-register.csv`.
