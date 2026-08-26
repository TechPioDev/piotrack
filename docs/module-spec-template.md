# Module Specification — <Module Name> (<CODE>)

> Complete this spec before writing code for the module (Master Prompt §58–59).
> Answer the §58 pre-development questions while filling it in.

## Purpose

What business capability this module delivers and for whom.

## Users & roles

Which roles use it; which permissions gate each capability (`domain.resource.action` list).

## Feature IDs

Register rows in scope for this module gate (from docs/register/feature-register.csv).

## User stories

As a <role>, I …

## Subscription requirements

Plans/entitlements/feature keys; usage meters touched; limit behavior.

## Database entities

Tables, key columns, relationships, indexes, tenant scoping notes, migration plan.

## API endpoints

Method, path, permission, request/response shape, pagination/filtering, idempotency needs.

## UI pages & components

Pages, navigation placement, reused design-system components, new components required.

## Integrations

Connectors consumed/required; behavior when disconnected.

## Notifications

Events emitted; channels; user preferences.

## Background jobs

Queued/scheduled work; retry/idempotency requirements.

## Business rules & validation

Rules, calculations, validation constraints (client + server).

## Error cases

Failure modes and the UX for each (validation, permission, not-found, integration, network).

## Audit requirements

Events recorded with before/after where applicable.

## Analytics events

Product analytics/metrics this module records.

## Automated tests

Unit / API / integration / UI / E2E cases (including authz + tenant isolation).

## Manual QA checklist

Visual, interaction, error-path and responsive checks specific to this module.

## Acceptance criteria

Checklist that gates module completion (feeds the Module Completion Report).
