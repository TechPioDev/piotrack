<?php

namespace App\Http\Controllers\Strategy;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Services\Delivery\TrainingService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The training course surface (TRAIN-001..007). Members read published
 * courses and track their own completion; managers author and publish.
 */
class TrainingController extends Controller
{
    public function __construct(
        private TrainingService $training,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $canManage = $request->user()->can('strategy.manage');

        $courses = Course::when(! $canManage, fn ($q) => $q->where('is_published', true))
            ->orderBy('topic')->orderBy('title')->get();

        $progress = $this->training->progress($courses, $request->user());

        return Inertia::render('strategy/training/index', [
            'topics' => Course::TOPICS,
            'courses' => $courses->map(fn (Course $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'topic' => $c->topic,
                'description' => $c->description,
                'is_published' => $c->is_published,
                'progress' => $progress[$c->id],
            ]),
        ]);
    }

    public function show(Request $request, Course $course): Response
    {
        $canManage = $request->user()->can('strategy.manage');
        abort_unless($course->is_published || $canManage, 404);

        $completed = LessonCompletion::where('user_id', $request->user()->id)
            ->whereIn('lesson_id', $course->lessons()->pluck('id'))
            ->pluck('lesson_id')->flip();

        return Inertia::render('strategy/training/show', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'topic' => $course->topic,
                'description' => $course->description,
                'is_published' => $course->is_published,
            ],
            'lessons' => $course->lessons->map(fn (Lesson $l) => [
                'id' => $l->id,
                'title' => $l->title,
                'body' => $l->body,
                'resource_url' => $l->resource_url,
                'completed' => isset($completed[$l->id]),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $course = Course::create($request->validate([
            'title' => ['required', 'string', 'max:200'],
            'topic' => ['required', Rule::in(Course::TOPICS)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]));

        $this->audit->log('training.course.created', context: ['title' => $course->title], resourceType: 'course', resourceId: (string) $course->id);

        return redirect()->route('strategy.training.show', $course->id)->with('status', __('Course created — add lessons, then publish.'));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $course->update($request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'topic' => ['sometimes', Rule::in(Course::TOPICS)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['boolean'],
        ]));

        return back()->with('status', __('Course updated.'));
    }

    public function destroy(Course $course): RedirectResponse
    {
        $course->delete();

        return redirect()->route('strategy.training.index')->with('status', __('Course removed.'));
    }

    public function storeLesson(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:20000'],
            'resource_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $course->lessons()->create($data + [
            'organization_id' => $course->organization_id,
            'sort_order' => ((int) $course->lessons()->max('sort_order')) + 1,
        ]);

        return back()->with('status', __('Lesson added.'));
    }

    public function destroyLesson(Lesson $lesson): RedirectResponse
    {
        $lesson->delete();

        return back()->with('status', __('Lesson removed.'));
    }

    /** Completion is personal: any member marks their own progress. */
    public function complete(Request $request, Lesson $lesson): RedirectResponse
    {
        abort_unless($lesson->course->is_published || $request->user()->can('strategy.manage'), 404);

        LessonCompletion::firstOrCreate(
            ['lesson_id' => $lesson->id, 'user_id' => $request->user()->id],
            ['completed_at' => now()],
        );

        return back()->with('status', __('Lesson marked complete.'));
    }

    public function uncomplete(Request $request, Lesson $lesson): RedirectResponse
    {
        LessonCompletion::where('lesson_id', $lesson->id)->where('user_id', $request->user()->id)->delete();

        return back()->with('status', __('Lesson marked incomplete.'));
    }

    public function seed(): RedirectResponse
    {
        $created = $this->training->seedCurriculum();

        return back()->with('status', $created > 0
            ? __(':n starter courses added as drafts — review and publish.', ['n' => $created])
            : __('The starter curriculum is already in place.'));
    }
}
