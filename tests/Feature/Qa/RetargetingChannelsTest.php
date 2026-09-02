<?php

declare(strict_types=1);

/**
 * Retargeting Engine close-out (Phase 16 — RETG-001..005, 009, 010).
 *
 * Platform-ready customer-match exports (the CSV each Ads UI accepts for a
 * manual customer-list upload) and SMS re-engagement driven from an audience
 * through the existing consent-enforcing SMS engine. Video/YouTube rows stay
 * Planned (they need video-campaign connectors, not a customer list).
 */

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\OutboundMessage;
use App\Models\RetargetingAudience;
use App\Models\Suppression;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Retarget Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);

    $this->audience = RetargetingAudience::create([
        'name' => 'Warm leads', 'source' => 'funnel_stage',
        'rules' => ['lifecycle_stage' => 'lead'], 'exclude_converted' => true,
    ]);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('exports the platform customer-match CSV with hashed members only', function () {
    Contact::create(['first_name' => 'In', 'email' => 'In.Audience@Example.com', 'lifecycle_stage' => 'lead']);
    Contact::create(['first_name' => 'NoMail', 'phone' => '+1215550000', 'lifecycle_stage' => 'lead']);
    Contact::create(['first_name' => 'Customer', 'email' => 'won@example.com', 'lifecycle_stage' => 'customer']);
    app(CurrentOrganization::class)->forget();

    $response = $this->actingAs($this->owner)
        ->get(route('ads.retargeting.export', $this->audience).'?platform=google')
        ->assertOk()
        ->assertDownload('warm-leads-google-customer-match.csv');

    $csv = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", $csv)));

    // Google's template header, one hashed row: the customer is excluded
    // (RETG-016 exclusion honoured in the export) and the mailless contact
    // cannot be matched by email.
    expect($lines[0])->toBe('Email')
        ->and($lines)->toHaveCount(2)
        ->and($lines[1])->toBe(hash('sha256', 'in.audience@example.com'));

    // Meta and LinkedIn use their own lowercase header.
    $meta = $this->actingAs($this->owner)->get(route('ads.retargeting.export', $this->audience).'?platform=meta')->streamedContent();
    expect(explode("\n", $meta)[0])->toBe('email');

    app(CurrentOrganization::class)->set($this->org);
    expect(AuditLog::where('action', 'ads.retargeting.exported')->count())->toBe(2);
});

it('drives SMS re-engagement through the consent-enforcing engine with honest counts', function () {
    config(['marketing.sms_provider' => 'log']);

    Contact::create(['first_name' => 'Optin', 'email' => 'a@x.test', 'phone' => '+12155550001', 'sms_opt_in' => true, 'lifecycle_stage' => 'lead']);
    Contact::create(['first_name' => 'Optout', 'email' => 'b@x.test', 'phone' => '+12155550002', 'sms_opt_in' => false, 'lifecycle_stage' => 'lead']);
    Contact::create(['first_name' => 'Phoneless', 'email' => 'c@x.test', 'sms_opt_in' => true, 'lifecycle_stage' => 'lead']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)
        ->post(route('ads.retargeting.sms', $this->audience), ['message' => 'We still have your assessment ready — reply YES.'])
        ->assertRedirect()
        ->assertSessionHas('status', fn (string $status) => str_contains($status, '1 SMS sent')
            && str_contains($status, '3 targeted')
            && str_contains($status, '1 suppressed')
            && str_contains($status, '1 without a phone'));

    app(CurrentOrganization::class)->set($this->org);
    $messages = OutboundMessage::where('channel', 'sms')->where('source', 'retargeting')->get();

    expect($messages)->toHaveCount(2) // opted-in sent + opted-out recorded as failed
        ->and($messages->firstWhere('address', '+12155550001')->status)->toBe('sent')
        ->and($messages->firstWhere('address', '+12155550002')->status)->toBe('failed')
        ->and($messages->firstWhere('address', '+12155550002')->error)->toBe('suppressed')
        ->and(AuditLog::where('action', 'ads.retargeting.sms_sent')->exists())->toBeTrue();
});

it('respects an explicit suppression even for an opted-in member', function () {
    config(['marketing.sms_provider' => 'log']);

    Contact::create(['first_name' => 'Blocked', 'email' => 'd@x.test', 'phone' => '+12155550003', 'sms_opt_in' => true, 'lifecycle_stage' => 'lead']);
    Suppression::create(['channel' => 'sms', 'address' => '+12155550003', 'reason' => 'complaint']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)
        ->post(route('ads.retargeting.sms', $this->audience), ['message' => 'Hello again'])
        ->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    expect(OutboundMessage::where('address', '+12155550003')->value('status'))->toBe('failed');
});

it('gates both endpoints and isolates tenants', function () {
    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)->get(route('ads.retargeting.export', $this->audience).'?platform=google')->assertForbidden();
    $this->actingAs($viewer)->post(route('ads.retargeting.sms', $this->audience), ['message' => 'x'])->assertForbidden();

    // An unknown platform is a validation error, not a file.
    $this->actingAs($this->owner)
        ->from(route('ads.retargeting.index'))
        ->get(route('ads.retargeting.export', $this->audience).'?platform=tiktok')
        ->assertRedirect(route('ads.retargeting.index'));

    [, $otherOwner] = makeOrganization('Other Org');
    $this->actingAs($otherOwner)->get(route('ads.retargeting.export', $this->audience).'?platform=google')->assertNotFound();
    $this->actingAs($otherOwner)->post(route('ads.retargeting.sms', $this->audience), ['message' => 'x'])->assertNotFound();
});
