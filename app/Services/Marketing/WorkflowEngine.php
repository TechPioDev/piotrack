<?php

namespace App\Services\Marketing;

use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Jobs\RunWorkflowStep;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\OutboundMessage;
use App\Models\Workflow;
use App\Models\WorkflowEnrollment;
use App\Models\WorkflowStep;

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

        // AUTO-029: a step may carry a condition. Failing it either skips the
        // step (the sequence continues) or exits the enrollment entirely.
        if ($contact !== null && ! $this->conditionPasses($step, $contact, $enrollment)) {
            if ((string) (($step->condition ?? [])['on_fail'] ?? 'skip') === 'exit') {
                $enrollment->update(['status' => 'exited', 'next_run_at' => null]);

                return;
            }
            $contact = null; // skip: nothing executes, scheduling continues below.
        }

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

    /**
     * AUTO-029: evaluate a step's condition against the real contact record.
     * Fields are actual attributes plus `engaged_since_enrollment` — a genuine
     * check for an email open/click AFTER the contact entered the workflow
     * (QA §21 step 6, "Check Engagement"). No condition = always run.
     */
    public function conditionPasses(WorkflowStep $step, Contact $contact, WorkflowEnrollment $enrollment): bool
    {
        $condition = $step->condition ?? [];
        $field = (string) ($condition['field'] ?? '');

        if ($field === '') {
            return true;
        }

        $actual = match ($field) {
            'engaged_since_enrollment' => OutboundMessage::where('contact_id', $contact->id)
                ->where(fn ($q) => $q
                    ->where('opened_at', '>=', $enrollment->enrolled_at)
                    ->orWhere('clicked_at', '>=', $enrollment->enrolled_at))
                ->exists() ? 'yes' : 'no',
            'lead_score' => (string) $contact->lead_score,
            default => (string) $contact->getAttribute($field),
        };

        $expected = (string) ($condition['value'] ?? '');

        return match ((string) ($condition['operator'] ?? 'equals')) {
            'not_equals' => mb_strtolower($actual) !== mb_strtolower($expected),
            'gte' => (float) $actual >= (float) $expected,
            'lte' => (float) $actual <= (float) $expected,
            'contains' => $expected !== '' && str_contains(mb_strtolower($actual), mb_strtolower($expected)),
            default => mb_strtolower($actual) === mb_strtolower($expected),
        };
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
