# 04 — High-Level Database Model

Conventions: UUID primary keys; `tenant_id` (FK → organizations) on every tenant-owned table;
`created_at`/`updated_at` everywhere; `deleted_at` soft deletes on business records; foreign keys

- indexes designed per access pattern; all schema changes via versioned migrations (Master Prompt §49).

Domains and principal entities (full column design happens per module spec):

## Identity & tenancy

- `users` (global identity), `organizations` (tenants), `organization_users` (membership + role),
  `teams`, `team_members`, `invitations`, `sessions`, `api_keys`, `mfa_credentials`
- `roles`, `permissions`, `role_permissions`

## Billing & entitlements

- `plans`, `prices` (monthly/annual/per-user/usage), `addons`, `coupons`, `taxes`
- `subscriptions`, `subscription_items`, `invoices`, `payments`, `credits`, `payment_methods`
- `entitlements` (plan ↔ feature/limit), `usage_records` (metered counters per tenant/period)
- `billing_events` (raw provider webhooks, idempotency-keyed)

## CRM & pipeline

- `companies`, `contacts`, `leads`, `accounts`, `opportunities`, `pipelines`, `pipeline_stages`, `deals`
  (deal value, MRR, ARR, contract term, LTV, win/loss)
- `activities` (polymorphic: notes, tasks, calls, emails, meetings), `custom_fields`, `custom_field_values`
- `lead_scores`, `score_rules`, `lead_routing_rules`, `duplicates`, `enrichment_records`
- `saved_views`

## Marketing execution

- `campaigns`, `channels`, `forms`, `form_submissions`, `landing_pages`, `websites`, `pages`, `content_items`
- `emails_outbound`, `email_templates`, `sms_outbound`, `lists`, `segments`, `suppressions`, `consents`
- `workflows`, `workflow_steps`, `workflow_runs`, `workflow_run_steps` (automation engine)
- `social_profiles`, `social_posts`, `videos`, `reviews`, `pr_mentions`, `backlinks`

## Search & AI visibility

- `keywords`, `keyword_clusters`, `rankings` (time series), `locations`, `gbp_profiles`, `citations`
- `competitors`, `competitor_rankings`, `crawl_audits`, `audit_findings`
- `ai_prompts` (monitored prompt library), `ai_visibility_checks`, `ai_mentions`, `ai_citations`

## Ads

- `ad_accounts`, `ad_campaigns`, `ad_groups`, `ads`, `ad_metrics_daily`, `retargeting_audiences`, `budgets`

## Attribution & analytics

- `visitors`, `visitor_sessions`, `touchpoints` (source→channel→campaign→ad→keyword→page),
  `attribution_records` (multi-model), `revenue_records` (MRR/ARR/LTV), `call_tracking_numbers`, `calls`
- `metrics_daily` (pre-aggregated dashboard rollups), `benchmarks` (anonymized cross-tenant aggregates),
  `growth_scores`
- `reports`, `report_schedules`, `dashboards`, `dashboard_widgets`

## Sales & work management

- `meetings`, `booking_pages`, `availability_rules`, `target_accounts` (ABM tiers), `enablement_assets`
- `projects`, `project_tasks`, `deliverables`, `approvals`, `sprints`, `client_portal_settings`

## Platform services

- `integrations`, `integration_credentials` (encrypted), `sync_runs`, `webhook_endpoints`, `webhook_deliveries`
- `notifications`, `notification_preferences`, `files`, `documents`
- `audit_logs` (actor, tenant, action, resource, before/after, IP/device)
- `support_tickets`, `ticket_messages`, `announcements`, `feature_flags`, `flag_overrides`
- `ai_requests` (cost/usage tracking per tenant/user/feature/provider/model)
- `jobs`/queue tables, `failed_jobs`, `import_jobs`, `import_rows`, `export_jobs`

## Scale notes (Master Prompt §52–53)

Time-series tables (`rankings`, `ad_metrics_daily`, `touchpoints`, `metrics_daily`, `ai_visibility_checks`)
are append-heavy: composite indexes on `(tenant_id, entity, date)`, partitioning-ready, and dashboards
read from pre-aggregated rollups — never raw scans at request time. Contacts/activities expected in the
millions: cursor pagination, server-side filtering, no unbounded browser payloads.
