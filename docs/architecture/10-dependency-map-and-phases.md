# 10 — Dependency Map & Development Phases

## Foundation dependency chain (Master Prompt §56)

```
DEVX (repo, CI, envs, test framework)
  → AUTH → TEN → RBAC (+AUDIT from here on)
    → BILL → ENTL/USAGE → ONBD
      → DSGN + core shell (NOTIF, SRCH, FILE, INTG framework, API standards, JOBS, OBS)
        → CRM
          → LEAD/AUTO/EMAIL/SMS (marketing execution)
            → SEO/AI-search/Ads/Content (acquisition modules)
              → LSCR/INTENT/ALERT/BOOK/ABM/ENAB (sales modules)
                → ANLY/ATTR/BENCH/CINT/GSCORE (analytics)
                  → AISA/AIVIS + AI features (AIPF underneath)
                    → ADMIN/PORTAL/PROJ/SUPP
                      → hardening & production readiness
```

Cross-cutting concerns (MLOC, VERT, SVC, PERF, METH) attach to the modules they qualify —
e.g. MLOC extends LSEO/ANLY; VERT/SVC extend CONT/campaign targeting; PERF extends ANLY/reporting.

## Stages (each stage = one or more module gates; §57 refined)

| Stage | Scope                                                                                                                                                                             | Register codes                                               |
| ----- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| 0     | Project foundation: repo, standards, environments, DB, CI, tests, logging, error framework                                                                                        | DEVX                                                         |
| 1     | Identity: registration, verification, login, reset, sessions, MFA-ready                                                                                                           | AUTH                                                         |
| 2     | Tenancy: organizations, memberships, invitations, teams, RBAC, audit logging                                                                                                      | TEN, RBAC, AUDIT                                             |
| 3     | Commercial: plans, checkout, subscriptions, invoices, billing portal, entitlements, usage                                                                                         | BILL, ENTL                                                   |
| 4     | Core platform: app shell/design system, navigation, dashboard framework, notifications, global search, settings, files, integration framework, API standards, jobs, observability | DSGN, NOTIF, SRCH, FILE, INTG, API, JOBS, OBS, ONBD          |
| 5     | CRM (full module)                                                                                                                                                                 | CRM, IMEX (CRM scope)                                        |
| 6     | Marketing platform: campaigns, leads, forms, landing pages, automation, email, SMS                                                                                                | LEAD, AUTO, EMAIL, SMS, FUNL                                 |
| 7     | SEO & search intelligence                                                                                                                                                         | TSEO, KSEO, LSEO, AEO, GEO, LLMO                             |
| 8     | Advertising                                                                                                                                                                       | PPC, LIAD, META, RETG                                        |
| 9     | Content & authority                                                                                                                                                               | CONT, SOC, VID, POD, REP, DPR, LINK                          |
| 10    | Sales: scoring, intent, alerts, booking, enablement, ABM                                                                                                                          | LSCR, INTENT, ALERT, BOOK, ENAB, ABM                         |
| 11    | Analytics: dashboards, attribution, revenue, benchmarks, growth score, competitive intel, omnichannel views                                                                       | ANLY, ATTR, CALL, CRO, BENCH, CINT, GSCORE, OMNI             |
| 12    | AI: sales agent, AI scoring, AI visibility, recommendations                                                                                                                       | AISA, AIVIS, AIPF                                            |
| 13    | Administration & service delivery: super admin, client portal, projects, support, strategy/brand/training work surfaces                                                           | ADMIN, PORTAL, PROJ, SUPP, TRAIN, STRAT*, BRAND*, METH, PERF |
| 14    | Production hardening: security, performance, accessibility, DR, monitoring, load tests, readiness audit (§62–63)                                                                  | SEC, BCK, PRIV + regression                                  |

\* STRAT/BRAND software surfaces (audits, roadmaps, asset libraries) may land earlier where a stage
needs them; their service-delivery workflows complete in Stage 13. WEB/MLOC/VERT/SVC distribute across
Stages 6–11 per module specs. The Feature Register determines exact scope per stage.

## Working rules

- A stage begins with module specs ([template](../module-spec-template.md)) and ends with Module
  Completion Reports + register updates. No stage starts with the previous stage's blocking tests red.
- Each development cycle reports: CURRENT MODULE / FEATURES INCLUDED / DEPENDENCIES /
  IMPLEMENTATION PLAN, then results per Master Prompt §65.
