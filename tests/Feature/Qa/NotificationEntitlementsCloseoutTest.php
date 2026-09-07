<?php

declare(strict_types=1);

/**
 * Notification System + Entitlements close-out (Phase 40 — NOTIF-003/004/005,
 * ENTL-002/004).
 *
 * SMS as an opt-in notification channel over the tested provider seam,
 * org-level Slack/Teams/webhook channels fired once per event, the platform
 * plan × entitlement matrix editor, and live-metered limits enforced at
 * their choke points.
 */

use App\Authorization\Role;
use App\Billing\Entitlements;
use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\File;
use App\Models\Keyword;
use App\Models\NotificationChannel;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workflow;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\LeadCapturedNotification;
use App\Services\Marketing\MessageDispatcher;
use App\Support\CurrentOrganization;
use App\Support\NotificationDispatcher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('NotifEntl Org');
    subscribeOrganization($this->org, 'professional');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('adds SMS as an opt-in notification channel that needs a phone on file', function () {
    $notification = new LeadCapturedNotification('Ada', 'Contact form');
    $user = $this->owner;

    // No phone, no preference: never SMS.
    expect($notification->via($user))->not->toContain(SmsChannel::class);

    // Preference alone is not enough — a phone must be on file.
    $user->notificationPreferences()->create(['category' => $notification->category(), 'channel' => 'sms', 'enabled' => true]);
    expect($notification->via($user->fresh()->loadMissing('notificationPreferences')))->not->toContain(SmsChannel::class);

    // Phone + explicit opt-in: the SMS channel joins in-app and email.
    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name, 'email' => $user->email, 'phone' => '+1-555-0100',
    ])->assertRedirect();
    $fresh = $user->fresh()->loadMissing('notificationPreferences');
    expect($fresh->phone)->toBe('+1-555-0100')
        ->and($notification->via($fresh))->toContain(SmsChannel::class)
        ->and($notification->via($fresh))->toContain('database');

    // Disabling the preference turns it back off — opt-in, never assumed.
    $user->notificationPreferences()->where('channel', 'sms')->update(['enabled' => false]);
    expect($notification->via($user->fresh()->loadMissing('notificationPreferences')))->not->toContain(SmsChannel::class);
});

it('posts one copy per event to the org Slack and signed webhook channels, surviving failures', function () {
    Http::fake([
        'https://93.184.216.34/*' => Http::response(['ok' => true]),
        'https://93.184.216.35/*' => Http::response('boom', 500),
    ]);

    NotificationChannel::create(['kind' => 'slack', 'url' => 'https://93.184.216.34/slack-hook']);
    NotificationChannel::create(['kind' => 'webhook', 'url' => 'https://93.184.216.34/events', 'secret' => 'whk_secret_1']);
    NotificationChannel::create(['kind' => 'teams', 'url' => 'https://93.184.216.35/teams-hook']); // fails, must not break
    NotificationChannel::create(['kind' => 'slack', 'url' => 'https://93.184.216.34/inactive', 'is_active' => false]);

    // One org-level event → one post per active channel, however many owners.
    app(NotificationDispatcher::class)->toOrganizationOwners($this->org, new LeadCapturedNotification('Ada L.', 'Pricing form'));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://93.184.216.34/slack-hook'
            && str_contains($request['text'], '*New lead captured*')
            && str_contains($request['text'], 'Ada L.');
    });
    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://93.184.216.34/events') {
            return false;
        }
        $expected = hash_hmac('sha256', $request->body(), 'whk_secret_1');

        return $request->hasHeader('X-Piotrack-Signature', $expected)
            && $request['category'] === 'leads'
            && $request['title'] === 'New lead captured';
    });
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'inactive'));

    // The failed Teams post was audited, not thrown.
    expect(AuditLog::where('action', 'notifications.channel.failed')->exists())->toBeTrue();

    // CRUD: https enforced; a plain member cannot manage channels.
    $this->actingAs($this->owner)->from(route('organization.edit'))
        ->post(route('organization.channels.store'), ['kind' => 'slack', 'url' => 'http://insecure.example/hook'])
        ->assertRedirect()->assertSessionHasErrors('url');

    $rep = addMember($this->org, Role::SalesRepresentative);
    $this->actingAs($rep)->post(route('organization.channels.store'), ['kind' => 'slack', 'url' => 'https://93.184.216.34/x'])
        ->assertForbidden();
});

it('lets platform staff edit the plan entitlement matrix, and tenants pick it up', function () {
    $staff = User::factory()->create(['platform_role' => Role::PlatformSuperAdmin->value]);
    $plan = Plan::where('code', 'professional')->firstOrFail();

    $this->actingAs($staff)->get(route('platform.plans'))->assertOk();

    // Turn a feature off and pin a limit for the plan.
    $this->actingAs($staff)->post(route('platform.plans.entitlements', $plan), [
        'key' => 'advertising', 'kind' => 'feature', 'bool_value' => false,
    ])->assertRedirect();
    $this->actingAs($staff)->post(route('platform.plans.entitlements', $plan), [
        'key' => 'keywords', 'kind' => 'limit', 'int_value' => 5,
    ])->assertRedirect();

    $entitlements = app(Entitlements::class);
    $entitlements->forget($this->org);
    expect($entitlements->feature($this->org, 'advertising'))->toBeFalse()
        ->and($entitlements->limit($this->org, 'keywords'))->toBe(5);

    // A tenant owner is not platform staff.
    $this->actingAs($this->owner)->get(route('platform.plans'))->assertForbidden();
});

it('meters stock limits live and enforces them at the choke points', function () {
    $plan = Plan::where('code', 'professional')->firstOrFail();
    foreach (['contacts' => 1, 'keywords' => 1, 'automations' => 1, 'sms' => 0, 'api_calls' => 1] as $key => $value) {
        $plan->entitlements()->updateOrCreate(['key' => $key], ['kind' => 'limit', 'bool_value' => null, 'int_value' => $value]);
    }
    app(Entitlements::class)->forget($this->org);

    // Contacts: the first fits, the second is refused with the plan message.
    $this->actingAs($this->owner)->post(route('crm.contacts.store'), ['first_name' => 'One', 'email' => 'one@x.com'])->assertRedirect();
    $this->actingAs($this->owner)->from(route('crm.contacts.index'))
        ->post(route('crm.contacts.store'), ['first_name' => 'Two', 'email' => 'two@x.com'])
        ->assertRedirect()->assertSessionHasErrors('email');
    expect(Contact::count())->toBe(1);

    // Keywords.
    $this->actingAs($this->owner)->post(route('seo.keywords.store'), ['phrase' => 'msp dallas', 'intent' => 'commercial'])->assertRedirect();
    $this->actingAs($this->owner)->from(route('seo.keywords.index'))
        ->post(route('seo.keywords.store'), ['phrase' => 'msp plano', 'intent' => 'commercial'])
        ->assertRedirect()->assertSessionHasErrors('phrase');
    expect(Keyword::count())->toBe(1);

    // Automations.
    $this->actingAs($this->owner)->post(route('marketing.automation.store'), ['name' => 'W1', 'trigger_type' => 'list_added'])->assertRedirect();
    $this->actingAs($this->owner)->from(route('marketing.automation.index'))
        ->post(route('marketing.automation.store'), ['name' => 'W2', 'trigger_type' => 'list_added'])
        ->assertRedirect()->assertSessionHasErrors('name');
    expect(Workflow::count())->toBe(1);

    // SMS allowance 0: dispatch refuses before the provider, recorded honestly.
    $contact = Contact::firstOrFail();
    $contact->update(['phone' => '+15550100', 'sms_opt_in' => true]);
    $message = app(MessageDispatcher::class)->sendSms($contact, 'Hello');
    expect($message->status)->toBe('failed')->and($message->error)->toBe('limit_reached');

    // API calls: the first is served and counted, the second gets a 429.
    $token = $this->owner->createToken('t')->plainTextToken;
    $headers = ['Authorization' => 'Bearer '.$token, 'X-Organization-Id' => (string) $this->org->id];
    $this->getJson('/api/v1/contacts', $headers)->assertOk();
    expect(app(UsageMeter::class)->usage($this->org, Limit::ApiCalls))->toBe(1);
    $this->getJson('/api/v1/contacts', $headers)->assertStatus(429);

    // Storage meters live from real file rows (MB, rounded up).
    File::create(['uploaded_by' => $this->owner->id, 'disk' => 'local', 'path' => 'x/a.pdf', 'name' => 'a.pdf', 'mime' => 'application/pdf', 'size' => 3 * 1_048_576]);
    expect(app(UsageMeter::class)->usage($this->org, Limit::StorageMb))->toBe(3)
        ->and(app(UsageMeter::class)->withinLimit($this->org, Limit::StorageMb, 3))->toBeTrue();
});
