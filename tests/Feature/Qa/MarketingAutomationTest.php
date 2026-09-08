<?php

declare(strict_types=1);

/**
 * QA §21/§22 - marketing automation and email, including the failure paths.
 *
 * §21 specifies a nine-action workflow off a "Cybersecurity Guide Download"
 * trigger. Seven of the nine are expressible and are executed here end to end.
 * Two are not, and are asserted as absent rather than quietly substituted:
 *
 *   "Add Tag"          - there is no tag concept; segmentation is list-based,
 *                        so add_to_list stands in and is exercised as such.
 *   "Check Engagement" - CLOSED in Phase 50 (AUTO-029): workflow_steps gained
 *                        a condition column ({field, operator, value, on_fail})
 *                        including a real engaged_since_enrollment check; the
 *                        pin test below now asserts the shipped shape and the
 *                        semantics live in MarketingAutomationCloseoutTest.
 *
 * Prospect: Michael Rodriguez, CFO, Precision Manufacturing Group.
 */

use App\Authorization\Role;
use App\Jobs\RunWorkflowStep;
use App\Models\Contact;
use App\Models\MarketingList;
use App\Models\OutboundMessage;
use App\Models\Workflow;
use App\Models\WorkflowEnrollment;
use App\Models\WorkflowStep;
use App\Notifications\WorkflowNotification;
use App\Services\Marketing\WorkflowEngine;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);

    $this->sarah = addMember($this->org, Role::SalesManager);
    $this->sarah->forceFill(['name' => 'Sarah Mitchell'])->save();

    $this->retargeting = MarketingList::create(['name' => 'CMMC retargeting audience', 'type' => 'static']);

    $this->contact = Contact::create([
        'first_name' => 'Michael', 'last_name' => 'Rodriguez',
        'email' => 'michael.rodriguez@precisionmfg-test.com',
        'title' => 'CFO', 'lead_score' => 30, 'email_opt_in' => true,
    ]);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

/** The §21 sequence, minus the two actions the engine cannot express. */
function cybersecurityGuideWorkflow(int $assigneeId, int $retargetingListId): Workflow
{
    $workflow = Workflow::create([
        'name' => 'Cybersecurity Guide Download nurture',
        'trigger_type' => 'form_submission',
        'status' => 'active',
    ]);

    $steps = [
        ['update_crm', ['lifecycle_stage' => 'mql'], 0],
        ['change_score', ['delta' => 25], 0],
        ['send_email', ['subject' => 'Your CMMC compliance guide', 'body' => 'Thanks for downloading.'], 0],
        ['assign', ['user_id' => $assigneeId], 60],
        ['notify', ['message' => 'Hot lead from the CMMC guide'], 0],
        ['add_to_list', ['list_id' => $retargetingListId], 0],
    ];

    foreach ($steps as $position => [$type, $config, $delay]) {
        WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'position' => $position,
            'action_type' => $type,
            'action_config' => $config,
            'delay_minutes' => $delay,
        ]);
    }

    return $workflow;
}

it('executes the §21 workflow end to end and lands every side effect', function () {
    Notification::fake();

    $workflow = cybersecurityGuideWorkflow($this->sarah->id, $this->retargeting->id);
    $engine = app(WorkflowEngine::class);

    $enrollment = $engine->enroll($workflow, $this->contact);
    expect($enrollment)->not->toBeNull();

    // Drive every step to completion.
    for ($i = 0; $i < 10 && $enrollment->fresh()->status === 'active'; $i++) {
        $enrollment->fresh()->forceFill(['next_run_at' => now()->subMinute()])->save();
        $engine->processEnrollment($enrollment->fresh());
    }

    $contact = $this->contact->fresh();
    $enrollment = $enrollment->fresh();

    expect($enrollment->status)->toBe('completed', 'the sequence did not run to completion')
        // 1. CRM updated
        ->and($contact->lifecycle_stage)->toBe('mql')
        // 2. Score raised from 30
        ->and($contact->lead_score)->toBe(55)
        // 3. Salesperson assigned
        ->and($contact->owner_id)->toBe($this->sarah->id);

    // 4. Email queued through the dispatcher.
    $email = OutboundMessage::where('contact_id', $contact->id)->where('channel', 'email')->first();

    expect($email)->not->toBeNull('no email was dispatched')
        ->and($email->subject)->toBe('Your CMMC compliance guide')
        ->and($email->source)->toBe('automation')
        ->and($email->workflow_id)->toBe($workflow->id)
        // A tracking token is what makes opens, clicks and unsubscribes work.
        ->and($email->token)->not->toBeEmpty();

    // 5. Alert raised - `notify` reaches the organization's owners rather than
    // writing a SalesAlert row, which is AlertService's job.
    Notification::assertSentTo(
        $this->owner,
        WorkflowNotification::class,
    );

    // 6. Added to the retargeting audience.
    expect($this->retargeting->fresh()->contacts()->pluck('contacts.id')->all())
        ->toContain($contact->id);
});

/*
|--------------------------------------------------------------------------
| Failure paths §21 names
|--------------------------------------------------------------------------
*/

it('refuses a duplicate trigger while an enrolment is already running', function () {
    $workflow = cybersecurityGuideWorkflow($this->sarah->id, $this->retargeting->id);
    $engine = app(WorkflowEngine::class);

    expect($engine->enroll($workflow, $this->contact))->not->toBeNull()
        ->and($engine->enroll($workflow, $this->contact))->toBeNull()
        ->and($engine->enroll($workflow, $this->contact))->toBeNull();

    expect(WorkflowEnrollment::where('workflow_id', $workflow->id)->count())->toBe(1)
        ->and($workflow->fresh()->enrolled_count)->toBe(1);
});

it('does not enrol into a paused workflow', function () {
    $workflow = cybersecurityGuideWorkflow($this->sarah->id, $this->retargeting->id);
    $workflow->update(['status' => 'paused']);

    expect(app(WorkflowEngine::class)->enroll($workflow->fresh(), $this->contact))->toBeNull()
        ->and(WorkflowEnrollment::count())->toBe(0);
});

it('stops a running enrolment mid-sequence and performs no further actions', function () {
    $workflow = cybersecurityGuideWorkflow($this->sarah->id, $this->retargeting->id);
    $engine = app(WorkflowEngine::class);

    $enrollment = $engine->enroll($workflow, $this->contact);

    // First step only: the CRM update.
    $engine->processEnrollment($enrollment->fresh());
    expect($this->contact->fresh()->lifecycle_stage)->toBe('mql');

    // Someone stops it.
    $enrollment->fresh()->update(['status' => 'exited']);

    $scoreAtStop = $this->contact->fresh()->lead_score;
    $engine->processEnrollment($enrollment->fresh());
    $engine->processEnrollment($enrollment->fresh());

    expect($this->contact->fresh()->lead_score)->toBe($scoreAtStop, 'a stopped enrolment kept acting')
        ->and($this->contact->fresh()->owner_id)->toBeNull()
        ->and($enrollment->fresh()->status)->toBe('exited');
});

it('removes enrolments with the workflow rather than leaving them orphaned', function () {
    $workflow = cybersecurityGuideWorkflow($this->sarah->id, $this->retargeting->id);
    $engine = app(WorkflowEngine::class);
    $enrollment = $engine->enroll($workflow, $this->contact);

    expect(WorkflowEnrollment::whereKey($enrollment->id)->exists())->toBeTrue();

    // workflow_enrollments.workflow_id cascades on delete, so a deleted workflow
    // takes its enrolments with it - nothing is left pointing at a missing
    // workflow, and dispatchDue cannot pick up a dangling row.
    $workflow->delete();

    expect(WorkflowEnrollment::whereKey($enrollment->id)->exists())->toBeFalse()
        ->and(WorkflowStep::where('workflow_id', $workflow->id)->count())->toBe(0);

    expect($engine->dispatchDue())->toBe(0);
});

it('isolates a failing enrolment behind its own job rather than one batch', function () {
    // dispatchDue queues one job per enrolment, each with three tries, so a
    // poisoned enrolment cannot stall the others.
    Queue::fake();

    $workflow = cybersecurityGuideWorkflow($this->sarah->id, $this->retargeting->id);
    $engine = app(WorkflowEngine::class);

    $second = Contact::create([
        'first_name' => 'Helena', 'last_name' => 'Vasquez',
        'email' => 'helena@precisionmfg-test.com', 'email_opt_in' => true,
    ]);

    $engine->enroll($workflow, $this->contact);
    $engine->enroll($workflow, $second);

    expect($engine->dispatchDue())->toBe(2);

    Queue::assertPushed(RunWorkflowStep::class, 2);
    expect((new RunWorkflowStep(1, 1))->tries)->toBe(3);
});

/*
|--------------------------------------------------------------------------
| §21 capability gaps, asserted so they cannot be lost
|--------------------------------------------------------------------------
*/

it('confirms the workflow engine now supports conditional branching (AUTO-029, closed Phase 50)', function () {
    // This test originally pinned the ABSENCE of branching (QA gap of
    // 2026-08-19): §21 step 6 is "Check Engagement", which needs a condition.
    // Phase 50 shipped workflow_steps.condition ({field, operator, value,
    // on_fail}) with the engaged_since_enrollment check reading real
    // open/click timestamps — so the pin flips to assert the shipped shape.
    // Semantics are covered in MarketingAutomationCloseoutTest.
    $columns = Schema::getColumnListing('workflow_steps');

    expect($columns)->toContain('condition')
        ->and($columns)->toContain('position')
        ->and($columns)->toContain('action_type')
        ->and($columns)->toContain('delay_minutes');
});

it('confirms there is no tag concept, only list membership', function () {
    expect(Schema::hasTable('tags'))->toBeFalse()
        ->and(Schema::hasTable('contact_tag'))->toBeFalse()
        ->and(Schema::getColumnListing('contacts'))->not->toContain('tags');

    // Lists are the substitute, and they work.
    expect(Schema::hasTable('marketing_lists'))->toBeTrue();
});
