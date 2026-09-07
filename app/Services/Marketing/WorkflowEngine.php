<?php

namespace App\Services\Marketing;

use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Jobs\RunWorkflowStep;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Workflow;
use App\Models\WorkflowEnrollment;

/**
 * The automation runtime (AUTO-001…017). A contact is enrolled on a trigger,
 * then steps run in order with per-step delays. Enrollment is unique per active
 * (workflow, contact): re-triggering while active is a no-op.
 */
class WorkflowEngine
{
    public function __construct(private ActionExecutor $actions) {}

    public function enroll(Workflow $workflow, Contact $contact): ?WorkflowEnrollment
    {
        if (! $workflow->isActive()) {
            return null;
        }

        $existing = WorkflowEnrollment::where('workflow_id', $workflow->id)
            ->where('contact_id', $contact->id)
            ->where('status', 'active')
            ->first();

        if ($existing !== null) {
            return null;
        }

        // ENTL-004: the plan's workflow-execution allowance for the period.
        $organization = Organization::find($workflow->organization_id);
        $meter = app(UsageMeter::class);
        if ($organization !== null && ! $meter->withinLimit($organization, Limit::WorkflowExecutions)) {
            return null;
        }

        $enrollment = WorkflowEnrollment::create([
            'workflow_id' => $workflow->id,
            'contact_id' => $contact->id,
            'current_position' => 0,
            'status' => 'active',
            'next_run_at' => now(),
            'enrolled_at' => now(),
        ]);

        $workflow->increment('enrolled_count');

        if ($organization !== null) {
            $meter->increment($organization, Limit::WorkflowExecutions);
        }

        return $enrollment;
    }

    /**
     * Run the enrollment's current step, then schedule the next (or complete).
     */
    public function processEnrollment(WorkflowEnrollment $enrollment): void
    {
        if ($enrollment->status !== 'active') {
            return;
        }

        $workflow = $enrollment->workflow()->first();

        if ($workflow === null) {
            $enrollment->update(['status' => 'exited']);

            return;
        }

        $steps = $workflow->steps()->get();
        $step = $steps->firstWhere('position', $enrollment->current_position);

        if ($step === null) {
            $this->complete($enrollment, $workflow);

            return;
        }

        $contact = $enrollment->contact()->first();

        if ($contact !== null) {
            $this->actions->execute($step, $contact, $workflow);
        }

        $nextPosition = $enrollment->current_position + 1;
        $nextStep = $steps->firstWhere('position', $nextPosition);

        if ($nextStep === null) {
            $this->complete($enrollment, $workflow);

            return;
        }

        $enrollment->update([
            'current_position' => $nextPosition,
            'next_run_at' => now()->addMinutes($nextStep->delay_minutes),
        ]);
    }

    /**
     * Dispatch a step job for every enrollment whose next run is due. Called by
     * the scheduler (marketing:process-workflows).
     */
    public function dispatchDue(): int
    {
        $due = WorkflowEnrollment::where('status', 'active')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->get();

        foreach ($due as $enrollment) {
            RunWorkflowStep::dispatch($enrollment->id, $enrollment->organization_id);
        }

        return $due->count();
    }

    private function complete(WorkflowEnrollment $enrollment, Workflow $workflow): void
    {
        $enrollment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'next_run_at' => null,
        ]);

        $workflow->increment('completed_count');
    }
}
