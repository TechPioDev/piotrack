# Module 11 Completion Report — Product Marketing Site (MSITE)

**Date:** 2026-09-01 · **Spec:** docs/module-spec-marketing-site.md · **Status:** Complete

## What shipped

Six public, SEO-ready product pages sharing the landing page's `.lp` design system:

| Page | Route | JSON-LD |
| --- | --- | --- |
| Features | `/features` | — |
| How it works | `/how-it-works` | — |
| Results | `/results` | — |
| About | `/about` | Organization |
| Contact | `/contact` | ContactPage |
| FAQ | `/faq` | FAQPage (10 Q&As) |

- **Server-side SEO**: the app has no SSR, so `<title>`, meta description, canonical link and
  JSON-LD render through Blade view data (`app.blade.php`) — present in the initial HTML for
  crawlers, asserted by tests. Homepage got the same treatment.
- **Shared marketing shell**: `resources/js/marketing/` — `styles.ts` (the `.lp` stylesheet,
  extracted from welcome.tsx, +subpage styles), `marketing-layout.tsx` (nav + theme toggle +
  footer), `site-footer.tsx`, `newsletter-form.tsx`. welcome.tsx dropped from 1,633 to ~640 lines
  with no behavior change; its nav now links the new pages.
- **Contact backend**: `contact_messages` table (platform-level), `POST /contact`
  (throttle:10,1 + honeypot + validation + platform audit `contact.message_received`).
- **FAQ single source of truth**: `MarketingSiteController::faqItems()` feeds both the rendered
  accordions and the FAQPage JSON-LD — they cannot drift.
- **Discovery**: `GET /sitemap.xml` (7 public pages) + robots.txt (Sitemap pointer, app surfaces
  disallowed).

## Honesty constraints upheld

No invented testimonials, case-study numbers, team bios or office address. `/results` presents
what the platform measurably proves and says outright that client testimonials will appear when
real ones exist. FAQ claims (14-day Growth trial, plan prices from $49, engine live/simulated
labeling, CSV import/export, tenant isolation) were verified against `PlanCatalog` and shipped
module behavior before writing.

## Gate results

| Check | Result |
| --- | --- |
| pint --test | pass |
| phpstan (1G) | pass — 0 errors (fixed 3 pre-existing: unused `$provider` promotions in AiVisibilityService/AiVisibilityDashboard, redundant `?? []` in AnswerAnalyzer) |
| prettier format:check | pass |
| eslint | pass |
| tsc (npm run types) | pass |
| Vitest | 43 passed |
| Pest | **818 passed / 3,444 assertions** (+13 tests: `tests/Feature/Qa/MarketingSiteTest.php`) |

## Live verification (local, piotrack dev server)

All six pages + sitemap rendered with correct server-side titles; zero console errors. Contact
form driven end-to-end in the browser: submitted → success state rendered → row stored in
`contact_messages` → platform audit entry written (test row cleaned). Homepage re-verified after
the footer/nav refactor.

## Register

No feature-register status changes: the marketing site is a product-website deliverable, not a
register feature row (same treatment as the Module 10c newsletter). Register remains
740 Tested / 295 Partial / 147 Planned + 9 N/A of 1,191 rows.

## Follow-ups

- Surface `contact_messages` and `newsletter_subscribers` in the platform console (list + CSV).
- SMTP forwarding of contact messages when a mail account exists.
- Real client testimonials on `/results` with Review schema when they exist.
- robots.txt hard-codes the public origin (`https://piotrack.com:8443`) — revisit when the site
  moves to standard 443.
