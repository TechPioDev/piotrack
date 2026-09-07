<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingList;
use App\Models\Vertical;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Support\AuditLogger;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    private const TRIGGERS = ['form_submission', 'lead_stage', 'deal_stage', 'email_engagement', 'list_added', 'booking_no_show', 'booking_completed'];

    private const ACTIONS = [
        'send_email', 'send_sms', 'assign', 'create_task', 'update_crm', 'change_score',
        'change_lifecycle', 'notify', 'add_to_list', 'remove_from_list', 'schedule_follow_up',
    ];

    public function __construct(private AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('marketing/automation/index', [
            'workflows' => Workflow::withCount('steps')->latest('id')->get()->map(fn (Workflow $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'trigger_type' => $w->trigger_type,
                'status' => $w->status,
                'steps_count' => $w->steps_count,
                'enrolled_count' => $w->enrolled_count,
                'completed_count' => $w->completed_count,
            ]),
            'triggers' => self::TRIGGERS,
            // VERT-018: bindable vertical for the coverage report.
            'verticals' => Vertical::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Workflow $workflow): Response
    {
        return Inertia::render('marketing/automation/show', [
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'trigger_type' => $workflow->trigger_type,
                'trigger_config' => $workflow->trigger_config,
                'status' => $workflow->status,
                'enrolled_count' => $workflow->enrolled_count,
                'completed_count' => $workflow->completed_count,
            ],
            'steps' => $workflow->steps()->get()->map(fn (WorkflowStep $s) => [
                'id' => $s->id,
                'position' => $s->position,
                'action_type' => $s->action_type,
                'action_config' => $s->action_config,
                'delay_minutes' => $s->delay_minutes,
            ]),
            'actions' => self::ACTIONS,
            'lists' => MarketingList::orderBy('name')->get(['id', 'name'])
                ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'trigger_type' => ['required', Rule::in(self::TRIGGERS)],
            'trigger_config' => ['nullable', 'array'],
            // VERT-018: vertical the sequence targets; coverage joins on it.
            'vertical_id' => ['nullable', 'integer', TenantExists::in('verticals')],
        ]);

        $workflow = Workflow::create($data);
        $this->audit->log('workflow.created', context: ['name' => $workflow->name], resourceType: 'workflow', resourceId: (string) $workflow->id, organizationId: $workflow->organization_id);

        return redirect()->route('marketing.automation.show', $workflow->id)->with('status', __('Workflow created.'));
    }

    public function update(Request $request, Workflow $workflow): RedirectResponse
    {
        $workflow->update($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'trigger_type' => ['required', Rule::in(self::TRIGGERS)],
            'trigger_config' => ['nullable', 'array'],
        ]));

        return back()->with('status', __('Workflow saved.'));
    }

    public function toggle(Workflow $workflow): RedirectResponse
    {
        $workflow->update(['status' => $workflow->isActive() ? 'paused' : 'active']);
        $this->audit->log('workflow.'.$workflow->status, resourceType: 'workflow', resourceId: (string) $workflow->id, organizationId: $workflow->organization_id);

        return back()->with('status', __('Workflow :status.', ['status' => $workflow->status]));
    }

    public function addStep(Request $request, Workflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'action_type' => ['required', Rule::in(self::ACTIONS)],
            'action_config' => ['nullable', 'array'],
            'delay_minutes' => ['nullable', 'integer', 'min:0', 'max:525600'],
        ]);

        $workflow->steps()->create([
            'position' => (int) $workflow->steps()->max('position') + 1,
            'action_type' => $data['action_type'],
            'action_config' => $data['action_config'] ?? [],
            'delay_minutes' => $data['delay_minutes'] ?? 0,
        ]);

        return back()->with('status', __('Step added.'));
    }

    public function deleteStep(Workflow $workflow, WorkflowStep $step): RedirectResponse
    {
        abort_unless($step->workflow_id === $workflow->id, 404);
        $step->delete();

        return back()->with('status', __('Step removed.'));
    }

    public function destroy(Workflow $workflow): RedirectResponse
    {
        $this->audit->log('workflow.deleted', context: ['name' => $workflow->name], resourceType: 'workflow', resourceId: (string) $workflow->id, organizationId: $workflow->organization_id);
        $workflow->delete();

        return redirect()->route('marketing.automation.index')->with('status', __('Workflow deleted.'));
    }
}
