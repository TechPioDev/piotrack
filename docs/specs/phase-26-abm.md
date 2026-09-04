# Phase 26 — ABM (Account-Based Marketing) close-out

Register target (6 rows): ABM-007 (org-chart mapping), ABM-011 (account-specific
content), ABM-012 (LinkedIn ABM), ABM-014 (account-based retargeting), ABM-016
(executive outreach), ABM-019 (sales & marketing orchestration).

Stays honest (2 rows): ABM-004 (company enrichment — needs a Clearbit-class data
provider; the platform must not fabricate firmographics), ABM-017 (video outreach —
needs a video recording/hosting provider).

## Design

**Org-chart mapping (ABM-007)** — the deferral said "reporting lines stay external",
but reporting lines are CRM data a rep captures in one click; enrichment providers
merely auto-fill them. `contacts.reports_to_contact_id` (same-company, self-refused) +
`AccountService::orgChart()` renders the committee as a reporting forest (cycle-safe),
on the account report beside the buying roles that already exist.

**Account-specific content (ABM-011)** — `content_pieces.company_id` targets a piece at
one account's company; the account report lists the account's content, and an
attach endpoint binds an existing piece to the account. Personalized landing pages
(ABM-010, done) remain the page side; this is the content side.

**LinkedIn ABM (ABM-012)** — LinkedIn Campaign Manager accepts a *company list* CSV
upload (`companyname,companywebsite`) for account targeting — no API needed.
`AccountService::linkedinCompanyCsv()` exports active target accounts in exactly that
shape (audited). The contact-level side already exists: tier committee lists →
retargeting audience → the Phase 16 LinkedIn hashed-email CSV. API audience sync stays
connector-gated, stated in the note.

**Account-based retargeting (ABM-014)** — the bridge existed on both sides and just
needed connecting: `syncTierList()` (ABM, tested) feeds a MarketingList;
RetargetingAudience `source=list` (Phase 16, tested) resolves members from one.
`AccountService::createRetargetingAudience(tier)` syncs the tier list, creates/reuses
the audience (converted customers excluded), and rebuilds counts — from there the
existing per-platform export CSVs work unchanged.

**Executive outreach + orchestration (ABM-016/019)** — a play-runner
(`AbmPlayRunner`) that orchestrates existing *tested* machinery, each step audited and
reported honestly:

- `executive_outreach`: for every decision-maker on the committee, an AI-drafted intro
  (advisory — **nothing is sent**) attached to a task on the contact's CRM timeline,
  due in 2 days, assigned to the account owner running the play. No decision-makers →
  the play says so and does nothing, instead of inventing targets.
- `account_retargeting`: the ABM-014 flow as a play (tier list synced, audience
  created/rebuilt, member count reported).

Plays return their step log to the UI; the play run is one audit record. This is
orchestration v1 as a *dedicated, named* runner rather than the implicit
alerts+workflows version the register called out.

## Tests (tests/Feature/Qa/AbmCloseoutTest.php)

1. Org chart builds the reporting forest (roots without managers, nested reports,
   cycle-safe); the manager endpoint refuses cross-company and self links.
2. Account content: an attached piece appears on the account report; other companies'
   and other tenants' pieces never do.
3. LinkedIn company CSV: exact header, active accounts only, tier filter, audited.
4. Account-retargeting play: audience built from the tier committee (customers
   excluded), counts correct, idempotent re-run, feeds the existing platform exports.
5. Executive-outreach play: one task per decision-maker carrying the AI draft, honest
   empty-committee behaviour, zero outbound messages.
