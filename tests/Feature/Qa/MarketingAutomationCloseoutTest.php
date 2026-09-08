<?php

declare(strict_types=1);

/**
 * Marketing Automation close-out (Phase 50 — AUTO-003/005/008/027/029).
 *
 * Page-visit workflows off the first-party pixel, content-download workflows
 * off actual gated-link redemption, buyer-intent workflows off recorded
 * intent scores, the retargeting-audience action over list-backed audiences,
 * and conditional branching with a real engagement check.
 */

use App\Models\Contact;
use App\Models\File;
use App\Models\MarketingList;
use App\Models\OutboundMessage;
use App\Models\RetargetingAudience;
use App\Models\Visitor;
use App\Models\Workflow;
use App\Models\WorkflowEnrollment;
use App\Services\Advertising\RetargetingService;
use App\Services\Marketing\WorkflowEngine;
use App\Services\Sales\IntentService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('AutoClose Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function autoWorkflow(string $trigger, array $config = []): Workflow
{
    return Workflow::create([
        'name' => ucfirst($trigger).' wf '.uniqid(),
        'trigger_type' => $trigger,
        'trigger_config' => $config,
        'status' => 'active',
    ]);
}

it('fires page-visit workflows from the first-party pixel, pinned to a path', function () {
    $pricing = autoWorkflow('page_visit', ['path_contains' => '/pricing']);

    $contact = Contact::create(['first_name' => 'Vis', 'email' => 'vis@x.test']);
    Visitor::create(['visitor_key' => 'wfvisitor001', 'first_seen_at' => now(), 'visits' => 1, 'contact_id' => $contact->id]);

    $this->org->forceFill(['tracking_key' => 'tk_autoclose01'])->save();
    app(CurrentOrganization::class)->forget();

    // A blog view does not match the pinned path.
    $this->postJson(route('public.track.event', 'tk_autoclose01'), ['vid' => 'wfvisitor001', 'type' => 'pageview', 'path' => '/blog/post'])->assertOk();
    app(CurrentOrganization::class)->set($this->org);
    expect(WorkflowEnrollment::where('workflow_id', $pricing->id)->count())->toBe(0);

    // The pricing view does.
    app(CurrentOrganization::class)->forget();
    $this->postJson(route('public.track.event', 'tk_autoclose01'), ['vid' => 'wfvisitor001', 'type' => 'pageview', 'path' => '/pricing'])->assertOk();
    app(CurrentOrganization::class)->set($this->org);

    $enrollment = WorkflowEnrollment::where('workflow_id', $pricing->id)->firstOrFail();
    expect($enrollment->contact_id)->toBe($contact->id);
});

it('fires content-download workflows when the gated signed link is actually redeemed', function () {
    $workflow = autoWorkflow('content_download');

    Storage::fake('local');
    Storage::disk('local')->put('magnets/guide.pdf', 'pdf-bytes');
    $file = File::create(['disk' => 'local', 'path' => 'magnets/guide.pdf', 'name' => 'guide.pdf', 'mime' => 'application/pdf', 'size' => 9]);
    $contact = Contact::create(['first_name' => 'Dl', 'email' => 'dl@x.test']);

    // The signed URL carries the capturing contact — tamper-proof.
    $url = URL::temporarySignedRoute('public.magnet', now()->addDay(), ['file' => $file->id, 'contact' => $contact->id]);

    app(CurrentOrganization::class)->forget();
    $this->get($url)->assertOk();
    app(CurrentOrganization::class)->set($this->org);

    expect(WorkflowEnrollment::where('workflow_id', $workflow->id)->where('contact_id', $contact->id)->exists())->toBeTrue()
        ->and($file->refresh()->download_count)->toBe(1);
});

it('fires buyer-intent workflows only when the recorded score reaches the configured floor', function () {
    $workflow = autoWorkflow('intent_threshold', ['min_intent_score' => 15]);
    $contact = Contact::create(['first_name' => 'Int', 'email' => 'int@x.test']);

    // 10 points: below the floor — no enrollment.
    app(IntentService::class)->record($contact, 'content_view', 10, '/guide');
    expect(WorkflowEnrollment::where('workflow_id', $workflow->id)->count())->toBe(0);

    // Another 10 lifts the rolling score to 20 — enrolled exactly once.
    app(IntentService::class)->record($contact, 'repeat_visit', 10, '/pricing');
    app(IntentService::class)->record($contact, 'content_view', 2, '/blog');
    expect(WorkflowEnrollment::where('workflow_id', $workflow->id)->count())->toBe(1);
});

it('adds contacts to list-backed retargeting audiences from a workflow, never faking rule-based membership', function () {
    $list = MarketingList::create(['name' => 'Retarget pool', 'type' => 'static']);
    $listAudience = RetargetingAudience::create(['name' => 'Warm visitors', 'source' => 'list', 'marketing_list_id' => $list->id]);
    $ruleAudience = RetargetingAudience::create(['name' => 'High score', 'source' => 'behavior', 'rules' => ['min_lead_score' => 90]]);

    $workflow = autoWorkflow('form_submission');
    $workflow->steps()->create(['position' => 0, 'action_type' => 'add_to_audience', 'action_config' => ['audience_id' => $listAudience->id], 'delay_minutes' => 0]);
    $workflow->steps()->create(['position' => 1, 'action_type' => 'add_to_audience', 'action_config' => ['audience_id' => $ruleAudience->id], 'delay_minutes' => 0]);

    $contact = Contact::create(['first_name' => 'Aud', 'email' => 'aud@x.test']);
    $engine = app(WorkflowEngine::class);
    $enrollment = $engine->enroll($workflow, $contact);
    $engine->processEnrollment($enrollment);
    $engine->processEnrollment($enrollment->refresh());

    $service = app(RetargetingService::class);
    expect($service->members($listAudience)->pluck('id'))->toContain($contact->id)
        ->and($listAudience->refresh()->member_count)->toBe(1)
        // The rule-sourced audience is untouched: membership stays rule-owned.
        ->and($service->members($ruleAudience)->pluck('id'))->not->toContain($contact->id);
});

it('branches on step conditions: a real engagement check, skip vs exit', function () {
    $engine = app(WorkflowEngine::class);

    // Skip: the unengaged contact skips "Check Engagement" but finishes the sequence.
    $workflow = autoWorkflow('form_submission');
    $workflow->steps()->create(['position' => 0, 'action_type' => 'change_lifecycle', 'action_config' => ['stage' => 'mql'], 'delay_minutes' => 0]);
    $workflow->steps()->create([
        'position' => 1, 'action_type' => 'change_lifecycle', 'action_config' => ['stage' => 'sql'], 'delay_minutes' => 0,
        'condition' => ['field' => 'engaged_since_enrollment', 'operator' => 'equals', 'value' => 'yes', 'on_fail' => 'skip'],
    ]);

    $cold = Contact::create(['first_name' => 'Cold', 'email' => 'cold@x.test']);
    $enrollment = $engine->enroll($workflow, $cold);
    $engine->processEnrollment($enrollment);
    $engine->processEnrollment($enrollment->refresh());

    expect($cold->refresh()->lifecycle_stage)->toBe('mql')       // step 2 skipped
        ->and($enrollment->refresh()->status)->toBe('completed'); // but the sequence finished

    // Pass: an engaged contact (opened after enrollment) runs the gated step.
    $warm = Contact::create(['first_name' => 'Warm', 'email' => 'warm@x.test']);
    $warmEnrollment = $engine->enroll($workflow, $warm);
    OutboundMessage::create(['contact_id' => $warm->id, 'channel' => 'email', 'address' => 'warm@x.test', 'status' => 'sent', 'token' => 'tok'.uniqid(), 'opened_at' => now()]);
    $engine->processEnrollment($warmEnrollment);
    $engine->processEnrollment($warmEnrollment->refresh());
    expect($warm->refresh()->lifecycle_stage)->toBe('sql');

    // Exit: a failed condition with on_fail=exit ends the enrollment and runs nothing after.
    $exitWorkflow = autoWorkflow('form_submission');
    $exitWorkflow->steps()->create([
        'position' => 0, 'action_type' => 'change_lifecycle', 'action_config' => ['stage' => 'sql'], 'delay_minutes' => 0,
        'condition' => ['field' => 'lead_score', 'operator' => 'gte', 'value' => '50', 'on_fail' => 'exit'],
    ]);

    $low = Contact::create(['first_name' => 'Low', 'email' => 'low@x.test', 'lead_score' => 10]);
    $lowEnrollment = $engine->enroll($exitWorkflow, $low);
    $engine->processEnrollment($lowEnrollment);

    expect($lowEnrollment->refresh()->status)->toBe('exited')
        ->and($low->refresh()->lifecycle_stage)->not->toBe('sql');
});
