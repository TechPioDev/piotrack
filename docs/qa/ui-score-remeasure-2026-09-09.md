# Product UI score — re-measurement (2026-09-09)

Anchor for the UI 100/100 campaign. Method identical to the 26–27 Aug baseline:
computed-style and DOM measurement on the same 17 authenticated pages, seeded demo
org ("Northwind IT Services", demo@piotrack.test), viewport 1052×820 (the baseline's
frame) plus 375×812 mobile spot-checks. Every number is a measurement, not an
impression. Detector for charts: the three shared chart components (`bar-list`,
`line-chart`, `segment-bar`) all render `role="img"` with an aria-label; empty
states carry a `: no data` suffix and are counted separately.

## Per-page raw measurements (1052×820)

| Page | h1 | Overflow px | Charts (data) | Charts (empty) | Tables |
| --- | --- | ---: | ---: | ---: | ---: |
| /dashboard | Dashboard | 0 | 3 | 2 | 0 |
| /analytics | Analytics | 0 | 0 | 0 | 2 |
| /chat | Conversations | 0 | 0 | 0 | 0 |
| /analytics/attribution | Attribution | 0 | 0 | 0 | 4 |
| /analytics/growth-score | MSP Growth Score | 0 | 0 | 0 | 0 |
| /crm/contacts | Contacts | 0 | 0 | 0 | 1 |
| /strategy | Strategy | 0 | 0 | 0 | 6 |
| /ads | Advertising | 0 | 2 | 0 | 1 |
| /content | Content | 0 | 2 | 0 | 1 |
| /crm/deals | Deals | 0 | 0 | 0 | 0 |
| /ai | AI | 0 | 0 | 0 | 0 |
| /projects | Projects | 0 | 0 | 0 | 4 |
| /settings/members | Settings (layout-level) | 0 | 0 | 0 | 0 |
| /marketing | Marketing | 0 | 2 | 0 | 0 |
| /seo | SEO | 0 | 1 | 1 | 0 |
| /sales | Sales | 0 | 2 | 0 | 0 |
| /billing | Settings (layout-level) | 0 | 0 | 0 | 0 |

Cross-cutting measurements:

- **Console errors: 0** across all 17 page loads.
- **Horizontal overflow: 0 px on 17/17** pages at 1052 (baseline: /crm/deals 2076 vs
  1052, /sales 1354 vs 1052). The `min-w-0` shell fix holds.
- **Mobile 375 px: 0 px overflow on /dashboard, /analytics and /crm/deals** — the
  kanban, the baseline's worst offender, is now fully contained.
- **Ground:** body `rgb(246, 249, 248)` (tinted hsl(160,16%,97.1%)) vs white cards —
  the white-on-white defect is gone. Font: Instrument Sans loads.
- **Icons:** zero imports of react-icons / heroicons / fontawesome / radix-icons
  anywhere under resources/js — lucide is the single icon source.
- **Stale link:** the /chat/inbox 404 link no longer exists.
- Deals board renders seeded deals (Qualified $9,600 · Proposal $21,600 · Won
  $19,200) with per-column internal scroll.

## Category scores — baseline → now

Categories re-measured today get new numbers with the evidence above; categories
not re-measured carry the baseline number unchanged (marked *carried*) so the
total never flatters.

| Category | Baseline | Now | Basis |
| --- | ---: | ---: | --- |
| Icon consistency | 75 | 95 | single icon source verified repo-wide |
| Forms | 70 | 70 | *carried* — not re-measured |
| Typography | 65 | 70 | Instrument Sans confirmed loading; scale not audited |
| Responsive | 60 | 95 | 0 px overflow 17/17 @1052 + 3/3 @375 incl. kanban |
| Empty/loading/error states | 60 | 65 | chart empty states designed; rest carried |
| Tables | 55 | 55 | *carried* — not re-measured |
| Navigation | 55 | 65 | stale 404 link gone; ⌘K present; ~60 links remain |
| Visual hierarchy | 55 | 85 | h1 on 17/17 (2 are the settings layout's generic h1) |
| Information density | 50 | 50 | *carried*; 5 dashboards render < 900 chars of content |
| Color system | 45 | 90 | tinted ground + elevated cards measured |
| Dashboards | 45 | 70 | all 6 domain dashboards carry 1–5 chart components |
| Data visualization | 30 | 55 | charts on 6/17 pages (was 5/17); analytics, attribution, growth-score, strategy still chartless with data already in props |

**Score: 72/100** (mean of 12, baseline 56/100).

## Phase plan to 100 (worst-first)

| Phase | Target categories | Work |
| --- | --- | --- |
| UI-P1 | Data viz 55, Dashboards 70, Info density 50 | Charts from data already in props: funnel on /analytics, channel/campaign bars on attribution, pillar segments + trend on growth-score, strategy market bars |
| UI-P2 | Tables 55 | Measure then standardize: sort affordance, lifecycle pills, aligned numerics, row hover, designed empty rows |
| UI-P3 | Empty/error states 65, Forms 70 | Designed empty states on chartless/table pages; form label/validation consistency pass |
| UI-P4 | Navigation 65, Hierarchy 85 | Page-level h1 on members/billing; sidebar group polish; promote ⌘K |
| UI-P5 | Typography 70, Icon 95 | Type-scale audit + fixes; residual icon misuse |
| Final | — | Full re-measure, new score section on the scoreboard artifact (baseline section preserved) |

Each phase ends with the standard gate, a re-measure of its target categories, and
a commit; the score is only updated from measurements.

## UI-P1 — result (2026-09-10)

Charts added exclusively from data the pages already receive; no new queries.

- **/analytics** — the eight funnel stat tiles became a real funnel (`BarList`,
  leads → closed won, per-step "% of leads" hints, closed-lost noted below it);
  the leads-by-channel table became channel bars with share hints.
- **/analytics/attribution** — revenue-by-channel and revenue-by-campaign tables
  became money-formatted bars with share hints, side by side.
- **/analytics/growth-score** — the hand-rolled trend columns became the shared
  `LineChart` (honest empty state until a snapshot is saved).
- **/crm/deals** — a `SegmentBar` of deal value by pipeline stage above the board
  (won green, lost destructive, open stages on the chart cycle), from the stage
  totals the kanban already carries.

Re-measured after `npm run build` at 1052×820: /analytics 2 charts ·
/analytics/attribution 2 charts · /crm/deals 1 chart · /analytics/growth-score
1 chart component (designed empty state) — 0 px overflow and 0 console errors on
all four. **Pages carrying chart components: 10/17** (9 with data + growth-score's
empty state), up from 6/17.

Category movements (measured): Data visualization 55 → 75 (10/17 pages, the
named chartless pages closed); Dashboards 70 → 80 (the analytics dashboard now
leads with a funnel). **Score after UI-P1: 75/100** (mean 74.6; baseline 56,
re-measure 72).

Gate: pint ✓ · phpstan 0 findings (raw grep) ✓ · prettier ✓ · eslint ✓ · tsc ✓ ·
build ✓ · Vitest 55 ✓ · Pest 1,086 / 5,706 assertions ✓.

## UI-P2 — tables (2026-09-14)

**Measured before** (same seeded org, 1052×820, computed styles on every rendered
cell): the CRM lists already carried the full data-table kit (sort headers,
filters, bulk actions, stage pills, saved views, designed empty states), so the gap
sat in the 118 hand-written tables elsewhere. Of 87 rendered numeric cells, **85 were
centered, 1 right-aligned, 10 tabular**; 18 of 22 tables used a sentence-case header
style while the shared primitives used uppercase — two visual systems. Every table
already scrolled inside its own container and had row hover.

**Found along the way — a real rendering bug:** on /website/taxonomy the Phase 39
change placed the Ads / Case studies / Sequences / Accounts / Messaging headers on the
*service-line* table (12 headers over 7 cells) instead of the *vertical* table (8
headers over 13 cells), so vertical counts rendered under no heading at all. Headers
moved to the table that owns those fields; a repo-wide sweep found no other case.

**Changes**

- Numeric columns right-aligned, header and cells together: 120 columns by a
  reviewed codemod that pairs each `<th>` with its body `<td>` and flips only plain
  numeric expressions (badges, ✓ marks, Yes/No, version labels and status pills stay
  centered), plus hand edits for numeric cells with muted "—" fallbacks, the taxonomy
  tables, and the `ui/table` pages (companies, visitors, widgets, keyword positions).
- `SortHeader` gained `align="right"` — its flex button previously ignored the
  header's `text-center`, so numeric sort headers sat left over centered digits.
- Base layer: every `table` uses tabular figures; every `thead th` shares one voice
  (muted, xs, semibold, tracked, uppercase); the 560 per-header `font-medium`
  overrides that fought it were removed.
- `Badge` no longer wraps mid-label ("Page 1" / "Top 3" were breaking onto two lines
  in the keyword position column).
- `resources/js/components/data-tables.test.tsx`: SortHeader alignment and sort
  toggling, plus a sweep of every page source that fails the build when a table's
  header count differs from its body row's cell count — proven by running it against
  the pre-fix taxonomy file (fails on exactly the two broken tables) and the fix
  (passes).

**Measured after** (identical script): numeric cells right-aligned **309 / 309** on
the sampled pages (/strategy 28, /analytics 18, /ads 8, /crm/companies 8,
/website/taxonomy 238, /sales/visitors 2, /analytics/attribution 2,
/marketing/forms 1, /projects 1) and tabular **309 / 309**; keyword positions end
12 px from the cell edge on all 8 rows; header style uniform on every rendered
table; 0 wrapped badges; 0 header/cell mismatches across all page sources; 0 px page
overflow.

Category movement: **Tables 55 → 90.** Held back from 100, stated plainly: sorting
exists only on the CRM lists (the other tables are short computed reports), and long
report tables have no sticky header. **Score after UI-P2: 78/100** (mean 77.5).

Gate: pint ✓ · phpstan 0 ✓ · prettier ✓ · eslint ✓ · tsc ✓ · build ✓ ·
Vitest **60** ✓ · Pest 1,086 / 5,706 ✓.

## UI-P3 — information density (2026-09-14)

The category had been carried from August without a method. Defined here as four
measurements at 1052×820: values visible above the fold, KPI tiles that carry
context (a comparison, share or supporting fact — not just label + number), dead
space (narrow blocks, duplicated numbers), and tile legibility (width, wrapped lines).

**Measured before** on the six domain dashboards (marketing, SEO, ads, content,
sales, AI): **41 KPI tiles, 4 with any context (10%)**. Marketing's six tiles were
bare inventory counts; SEO and ads squeezed seven tiles into a 756 px content area
(98–116 px wide, labels and values wrapping to two lines, 158 px tall); ads kept a
second row of three stat cards pinned to `max-w-md`; sales repeated the lead
temperature numbers already in its bar legend as three extra boxes and put its stat
cards below the charts; the AI totals were all-time numbers placed after two detail
cards. The main dashboard, by contrast, had deltas on 5 of 8 tiles.

**Changes**

- `App\Services\Analytics\PeriodComparison` — the command center's private
  "this window vs the one before" rule, extracted so every dashboard computes a
  delta identically (the command center now delegates to it; its 8 tests pass
  unchanged). Honesty rule kept: only events on the timestamp that records them
  (`sent_at`, `published_at`, `enrolled_at`, `checked_at`, a submission's or
  booking's `created_at`, an ad metric's `date`) get a delta; stocks and ratios
  get a supporting fact instead; a zero previous window is "new", never +∞.
- `StatCard` gained a `hint` context line; `formatDelta`, `countOf` and
  `shareOf` moved into `lib/format` so the six dashboards and the command
  center render context the same way.
- **Marketing:** inventory counts became activity — new contacts, form
  submissions and messages sent (by `sent_at`, with open rate) with 30-day deltas;
  leads as a share of contacts; workflows with enrollments; lists with members.
- **SEO:** audits run and AI checks with deltas; "Avg score" shows "—" with no
  audits instead of a fabricated 0; Top 3 folded into the Page 1 tile's context
  so six tiles fill two even rows.
- **Ads:** seven KPI tiles + the narrow three-card row became six tiles — spend,
  impressions, clicks, conversions and revenue with deltas against the previous
  30 days; CTR, CPC, CPA, ROAS and the campaign/audience counts ride along as
  context, so no number was dropped. The KPI window now matches the trend chart's
  30 days exactly (it was 31). Money on eight pages gained thousands separators.
- **Content:** pieces and posts published (by `published_at`) with deltas; live
  vs total, scheduled posts, placements from prospects, rating with review count and
  positive share ("—" with no reviews).
- **Sales:** tiles moved above the charts — meetings booked with delta and next
  booking date, hot leads as a share of scored contacts, unread alerts with alerts
  raised, target accounts with tier 1; the duplicated temperature boxes removed.
- **AI:** totals moved to the top as the summary — requests with a 30-day delta,
  failure rate, tokens and cost per request.
- Six-tile grids go three-across below `xl`, so tiles never fall under 176 px.

Tests: `tests/Feature/Qa/DashboardContextTest.php` (exact window boundaries on
days 0/29/30/59/60; messages counted by `sent_at` not queue time; ad volumes
window-against-window with ratios never delta'd and the KPI row on the same window;
drafts never counted as published; no fabricated audit score; tenant isolation on
four dashboards' flows) and `resources/js/components/stat-card.test.tsx`
(delta signs, "new" instead of infinity, nothing when both windows are empty,
pluralization, no share without a base, hint rendering).

**Measured after** (same pages, same frame): **26 KPI tiles, 26 with context
(100%)** — fewer boxes carrying more information; minimum tile width 176 px
(was 98); every label and hint on one line; no duplicated numbers; no narrow rows;
0 console errors.

Category movement: **Information density 50 → 80** and **Dashboards 80 → 85**
(summary-first, contextual tiles on all six domain dashboards).
Held back from 100, stated plainly: /analytics still shows 20+ bare tiles over 2.6
screens (including an eight-tile ads block that repeats /ads), and the main
dashboard's onboarding checklist pushes its KPIs below the fold for tenants that
haven't finished setup. **Score after UI-P3: 80/100** (mean 80.4).

Gate: pint ✓ · phpstan 0 ✓ · prettier ✓ · eslint ✓ · tsc ✓ · build ✓ ·
Vitest **67** ✓ · Pest **1,092 / 5,731** ✓.

## UI-P4 — empty, loading and error states (2026-09-14)

Measured on a genuinely empty organization (enterprise plan, no data) plus a
source census of all 127 pages, and a simulated dropped connection in the browser.

**Measured before**

- **Empty:** the designed `EmptyState` (icon, title, guidance, inline action) was
  used on 7 pages; 27 collection pages fell back to one muted sentence; the empty
  deals board printed "No deals" six times with no way forward; 51 empty messages
  were four words or fewer.
- **Loading:** 154 of 156 forms surfaced `processing` (the two CSV import previews
  did not); 6 files ran one-click mutations with no busy state, five of them
  creating records (accept invitation, save growth snapshot, run ABM play, attach
  funnel asset, add link prospect) so a double click duplicated work; the progress
  bar was a flat grey.
- **Errors:** 403/404/500/503 pages, the React error boundary and 419 handling
  already existed — but measuring what reaches the screen found **four silent
  failures**: (1) the expired-session handler flashed under `message`, which the
  client never received, so a submit after a timeout just bounced back; (2) an
  invalid invitation link and (3) checkout of a custom-priced plan flashed `error`,
  also never shared, so both redirected with no explanation; (4) Phase 52's "Draft
  copy" flashed `draft`, never shared, so the AI draft never appeared — its test had
  asserted the session, not the page. And (5) a request that never reached the
  server (dropped Wi-Fi, VPN reconnect, a deploy restarting) did nothing visible at
  all: no navigation, no message, no console output.

**Changes**

- Flash plumbing: `error` and `draft` shared with the client; the 419 handler
  flashes `error`; `FlashMessage` renders errors in the destructive style (error
  first, each dismissible) and the auth layout shows errors, so an expired session on
  the login form is explained too.
- `ConnectionNotice`: handles Inertia's `exception` event (network failures only —
  cancelled visits never fire it, server errors render their page) with "Couldn't
  reach Piotrack — nothing was saved or loaded", clearing on the next success.
- Designed empty states with the page's own create action (same dialog, same
  permission) on 17 more collection pages: ads campaigns and retargeting, content
  pieces and social, the six marketing pages, SEO keywords, and sales accounts,
  alerts, booking, enablement, intent and scoring. The empty deals board is one
  state naming the pipeline's real stages with New deal.
- Busy states on the five creating actions and both import previews ("Reading the
  file…"); the progress bar uses the brand token with a 150 ms delay.
- Precise copy for bare values: "Not measured yet" for scores excluded from the
  growth score and benchmarks; company contacts/deals lines say whose they are.

Tests: four `FlashMessageTest` regressions — expired session, invalid invitation,
custom-priced checkout, draft delivered to the page — **proven by reverting the two
server fixes (5 failures) and restoring them (all pass)**;
`resources/js/components/feedback-states.test.tsx` — error styling and ordering,
the errors-only mode, independent dismissal, the connection notice showing,
preventing Inertia's unseen rejection and clearing on success, and a pin that 21
collection pages keep a designed empty state with an action.

**Measured after:** designed empty states on **25 pages** (was 7), each verified
action opening its dialog in the browser (lists, alerts) and the deals board naming
six stages; forms surfacing busy state **156 / 156**; creating one-click actions
without busy state **0** (the one remaining file is idempotent notification
toggles); the simulated dropped connection now shows the notice; the progress bar
paints `var(--brand)`.

Category movement: **Empty / loading / error states 65 → 90.** Held back, stated
plainly: eight multi-section or form-first pages (audits, schema, AI visibility,
training, LLMO, local, outreach, reputation) keep contextual sentences by design,
and long provider calls (AI drafts) show only a disabled button, no progress detail.
**Score after UI-P4: 83/100** (mean 82.5).

Gate: pint ✓ · phpstan 0 ✓ · prettier ✓ · eslint ✓ · tsc ✓ · build ✓ ·
Vitest **75** ✓ · Pest **1,096 / 5,782** ✓.

## UI-P5 — navigation (2026-09-15)

Measured with the demo org at 1052×820 by visiting every one of the 65 sidebar
destinations in turn (clicking the real sidebar links), reading the active item,
the header breadcrumb, the page `h1` and the tab title; plus the search endpoint
queried with six page names, and the collapsed icon rail.

**Measured before**

- Active highlight correct on **65 / 65** pages (the longest-match rule held).
- **58 / 65** headers named only the page, never its section — and six collided:
  "Campaigns" (marketing and ads), "AI Visibility" (SEO and AI), "Content" (the
  content overview and the content list). The same three pairs shared an identical
  `h1` and browser-tab title.
- Seven links were named "Dashboard"; **12 icons were shared by two links each**, so
  the collapsed icon rail (icons plus a bare-title tooltip) had 24 ambiguous targets.
- Search found **0 of 6** page names ("keywords", "billing", "attribution",
  "members", "deals", "booking"): it covered records only. Results had no arrow-key
  navigation, and the shortcut hint read "⌘K" on Windows.
- Settings, members and billing were reachable only through the avatar menu.
- No skip link: a keyboard user tabbed through 65 sidebar links to reach a page.

**Changes**

- `resources/js/lib/navigation.ts` — one definition of every destination (section,
  name, icon, permission, search words). The sidebar, the settings menu, the header
  breadcrumb and the command palette all read it, so names and permission gates
  cannot drift between them.
- Every sidebar link has its own icon; section dashboards are "Overview", and the
  icon-rail tooltip names the section ("SEO · Overview").
- Section breadcrumbs derived in the header for every page — "Advertising › Ad
  campaigns", "CRM › Contacts › Ann Lee", "Settings › Members" — with a repeated
  section word dropped ("AI Agent" → "AI › Agent").
- Distinct names for the three colliding pages: "Ad campaigns"; "Content library"
  (sidebar "Library"); and, for the two AI visibility pages, "AI answer checks" (SEO:
  run a prompt, act on cited sources) and "AI visibility report" (AI: prompt library,
  trends, competitors) — each page now links to the other.
- Command palette: pages matched instantly by name, section or the words people use
  ("pipeline" → Deals, "invoices" → Billing, "seo key" → Keywords), only pages the
  user may open; arrow keys move through pages and records, Enter opens; the hint
  shows Ctrl K on Windows and ⌘K on Apple devices.
- "Skip to content" link as the first tab stop, focusing the main region.

Tests: `resources/js/components/navigation.test.tsx` — unique icons, one link per
page, names repeated only across sections, permission filtering, page search
including word-start matching ("ads" never finds Leads), section breadcrumbs, a
source sweep that fails when two pages would show the same header (**proven by
restoring the old "Content" title: it fails on exactly `/content` vs
`/content/pieces`**), and the palette driven by keyboard alone. The existing nine
sidebar tests pass unchanged.

**Measured after** (same 65-page pass): active highlight **65 / 65**; section named in
**54 / 65** headers, the other 11 being the section overviews themselves plus
Dashboard and Portal; duplicate headers, `h1`s and tab titles **0** (was 6, 6, 6);
duplicate icons **0** (was 12 pairs); icon-rail tooltip "SEO · Overview"; page names
found by search **6 / 6** — live, Ctrl K → "keywords" → Enter landed on /seo/keywords
with header "SEO › Keywords"; skip link is the first tab stop and moves focus to
`main-content`; at 375 px the longest trail ("Delivery › Strategy › Research") wraps
inside the 64 px header with 0 px overflow.

Two things the preview could not show, stated plainly: the skip link's focused state
(the preview window never holds OS focus, so `:focus` styles cannot apply there —
the compiled rule and the off-screen resting position were verified instead), and
Escape closing the palette (a synthetic key event did not reach the unchanged Radix
dialog). Reading the compiled CSS caught a real bug in the first skip-link version
(`focus:not-sr-only` zeroing its padding), replaced with an off-screen/slide-in
pattern.

Category movement: **Navigation 65 → 88.** Held back: the sidebar still holds 65
links in 13 groups with one section open at a time, so reaching another section by
mouse is two clicks, and the palette does not yet offer recently visited pages.
**Score after UI-P5: 84/100** (mean 84.4).

Gate: pint ✓ · phpstan 0 ✓ · prettier ✓ · eslint ✓ · tsc ✓ · build ✓ ·
Vitest **90** ✓ · Pest **1,096 / 5,782** ✓.
