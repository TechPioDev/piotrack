# 01 — Product Definition & Module Map

## Product

**Piotrack — MSP Growth OS.** A commercial, multi-tenant SaaS platform for MSP-focused growth:
marketing execution connected to sales operations, proved by qualified pipeline, MRR, revenue and ROI.
Each customer organization (an MSP, or an agency serving MSPs) operates in an isolated tenant with
its own users, CRM data, campaigns, integrations and billing.

Competitive positioning (from the Feature Inventory):

1. Transparent packaged tiers and pricing.
2. Full-funnel revenue attribution — keyword/campaign through closed MRR, not traffic and clicks.
3. AI visibility measurement across ChatGPT, Gemini, Perplexity, Copilot and Google AI experiences.
4. AI-assisted sales qualification, follow-up, CRM hygiene and appointment booking (human oversight).
5. MSP-specific benchmark intelligence (CPL, conversion, pipeline, sales velocity).
6. One unified dashboard: traffic → leads → SQLs → meetings → pipeline → MRR → ROI.

## Module groups

The inventory's 12 capability groups organize the 50 product modules. Codes refer to the
[Feature Register](../register/FEATURE_REGISTER.md).

| Group                 | Modules (codes)                                                                                                                        |
| --------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| 1. Strategy           | STRAT                                                                                                                                  |
| 2. Brand              | BRAND                                                                                                                                  |
| 3. Website            | WEB, CRO                                                                                                                               |
| 4. Search             | TSEO, KSEO, LSEO                                                                                                                       |
| 5. AI Search          | AEO, GEO, LLMO, AIVIS                                                                                                                  |
| 6. Authority          | CONT, SOC, VID, POD, REP, DPR, LINK                                                                                                    |
| 7. Paid Media         | PPC, LIAD, META, RETG                                                                                                                  |
| 8. Pipeline           | LEAD, LSCR, INTENT, ALERT, BOOK, ABM, FUNL                                                                                             |
| 9. CRM                | CRM, ATTR (record-level), CALL                                                                                                         |
| 10. Automation        | AUTO, EMAIL, SMS, AISA                                                                                                                 |
| 11. Sales             | ENAB, PROJ, TRAIN, PERF, METH                                                                                                          |
| 12. Analytics         | ANLY, ATTR (reporting), BENCH, CINT, GSCORE, OMNI                                                                                      |
| Cross-cutting product | PORTAL, MLOC, VERT, SVC                                                                                                                |
| Platform foundation   | AUTH, TEN, RBAC, ONBD, BILL, ENTL, DSGN, NOTIF, SRCH, IMEX, AUDIT, SEC, API, JOBS, OBS, BCK, PRIV, SUPP, ADMIN, INTG, AIPF, FILE, DEVX |

## Module ≠ page (Master Prompt §15)

Every module above is a complete business capability: entities, APIs, UI, permissions, imports/exports,
background jobs, notifications, audit events, reports and tests. The
[module spec template](../module-spec-template.md) must be completed before implementation of each module.

## Software vs. service-delivery capabilities

The inventory blends pure software features (rank tracking, workflows, dashboards) with
agency service deliverables (brand discovery, podcast production, consulting). Platform treatment:

- **Software features** — implemented as product functionality.
- **Service-delivery features** — supported through the platform's work-management surface
  (projects, deliverables, approvals, client portal) so a provider team can deliver them to tenants.
  These stay in the register and are resolved per-feature at module-spec time; anything genuinely
  out of software scope is marked `Not Applicable` with justification (never silently dropped).
