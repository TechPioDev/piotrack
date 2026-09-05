# Module Completion Report — Analytics Dashboard (web analytics) + Privacy & Data Management

**Phase 35 · 2026-09-05 · Register: ANLY-001..008, ANLY-013, ANLY-014, PRIV-002, PRIV-006 → Tested; ANLY-012 note refreshed (stays Planned)**

## Scope

Two closeouts sharing the first-party pixel as their backbone:

1. **Analytics Dashboard — traffic metrics (ANLY-001..008) + organic SEO metrics (ANLY-013/014).**
   The old blocker note said sessions/users need GA4. That predates the VINT first-party pixel
   (Phase 27 shipped click/scroll capture; the visitor row has carried `visits`, `page_views`,
   and first-touch UTM fields since Stage 6). GA4 is optional enrichment, not a prerequisite.
2. **Privacy — cookie consent banner (PRIV-002) + bounce/complaint handling (PRIV-006).**
   The `cookie_preferences` table existed "ahead of the need"; the pixel *is* the need. And
   bounce/complaint handling needed an inbound seam, not a live ESP contract.

## What shipped

### First-party web analytics (`AnalyticsService::web()`)

- **Sessions** = SUM(visitors.visits) — the tracker's own 30-minute-window counter;
  **users** = visitor count; **pageviews** = SUM(page_views).
- **Channel split** over `ChannelClassifier::CHANNELS` (organic / paid / social / content /
  referral / direct): per-channel visitors, sessions, pageviews, and identified leads
  (visitors linked to contacts).
- **Organic performance** (ANLY-013/014): organic sessions/visitors/leads plus customers and
  won revenue joined first-touch → contact → won deals.
- Dashboard section "Website analytics": three stat cards, channel table, organic card with an
  honest GSC note (external click volume = INTG enrichment).

### Consent-gated pixel (PRIV-002)

- `resources/views/public/consent-pixel.blade.php` — nonce'd inline gate included by both public
  blades (site pages + landing layout) in place of the raw `<script src>` tag. Reads `pt_consent`:
  granted → inject pixel; denied → nothing; undecided → banner ("Essential only" / "Allow").
- Fully client-side, so cached/ETag'd output never varies by cookie — the P23 performance-budget
  test still passes untouched.
- Decision beacons to `POST t/{key}/consent` → durable `cookie_preferences` row (necessary always
  true, analytics as chosen, marketing false, visitor token derived, `decided_at` stamped).
  Unknown key 404s; bad payload 422s. `pt_consent` added to the cookie-encryption except list.

### ESP bounce/complaint webhook (PRIV-006)

- `POST webhooks/email` (`EmailProviderWebhookController`), registered **before** the billing
  `webhooks/{provider}` wildcard. Any ESP that can POST `{type: bounce|complaint, email}` with
  an `X-Webhook-Secret` header plugs in.
- Shared secret from `EMAIL_WEBHOOK_SECRET`; **unset ⇒ every request refused (403)** — never an
  open suppression-injection door. `hash_equals` comparison.
- On a valid event: every tenant that has messaged the address (OutboundMessage +
  CampaignRecipient, cross-tenant by design) gets a suppression (`channel=email`, reason=type)
  and its sent recipient rows marked `bounced`. The dispatch pipeline already honors
  suppressions, so a re-send reaches nobody — asserted in the test.

## Gate results

| Check | Result |
| --- | --- |
| `vendor/bin/pint --test` | pass (after auto-fix) |
| `phpstan analyse` (1G) | 0 errors |
| `npm run format:check` / `npx eslint .` / `npm run types` | pass |
| `npm run build` | pass |
| `npm run test` (Vitest) | 55 passed |
| `php vendor/bin/pest` | **989 passed, 4,814 assertions** |

New: `tests/Feature/Qa/AnalyticsPrivacyCloseoutTest.php` (3 tests) — web metrics + channel split
+ organic conversions + dashboard prop; consent gate markup (no hard-coded pixel tag) + durable
preference + 422/404 edges; webhook 403 (missing/wrong/unconfigured secret), cross-tenant
suppression, recipient marking, and the suppressed re-send reaching zero recipients.

## Honesty notes

- **ANLY-012 (map rankings)** stays Planned: local-pack positions need a Google Business
  Profile / local SERP provider; no first-party source exists. It no longer shares a blocker
  note with ANLY-001..008.
- GA4/GSC remain available as *enrichment* through INTG — the register notes say so explicitly;
  nothing claims parity with Google's numbers.
- The webhook is a tested seam; pointing a live ESP at it is deploy-time config
  (`EMAIL_WEBHOOK_SECRET` + provider dashboard), recorded as such.

## Register effect

12 rows → Tested, 1 note refresh. Analytics Dashboard **35/36 (97.2%)**, Privacy & Data
Management **6/6 (100%)**. Global: **1,028/1,190 buildable Tested (86.4%)**, 30/75 modules
complete.
