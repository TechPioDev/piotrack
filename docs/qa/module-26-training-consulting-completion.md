# Module Completion Report — Training & Consulting, Phase 15

**Date:** 3 September 2026 · **Module:** Training & Consulting · **Register: 13/13 Tested (100%)**, was 6/13 (46%)

## What shipped

All seven open rows shared one recorded gap: engagements (booking and tracking the
human-delivered consulting and training) were already Tested, but "a course/LMS surface
with materials and completion tracking is not built". Phase 15 built exactly that:

- **Courses** — topic-tagged (marketing / seo / sales / executive), described, and
  publish-gated: members see published courses only; managers author drafts and publish
  deliberately.
- **Lessons** — ordered materials with an optional resource link.
- **Completion tracking** — per-user, per-lesson, idempotent; course progress is
  computed from the records (completed / total), never stored where it could drift.
- **Starter curriculum** — 4 draft courses × 3 lessons of real, platform-anchored
  training material (how the capture chain, attribution, rank tracking, crawl reports,
  §20 scoring, intent alerts and QBR prep actually work in Piotrack) — the same
  discipline as the MSP keyword library: domain knowledge, not fabricated data.
  Idempotent, and it arrives unpublished for manager review.
- **Surface** — `/strategy/training` (course grid, topic filter, progress bars) and a
  course page with mark-complete toggles and manager editing; destructive actions go
  through the Phase 13 `ConfirmAction` standard. Sidebar: Strategy → Training.
  Permissions: `strategy.view` to read and track own completion, `strategy.manage`
  to author.

The consulting and teaching themselves remain human-delivered — as every one of these
rows has said from the start; the platform now carries their materials and measures
completion, which is what the rows were waiting on.

## Gate evidence

- Pest: **889 passed / 3,990 assertions** (+5:
  [TrainingCoursesTest](../../tests/Feature/Qa/TrainingCoursesTest.php) — authoring +
  deliberate publish, draft invisibility to members, per-user idempotent completion
  with computed progress, curriculum seed idempotency, permission gating + tenant
  isolation).
- Pint clean · PHPStan 0 · Prettier/ESLint/tsc clean · Vite build ok · Vitest 55.
