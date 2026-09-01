<?php

declare(strict_types=1);

/**
 * Register close-out sprint 1 (Module 12): the small lagging register modules.
 *
 * ALERT-001..004: sales alerts reach owners in-app + email, a CRM timeline
 * entry, and the org's configured SMS number and Slack/Teams webhook.
 * ALERT-006..009: repeat visits, deep content reading, bottom-funnel pages and
 * booked meetings fire event alerts for KNOWN contacts only.
 * SRCH-001/002: global search spans leads/campaigns/content; recent terms are
 * remembered per user. BOOK-010: a tracked visitor's booking links the contact
 * to the browsing trail so first-touch attribution survives into the meeting.
 */

use App\Authorization\Role;
use App\Messaging\Contracts\SmsProvider;
use App\Messaging\MessagingProviderManager;
use App\Messaging\SentResult;
use App\Messaging\SmsMessage;
use App\Models\Activity;
use App\Models\BookingPage;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\SalesAlert;
use App\Models\Visitor;
use App\Notifications\SalesAlertNotification;
use App\Services\Sales\AlertService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Closeout Org');
    subscribeOrganization($this->org, 'enterprise');
    $this->org->forceFill(['tracking_key' => 'tk_'.Str::lower(Str::random(24))])->save();
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function sprintTrack($test, array $payload)
{
    return $test->postJson('/t/'.$test->org->tracking_key.'/e', array_merge(['vid' => 'closeout1234567890ab'], $payload));
}

// ---- ALERT-001/002/003/004: delivery channels ----

it('delivers a sales alert in-app, by email, to the CRM timeline, by SMS and to a webhook', function () {
    Notification::fake();
    Http::fake(['hooks.example.test/*' => Http::response(['ok' => true])]);

    $sms = new class implements SmsProvider
    {
        public array $sent = [];

        public function send(SmsMessage $message): SentResult
        {
            $this->sent[] = $message;

            return SentResult::accepted('fake-'.count($this->sent));
        }
    };
    app()->instance(SmsProvider::class, $sms);
    app()->bind(MessagingProviderManager::class, function ($app) use ($sms) {
        return new class($sms) extends MessagingProviderManager
        {
            public function __construct(private SmsProvider $fake) {}

            public function sms(?string $name = null): SmsProvider
            {
                return $this->fake;
            }
        };
    });

    $this->org->update(['alert_channels' => [
        'sms_to' => '+1 555 0100',
        'webhook_url' => 'https://hooks.example.test/T000/B000',
    ]]);

    $contact = Contact::create(['first_name' => 'Casey', 'email' => 'casey@closeout.test', 'lifecycle_stage' => 'lead']);

    expect(app(AlertService::class)->fire('meeting_request', $contact))->toBeTrue();

    // In-app + email to owners (ALERT-001).
    Notification::assertSentTo($this->owner, SalesAlertNotification::class);

    // CRM timeline entry on the contact (ALERT-003).
    expect(Activity::where('subject_type', 'contact')->where('subject_id', $contact->id)
        ->where('title', 'like', 'Sales alert:%')->exists())->toBeTrue();

    // SMS through the messaging provider (ALERT-002).
    expect($sms->sent)->toHaveCount(1)
        ->and($sms->sent[0]->toPhone)->toBe('+1 555 0100');

    // Slack/Teams webhook (ALERT-004).
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://hooks.example.test/')
        && str_contains((string) $request->body(), 'requested a meeting'));

    // Deduped while unread — nothing fires twice.
    expect(app(AlertService::class)->fire('meeting_request', $contact))->toBeFalse();
});

it('saves and permission-gates the alert delivery channel settings', function () {
    $this->actingAs($this->owner)
        ->put(route('sales.alerts.channels'), ['sms_to' => '+15550123', 'webhook_url' => 'https://hooks.example.test/x'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($this->org->fresh()->alert_channels['sms_to'])->toBe('+15550123');

    $this->actingAs($this->owner)
        ->put(route('sales.alerts.channels'), ['webhook_url' => 'http://insecure.example.test/x'])
        ->assertSessionHasErrors('webhook_url'); // https only

    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)->put(route('sales.alerts.channels'), ['sms_to' => '+15550999'])->assertForbidden();
});

// ---- ALERT-006/007/008/009: event-fired alerts ----

it('fires a repeat-visit alert when a known contact starts another session', function () {
    $contact = Contact::create(['first_name' => 'Riley', 'email' => 'riley@closeout.test', 'lifecycle_stage' => 'lead']);

    sprintTrack($this, ['type' => 'pageview', 'path' => '/home']);
    sprintTrack($this, ['type' => 'identify', 'email' => 'riley@closeout.test']);
    expect(SalesAlert::where('type', 'repeat_visit')->count())->toBe(0);

    // Second session: 31 quiet minutes later.
    Visitor::withoutGlobalScope('tenant')->firstOrFail()
        ->forceFill(['last_seen_at' => now()->subMinutes(31)])->save();
    sprintTrack($this, ['type' => 'pageview', 'path' => '/services']);

    expect(SalesAlert::where('contact_id', $contact->id)->where('type', 'repeat_visit')->count())->toBe(1);
});

it('fires bottom-funnel and content-engagement alerts for known contacts only', function () {
    $contact = Contact::create(['first_name' => 'Drew', 'email' => 'drew@closeout.test', 'lifecycle_stage' => 'lead']);

    // Anonymous browsing never alerts — the visitor is not identified yet.
    sprintTrack($this, ['type' => 'pageview', 'path' => '/pricing']);
    app(CurrentOrganization::class)->set($this->org);
    expect(SalesAlert::count())->toBe(0);

    sprintTrack($this, ['type' => 'identify', 'email' => 'drew@closeout.test']);

    // Bottom-funnel page fires at once (ALERT-008).
    sprintTrack($this, ['type' => 'pageview', 'path' => '/pricing']);
    app(CurrentOrganization::class)->set($this->org);
    expect(SalesAlert::where('contact_id', $contact->id)->where('type', 'bottom_funnel')->count())->toBe(1);

    // Content engagement fires at the third read (ALERT-007).
    sprintTrack($this, ['type' => 'pageview', 'path' => '/blog/one']);
    sprintTrack($this, ['type' => 'pageview', 'path' => '/blog/two']);
    app(CurrentOrganization::class)->set($this->org);
    expect(SalesAlert::where('type', 'content_engagement')->count())->toBe(0);
    sprintTrack($this, ['type' => 'pageview', 'path' => '/blog/three']);
    app(CurrentOrganization::class)->set($this->org);
    expect(SalesAlert::where('contact_id', $contact->id)->where('type', 'content_engagement')->count())->toBe(1);
});

it('fires a meeting-request alert when a booking is made', function () {
    $page = BookingPage::create([
        'name' => 'Intro call', 'slug' => 'closeout-intro', 'meeting_type' => 'consultation',
        'duration_minutes' => 30, 'is_active' => true, 'user_id' => $this->owner->id,
    ]);

    $this->post(route('public.booking.book', 'closeout-intro'), [
        'name' => 'Morgan Lee', 'email' => 'morgan@client.test',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]);

    $contact = Contact::where('email', 'morgan@client.test')->firstOrFail();
    expect(SalesAlert::where('contact_id', $contact->id)->where('type', 'meeting_request')->count())->toBe(1);
});

// ---- BOOK-010: meeting source attribution ----

it('links a tracked visitor to the contact created by their booking', function () {
    sprintTrack($this, ['type' => 'pageview', 'path' => '/services', 'utm_source' => 'google', 'utm_campaign' => 'cmmc']);
    app(CurrentOrganization::class)->set($this->org);

    $page = BookingPage::create([
        'name' => 'Intro call', 'slug' => 'closeout-attrib', 'meeting_type' => 'consultation',
        'duration_minutes' => 30, 'is_active' => true, 'user_id' => $this->owner->id,
    ]);

    $this->withUnencryptedCookie('_pt_vid', 'closeout1234567890ab')
        ->post(route('public.booking.book', 'closeout-attrib'), [
            'name' => 'Jamie Fox', 'email' => 'jamie@client.test',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ]);

    $contact = Contact::where('email', 'jamie@client.test')->firstOrFail();
    $visitor = Visitor::withoutGlobalScope('tenant')->where('visitor_key', 'closeout1234567890ab')->firstOrFail();

    expect($visitor->contact_id)->toBe($contact->id)
        ->and($visitor->utm_source)->toBe('google')   // first touch survives
        ->and($visitor->utm_campaign)->toBe('cmmc');
});

// ---- SRCH-001/002 ----

it('searches leads, campaigns and content, and remembers recent terms', function () {
    Contact::create(['first_name' => 'Quinn', 'last_name' => 'Searchable', 'email' => 'quinn@closeout.test', 'lifecycle_stage' => 'mql']);
    Campaign::create(['name' => 'Searchable Spring Push', 'type' => 'email', 'status' => 'draft']);
    ContentPiece::create(['title' => 'Searchable CMMC Guide', 'slug' => 'searchable-cmmc-guide', 'content_type' => 'blog', 'status' => 'draft']);

    $response = $this->actingAs($this->owner)->getJson('/search?q=Searchable')->assertOk();
    $labels = array_column($response->json('groups'), 'label');

    expect($labels)->toContain('Leads')->toContain('Campaigns')->toContain('Content')
        ->and($response->json('recent'))->toContain('Searchable');

    // Empty query serves the remembered terms for the palette.
    $this->actingAs($this->owner)->getJson('/search?q=')
        ->assertOk()->assertJsonPath('recent.0', 'Searchable');
});
