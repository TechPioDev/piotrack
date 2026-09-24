<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectTask;
use App\Models\Sprint;
use App\Models\User;
use App\Services\Delivery\ProjectService;
use App\Support\CurrentOrganization;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    public function index(CurrentOrganization $current): Response
    {
        return Inertia::render('delivery/projects', [
            'projects' => Project::with(['members.user:id,name'])->latest('id')->get()
                ->map(fn (Project $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->description,
                    'status' => $p->status,
                    'health' => $p->health,
                    'starts_on' => $p->starts_on?->toDateString(),
                    'ends_on' => $p->ends_on?->toDateString(),
                    'progress' => $this->projects->progress($p),
                    'members' => $p->members->map(fn (ProjectMember $m) => [
                        'id' => $m->id,
                        'user' => $m->user?->name,
                        'role' => $m->role,
                    ])->all(),
                ]),
            'sprints' => Sprint::latest('id')->get()->map(fn (Sprint $s) => [
                'id' => $s->id,
                'project_id' => $s->project_id,
                'name' => $s->name,
                'goal' => $s->goal,
                'status' => $s->status,
                'starts_on' => $s->starts_on?->toDateString(),
                'ends_on' => $s->ends_on?->toDateString(),
            ]),
            'tasks' => ProjectTask::latest('id')->limit(200)->get()->map(fn (ProjectTask $t) => [
                'id' => $t->id,
                'project_id' => $t->project_id,
                'sprint_id' => $t->sprint_id,
                'title' => $t->title,
                'status' => $t->status,
                'priority' => $t->priority,
                'due_on' => $t->due_on?->toDateString(),
            ]),
            'deliverables' => Deliverable::latest('id')->get()->map(fn (Deliverable $d) => [
                'id' => $d->id,
                'project_id' => $d->project_id,
                'title' => $d->title,
                'type' => $d->type,
                'status' => $d->status,
                'approval_status' => $d->approval_status,
                'client_visible' => $d->client_visible,
                'due_on' => $d->due_on?->toDateString(),
                'rejection_reason' => $d->rejection_reason,
            ]),
            // This workspace's people only. It used to be the first hundred
            // users on the whole platform - other companies' staff included.
            'members' => $current->get()?->members()
                ->wherePivot('status', 'active')
                ->orderBy('users.name')
                ->get(['users.id', 'users.name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
                ->all() ?? [],
            'roles' => ProjectMember::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->projects->create($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['planning', 'active', 'on_hold', 'completed'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]));

        return back()->with('status', __('Project created.'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $project->update($request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'status' => ['sometimes', Rule::in(['planning', 'active', 'on_hold', 'completed'])],
            'health' => ['sometimes', Rule::in(['on_track', 'at_risk', 'off_track'])],
        ]));

        return back()->with('status', __('Project updated.'));
    }

    public function assign(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', TenantExists::member()],
            'role' => ['required', Rule::in(ProjectMember::ROLES)],
        ]);

        $this->projects->assign($project, User::findOrFail($data['user_id']), $data['role']);

        return back()->with('status', __('Team member assigned.'));
    }

    public function storeSprint(Request $request, Project $project): RedirectResponse
    {
        $this->projects->startSprint($project, $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'goal' => ['nullable', 'string', 'max:1000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]));

        return back()->with('status', __('Sprint created.'));
    }

    public function storeTask(Request $request, Project $project): RedirectResponse
    {
        $this->projects->addTask($project, $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sprint_id' => ['nullable', 'integer', TenantExists::in('sprints')],
            'assignee_id' => ['nullable', 'integer', TenantExists::member()],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'due_on' => ['nullable', 'date'],
        ]));

        return back()->with('status', __('Task added.'));
    }

    public function updateTask(Request $request, ProjectTask $task): RedirectResponse
    {
        $task->update($request->validate([
            'status' => ['required', Rule::in(['todo', 'in_progress', 'review', 'done'])],
        ]));

        return back()->with('status', __('Task updated.'));
    }

    public function storeDeliverable(Request $request, Project $project): RedirectResponse
    {
        $this->projects->addDeliverable($project, $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'type' => ['nullable', Rule::in(['document', 'content', 'website', 'campaign', 'report', 'design'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_on' => ['nullable', 'date'],
            'client_visible' => ['boolean'],
        ]));

        return back()->with('status', __('Deliverable added.'));
    }

    public function submitDeliverable(Deliverable $deliverable): RedirectResponse
    {
        $this->projects->submitForApproval($deliverable);

        return back()->with('status', __('Deliverable submitted for client approval.'));
    }

    /**
     * Internal approval path (an agency approving on the client's behalf still
     * records who approved). The portal has its own client-facing route.
     */
    public function approveDeliverable(Request $request, Deliverable $deliverable): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        try {
            $this->projects->approve($deliverable, $user);
        } catch (RuntimeException $e) {
            return back()->withErrors(['deliverable' => $e->getMessage()]);
        }

        return back()->with('status', __('Deliverable approved.'));
    }

    public function rejectDeliverable(Request $request, Deliverable $deliverable): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $user = $request->user();
        abort_if($user === null, 403);

        try {
            $this->projects->reject($deliverable, $user, $data['reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['deliverable' => $e->getMessage()]);
        }

        return back()->with('status', __('Deliverable sent back for revision.'));
    }
}
