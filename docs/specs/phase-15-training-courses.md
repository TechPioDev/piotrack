# Phase 15 — Training & Consulting close-out: course surface + completion tracking

Register target: TRAIN-001..007 (7 rows). All seven share one recorded gap: engagements
(booking/tracking human-delivered consulting and training) exist and are Tested, but
"a course/LMS surface with materials and completion tracking is not built". This phase
builds exactly that surface; the consulting and teaching themselves remain
human-delivered, as the notes have always said.

## Design

Tenant-scoped, under the existing strategy area (strategy.view / strategy.manage):

- `courses` — title, topic (marketing | seo | sales | executive), description,
  is_published. Members see published courses; managers see and edit everything.
- `lessons` — course-ordered materials: title, body, optional resource URL.
- `lesson_completions` — per-user, per-lesson, unique; progress is computed
  (completed / published lessons), never stored where it could drift.

`TrainingService`: progress math and an idempotent starter curriculum
(`seedCurriculum()`): 4 courses × 3 lessons of real, platform-anchored training
material (how rank tracking, the keyword library, §20 scoring, intent signals and QBR
prep actually work in Piotrack) — domain knowledge like the MSP keyword library seeds,
not fabricated data. Seeded courses arrive unpublished so the manager reviews and
publishes deliberately.

Surface: `/strategy/training` (course grid with per-topic filter and progress),
`/strategy/training/{course}` (lessons, mark-complete toggles, manager editing).
Sidebar: Strategy → Training.

## Tests (tests/Feature/Qa/TrainingCoursesTest.php)

1. Course + lesson management with publish gating (members see published only).
2. Completion tracking: per-user progress math, idempotent completes, un-complete.
3. Curriculum seed: 4 courses/12 lessons, idempotent, arrives unpublished.
4. Permission gating (viewer reads, cannot manage) + tenant isolation.
