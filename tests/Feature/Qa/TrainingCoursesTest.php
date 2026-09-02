<?php

declare(strict_types=1);

/**
 * Training & Consulting close-out (Phase 15 — TRAIN-001..007).
 *
 * The one gap all seven rows shared: a course surface with materials and
 * completion tracking. Courses are topic-tagged, lessons are ordered
 * materials, completion is per-user and computed — and drafts are invisible
 * to members until a manager deliberately publishes.
 */

use App\Authorization\Role;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Services\Delivery\TrainingService;
use App\Support\CurrentOrganization;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Training Org');
    subscribeOrganization($this->org, 'enterprise');
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('authors a course with lessons and publishes it deliberately', function () {
    $this->actingAs($this->owner)
        ->post(route('strategy.training.store'), ['title' => 'SEO practice', 'topic' => 'seo', 'description' => 'Hands-on.'])
        ->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    $course = Course::firstOrFail();
    expect($course->is_published)->toBeFalse();

    $this->actingAs($this->owner)
        ->post(route('strategy.training.lessons.store', $course->id), ['title' => 'Rank tracking', 'body' => 'How ranks are checked.'])
        ->assertRedirect();
    $this->actingAs($this->owner)
        ->post(route('strategy.training.lessons.store', $course->id), ['title' => 'Crawls', 'body' => 'Reading the report.'])
        ->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    expect($course->lessons()->pluck('sort_order')->all())->toBe([1, 2]);

    $this->actingAs($this->owner)
        ->patch(route('strategy.training.update', $course->id), ['is_published' => true])
        ->assertRedirect();

    expect($course->fresh()->is_published)->toBeTrue();
});

it('hides drafts from members and shows them published courses with progress', function () {
    app(CurrentOrganization::class)->set($this->org);
    $draft = Course::create(['title' => 'Draft course', 'topic' => 'sales', 'is_published' => false]);
    $live = Course::create(['title' => 'Live course', 'topic' => 'marketing', 'is_published' => true]);
    Lesson::create(['course_id' => $live->id, 'title' => 'L1', 'sort_order' => 1]);
    app(CurrentOrganization::class)->forget();

    $viewer = addMember($this->org, Role::Viewer);

    $this->actingAs($viewer)->get(route('strategy.training.index'))->assertInertia(
        fn (AssertableInertia $page) => $page->component('strategy/training/index')
            ->has('courses', 1)
            ->where('courses.0.title', 'Live course')
            ->where('courses.0.progress.total', 1),
    );

    $this->actingAs($viewer)->get(route('strategy.training.show', $draft->id))->assertNotFound();
    // Managers still see the draft.
    $this->actingAs($this->owner)->get(route('strategy.training.show', $draft->id))->assertOk();
});

it('tracks completion per user, idempotently, and computes progress', function () {
    app(CurrentOrganization::class)->set($this->org);
    $course = Course::create(['title' => 'Course', 'topic' => 'seo', 'is_published' => true]);
    $l1 = Lesson::create(['course_id' => $course->id, 'title' => 'One', 'sort_order' => 1]);
    $l2 = Lesson::create(['course_id' => $course->id, 'title' => 'Two', 'sort_order' => 2]);
    app(CurrentOrganization::class)->forget();

    $viewer = addMember($this->org, Role::Viewer);

    $this->actingAs($viewer)->post(route('strategy.training.complete', $l1->id))->assertRedirect();
    $this->actingAs($viewer)->post(route('strategy.training.complete', $l1->id))->assertRedirect(); // idempotent

    app(CurrentOrganization::class)->set($this->org);
    expect(LessonCompletion::count())->toBe(1);

    $progress = app(TrainingService::class)->progress(Course::where('id', $course->id)->get(), $viewer);
    expect($progress[$course->id])->toBe(['total' => 2, 'completed' => 1, 'pct' => 50]);

    // Another user's progress is their own.
    $other = addMember($this->org, Role::Analyst);
    $progressOther = app(TrainingService::class)->progress(Course::where('id', $course->id)->get(), $other);
    expect($progressOther[$course->id]['completed'])->toBe(0);

    // Un-completing removes exactly this user's record.
    $this->actingAs($viewer)->delete(route('strategy.training.uncomplete', $l1->id))->assertRedirect();
    app(CurrentOrganization::class)->set($this->org);
    expect(LessonCompletion::count())->toBe(0)
        ->and($l2->fresh())->not->toBeNull();
});

it('seeds the starter curriculum as drafts, idempotently', function () {
    $this->actingAs($this->owner)->post(route('strategy.training.seed'))->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    expect(Course::count())->toBe(4)
        ->and(Lesson::count())->toBe(12)
        ->and(Course::where('is_published', true)->count())->toBe(0)
        ->and(Course::pluck('topic')->sort()->values()->all())->toBe(['executive', 'marketing', 'sales', 'seo']);

    // Running again adds nothing and touches nothing.
    $this->actingAs($this->owner)->post(route('strategy.training.seed'))->assertRedirect();
    app(CurrentOrganization::class)->set($this->org);
    expect(Course::count())->toBe(4)->and(Lesson::count())->toBe(12);
});

it('gates authoring behind strategy.manage and isolates tenants', function () {
    app(CurrentOrganization::class)->set($this->org);
    $course = Course::create(['title' => 'Ours', 'topic' => 'seo', 'is_published' => true]);
    app(CurrentOrganization::class)->forget();

    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)->post(route('strategy.training.store'), ['title' => 'X', 'topic' => 'seo'])->assertForbidden();
    $this->actingAs($viewer)->post(route('strategy.training.seed'))->assertForbidden();
    $this->actingAs($viewer)->delete(route('strategy.training.destroy', $course->id))->assertForbidden();

    [, $otherOwner] = makeOrganization('Other Org');
    $this->actingAs($otherOwner)->get(route('strategy.training.show', $course->id))->assertNotFound();
    $this->actingAs($otherOwner)->get(route('strategy.training.index'))->assertInertia(
        fn (AssertableInertia $page) => $page->has('courses', 0),
    );
});
