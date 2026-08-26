# 09 — Recommended Technology Architecture (ADR-0001)

**Status: Accepted — confirmed by owner 2026-08-12.**

## Recommendation: modular Laravel monolith + React

| Layer         | Choice                                                                                      | Rationale                                                                                                                                                                  |
| ------------- | ------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Backend       | PHP 8.3+, Laravel 11/12, modular domain structure (`app/Domains/...`)                       | Mature SaaS batteries: auth, policies, queues, notifications, migrations. Matches the owner's existing PHP ecosystem (osTicket, portal work) for long-term maintainability |
| Frontend      | Inertia.js + React + TypeScript + Tailwind CSS                                              | SPA-quality UX with monolith simplicity; design system as a shared component library                                                                                       |
| Database      | PostgreSQL 16                                                                               | Row-level tenancy at scale, JSONB for custom fields, partitioning for time series                                                                                          |
| Cache/queues  | Redis + Laravel Horizon                                                                     | Retries, backoff, dead-letter, monitoring dashboard out of the box                                                                                                         |
| Search        | Meilisearch (via Scout)                                                                     | Global search with tenant-filtered indexes                                                                                                                                 |
| Files         | S3-compatible object storage                                                                | Tenant-prefixed paths, presigned access                                                                                                                                    |
| Billing       | Stripe behind a `PaymentProvider` interface (Cashier as implementation detail)              | Master Prompt §7 abstraction requirement                                                                                                                                   |
| RBAC          | spatie/laravel-permission (wrapped by our permission registry)                              | Proven granular permissions                                                                                                                                                |
| Super-admin   | Filament panel (platform scope only)                                                        | Fast, secure internal tooling                                                                                                                                              |
| AI            | Provider-abstracted client, Anthropic Claude first                                          | AIPF module: prompt mgmt, cost tracking, limits                                                                                                                            |
| E2E           | Playwright; Pest/PHPUnit for unit/API; Vitest for components                                | §33 layers                                                                                                                                                                 |
| CI/CD         | GitHub Actions → staging → production; gates per §51                                        | Lint, types (PHPStan + tsc), tests, security audit, migration validation, smoke test                                                                                       |
| Observability | Structured JSON logs, Sentry-class error tracking, health endpoints, uptime + queue metrics | §24                                                                                                                                                                        |
| Hosting       | Single-region containers (or Forge-class VPS) + managed Postgres with PITR                  | §26, §50; scale-out later without rebuild                                                                                                                                  |

## Alternative considered

**TypeScript end-to-end** (NestJS + Next.js + Prisma + BullMQ). Equally capable; one language across
stack; stronger ecosystem for some realtime UX. Rejected as default because it trades away the
fastest path to robust billing/queues/admin tooling and the owner's existing operational familiarity
with PHP deployments. Revisit at owner's request — this decision gates Stage 0.

## Architecture style

Modular monolith with strong domain boundaries (CRM, Marketing, SEO, Ads, Automation, Analytics,
Billing, Platform) — explicitly no premature microservices (Master Prompt §53). Domains communicate
through services/events, keeping later extraction possible. Background work runs on Horizon queues;
scheduled work (rank checks, syncs, digests) on the scheduler; heavy analytics pre-aggregated by jobs.

## Environments

Local (Docker Compose), Development, Staging, Production — config via environment only; no
hard-coded URLs, keys, price IDs or tenant IDs (Master Prompt §47–48).
