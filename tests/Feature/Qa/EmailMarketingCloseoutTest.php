<?php

declare(strict_types=1);

/**
 * Email Marketing close-out (Phase 34 — EMAIL-003/009/012/013/014/015/019).
 *
 * Dynamic-segment audiences actually receive campaigns (a real bug fixed),
 * subject A/B splits with per-variant results, conditional content blocks,
 * the email_engagement behavioral trigger fired by first click, post-send
 * conversion attribution, and plain-text sequences through the workflow
 * engine.
 */

use App\Marketing\MergeTags;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\MarketingList;
use App\Models\OutboundMessage;
use App\Models\Pipeline;
use App\Models\Workflow;
use App\Models\WorkflowEnrollment;
use App\Models\WorkflowStep;
use App\Services\Marketing\CampaignService;
use App\Services\Marketing\EmailTrackingService;
use App\Services\Marketing\ListService;
use App\Services\Marketing\WorkflowEngine;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Email Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function emailContact(string $first, string $email, array $extra = []): Contact
{
    return Contact::create(['first_name' => $first, 'email' => $email, 'email_opt_in' => true] + $extra);
}

it('sends to a dynamic segment by its criteria, not just static memberships', function () {
    // Criteria: lead_score >= 50. Nobody is a static member of this list.
    $segment = MarketingList::create(['name' => 'Hot leads', 'type' => 'dynamic', 'criteria' => ['min_lead_score' => 50]]);
    $hot = emailContact('Hot', 'hot@x.com', ['lead_score' => 80]);
    emailContact('Cold', 'cold@x.com', ['lead_score' => 10]);

    $campaign = Campaign::create(['name' => 'Segment blast', 'channel' => 'email', 'subject' => 'Hi {{first_name}}', 'body_html' => '<p>Hello</p>', 'marketing_list_id' => $segment->id, 'status' => 'draft']);
    app(CampaignService::class)->send($campaign);

    $recipients = CampaignRecipient::where('campaign_id', $campaign->id)->get();
    expect($recipients)->toHaveCount(1)
        ->and($recipients->first()->contact_id)->toBe($hot->id)
        ->and($campaign->refresh()->stat_sent)->toBe(1);
});

it('splits subject A/B, reports per-variant opens and the leader', function () {
    $list = MarketingList::create(['name' => 'AB list', 'type' => 'static']);
    $contacts = collect(range(1, 4))->map(fn ($i) => emailContact("C{$i}", "ab{$i}@x.com"));
    foreach ($contacts as $contact) {
        app(ListService::class)->addContact($list, $contact);
    }

    $campaign = Campaign::create(['name' => 'AB test', 'channel' => 'email', 'subject' => 'Subject A', 'subject_b' => 'Subject B', 'body_html' => '<p>x</p>', 'marketing_list_id' => $list->id, 'status' => 'draft']);
    app(CampaignService::class)->send($campaign);

    $recipients = CampaignRecipient::where('campaign_id', $campaign->id)->get();
    expect($recipients->where('variant', 'a'))->toHaveCount(2)
        ->and($recipients->where('variant', 'b'))->toHaveCount(2);

    // Both B recipients open; one A recipient opens: B leads.
    foreach ($recipients->where('variant', 'b') as $recipient) {
        app(EmailTrackingService::class)->open($recipient->token);
    }
    app(EmailTrackingService::class)->open($recipients->firstWhere('variant', 'a')->token);

    $results = app(CampaignService::class)->abResults($campaign->refresh());
    expect($results['variants']['a']['open_rate'])->toBe(50.0)
        ->and($results['variants']['b']['open_rate'])->toBe(100.0)
        ->and($results['leader'])->toBe('b');

    // Without a B subject there is no split and no report.
    $plain = Campaign::create(['name' => 'Plain', 'channel' => 'email', 'subject' => 'One subject', 'body_html' => '<p>x</p>', 'marketing_list_id' => $list->id, 'status' => 'draft']);
    app(CampaignService::class)->send($plain);
    expect(CampaignRecipient::where('campaign_id', $plain->id)->whereNotNull('variant')->count())->toBe(0)
        ->and(app(CampaignService::class)->abResults($plain->refresh()))->toBeNull();
});

it('renders conditional content blocks per contact', function () {
    $customer = emailContact('Vip', 'vip@x.com', ['lifecycle_stage' => 'customer']);
    $lead = emailContact('New', 'new@x.com', ['lifecycle_stage' => 'lead']);

    $template = 'Hi {{first_name}}. {{#if lifecycle_stage=customer}}Thanks for being a client.{{else}}Here is what clients say.{{/if}}{{#if company}} From {{company}}.{{/if}}';

    expect(MergeTags::render($template, $customer))->toBe('Hi Vip. Thanks for being a client.')
        ->and(MergeTags::render($template, $lead))->toBe('Hi New. Here is what clients say.');
});

it('fires the email_engagement workflow on first click only', function () {
    $workflow = Workflow::create(['name' => 'Clicker nurture', 'trigger_type' => 'email_engagement', 'status' => 'active']);
    WorkflowStep::create(['workflow_id' => $workflow->id, 'position' => 0, 'action_type' => 'change_score', 'action_config' => ['delta' => 5], 'delay_minutes' => 0]);

    $list = MarketingList::create(['name' => 'Trigger list', 'type' => 'static']);
    $contact = emailContact('Click', 'click@x.com');
    app(ListService::class)->addContact($list, $contact);

    $campaign = Campaign::create(['name' => 'Trigger campaign', 'channel' => 'email', 'subject' => 'S', 'body_html' => '<p>x</p>', 'marketing_list_id' => $list->id, 'status' => 'draft']);
    app(CampaignService::class)->send($campaign);
    $recipient = CampaignRecipient::where('campaign_id', $campaign->id)->firstOrFail();
    app(CurrentOrganization::class)->forget(); // the tracking route is public

    app(EmailTrackingService::class)->click($recipient->token);
    app(EmailTrackingService::class)->click($recipient->fresh()->token); // repeat click

    app(CurrentOrganization::class)->set($this->org);
    expect(WorkflowEnrollment::where('workflow_id', $workflow->id)->where('contact_id', $contact->id)->count())->toBe(1);
});

it('attributes only post-send wins to the campaign', function () {
    $list = MarketingList::create(['name' => 'Conv list', 'type' => 'static']);
    $converted = emailContact('Won', 'won@x.com');
    $earlier = emailContact('Old', 'old@x.com');
    foreach ([$converted, $earlier] as $contact) {
        app(ListService::class)->addContact($list, $contact);
    }

    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $wonStage = $pipeline->stages()->where('is_won', true)->firstOrFail();
    // A win BEFORE the send: never the campaign's doing.
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $wonStage->id, 'name' => 'Old win', 'value' => 500000, 'status' => 'won', 'contact_id' => $earlier->id, 'closed_at' => now()->subDay()]);

    $campaign = Campaign::create(['name' => 'Conv campaign', 'channel' => 'email', 'subject' => 'S', 'body_html' => '<p>x</p>', 'marketing_list_id' => $list->id, 'status' => 'draft']);
    app(CampaignService::class)->send($campaign);

    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $wonStage->id, 'name' => 'New win', 'value' => 240000, 'status' => 'won', 'contact_id' => $converted->id, 'closed_at' => now()->addHour()]);

    $conversions = app(CampaignService::class)->conversions($campaign->refresh());
    expect($conversions['customers'])->toBe(1)
        ->and($conversions['revenue'])->toBe(240000);
});

it('runs a delayed plain-text sequence through the workflow engine', function () {
    $workflow = Workflow::create(['name' => 'Welcome sequence', 'trigger_type' => 'list_added', 'status' => 'active']);
    WorkflowStep::create(['workflow_id' => $workflow->id, 'position' => 0, 'action_type' => 'send_email', 'action_config' => ['subject' => 'Welcome, {{first_name}}', 'body' => 'Plain text welcome.'], 'delay_minutes' => 0]);
    WorkflowStep::create(['workflow_id' => $workflow->id, 'position' => 1, 'action_type' => 'send_email', 'action_config' => ['subject' => 'Day two', 'body' => 'Plain text follow-up.'], 'delay_minutes' => 1440]);

    $contact = emailContact('Seq', 'seq@x.com');
    $enrollment = app(WorkflowEngine::class)->enroll($workflow, $contact);
    app(WorkflowEngine::class)->processEnrollment($enrollment);
    expect(OutboundMessage::where('contact_id', $contact->id)->count())->toBe(1);

    // A day later the engine delivers step two.
    $this->travel(1441)->minutes();
    app(WorkflowEngine::class)->processEnrollment($enrollment->fresh());

    $messages = OutboundMessage::where('contact_id', $contact->id)->orderBy('id')->get();
    // The stored row keeps the raw template; merge tags render at send time
    // (dispatcher behaviour, tested in the Stage 6 suite).
    expect($messages)->toHaveCount(2)
        ->and($messages[0]->subject)->toContain('Welcome')
        ->and($messages[1]->subject)->toBe('Day two');

    // EMAIL-009: an event campaign reaches its segment like any other.
    $list = MarketingList::create(['name' => 'Event invitees', 'type' => 'static']);
    app(ListService::class)->addContact($list, $contact);
    $event = Campaign::create(['name' => 'Ransomware webinar invite', 'channel' => 'email', 'type' => 'event', 'subject' => 'Join us', 'body_html' => '<p>Register</p>', 'marketing_list_id' => $list->id, 'status' => 'draft']);
    app(CampaignService::class)->send($event);
    expect($event->refresh()->stat_sent)->toBe(1)->and($event->type)->toBe('event');
});
