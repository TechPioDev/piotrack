<?php

declare(strict_types=1);

/**
 * Lead Generation close-out (Phase 7 — LEAD-003/007/009/010..014).
 *
 * Channel classification is deterministic over immutable first-touch data;
 * scoring promotes lead → MQL → SQL forward-only; an assessment booking is a
 * lead like any other meeting type; and a converted phone call becomes a real
 * contact that enters routing and attribution — with no invented email.
 */

use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Call;
use App\Models\CallTrackingNumber;
use App\Models\Contact;
use App\Models\ScoringRule;
use App\Models\Visitor;
use App\Services\Analytics\CallTrackingService;
use App\Services\Sales\LeadScoringService;
use App\Services\Strategy\StrategyInsights;
use App\Support\ChannelClassifier;
use App\Support\CurrentOrganization;
use Illuminate\Support\Str;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('LeadGen Org');
    subscribeOrganization($this->org, 'enterprise');
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('classifies acquisition channels deterministically from first touch', function () {
    // Explicit medium claims win.
    expect(ChannelClassifier::classify('google', 'cpc', null))->toBe('paid')
        ->and(ChannelClassifier::classify('linkedin', 'social', null))->toBe('social')
        ->and(ChannelClassifier::classify('newsletter', 'email', null))->toBe('content')
        ->and(ChannelClassifier::classify('google', 'organic', null))->toBe('organic')
        ->and(ChannelClassifier::classify('partner-site', 'referral', null))->toBe('referral');

    // Source and referrer fallbacks.
    expect(ChannelClassifier::classify('facebook', null, null))->toBe('social')
        ->and(ChannelClassifier::classify(null, null, 'https://www.google.com/search?q=msp'))->toBe('organic')
        ->and(ChannelClassifier::classify(null, null, 'https://chamberofcommerce.test/members'))->toBe('referral')
        ->and(ChannelClassifier::classify(null, null, null))->toBe('direct');
});

it('reports leads by channel from real visitor first-touch data', function () {
    app(CurrentOrganization::class)->set($this->org);
    $lead = Contact::create(['first_name' => 'Paid', 'email' => 'paid@x.test', 'lifecycle_stage' => 'lead']);

    Visitor::create(['visitor_key' => 'v-paid', 'contact_id' => $lead->id, 'utm_source' => 'google', 'utm_medium' => 'cpc', 'first_seen_at' => now(), 'visits' => 1]);
    Visitor::create(['visitor_key' => 'v-org', 'referrer' => 'https://www.bing.com/search', 'first_seen_at' => now(), 'visits' => 1]);
    Visitor::create(['visitor_key' => 'v-dir', 'first_seen_at' => now(), 'visits' => 1]);

    $channels = app(StrategyInsights::class)->leadChannels();

    expect($channels['paid'])->toBe(['visitors' => 1, 'leads' => 1])
        ->and($channels['organic'])->toBe(['visitors' => 1, 'leads' => 0])
        ->and($channels['direct'])->toBe(['visitors' => 1, 'leads' => 0]);
});

it('promotes lead to MQL at the threshold and never demotes (LEAD-003)', function () {
    app(CurrentOrganization::class)->set($this->org);

    ScoringRule::create([
        'name' => 'Mid intent', 'category' => 'demographic', 'attribute' => 'title',
        'operator' => 'contains', 'value' => 'Manager', 'points' => LeadScoringService::MQL_THRESHOLD, 'is_active' => true,
    ]);

    $scoring = app(LeadScoringService::class);

    $lead = Contact::create(['first_name' => 'Mid', 'title' => 'IT Manager', 'lifecycle_stage' => 'lead']);
    expect($scoring->apply($lead)->lifecycle_stage)->toBe('mql');

    // Below the threshold: stays a lead.
    $cold = Contact::create(['first_name' => 'Cold', 'title' => 'Student', 'lifecycle_stage' => 'lead']);
    expect($scoring->apply($cold)->lifecycle_stage)->toBe('lead');

    // A customer is never touched by MQL promotion.
    $customer = Contact::create(['first_name' => 'Cust', 'title' => 'IT Manager', 'lifecycle_stage' => 'customer']);
    expect($scoring->apply($customer)->lifecycle_stage)->toBe('customer');
});

it('treats an assessment booking as a captured lead (LEAD-007)', function () {
    app(CurrentOrganization::class)->set($this->org);
    BookingPage::create([
        'name' => 'IT Assessment', 'slug' => 'free-it-assessment', 'meeting_type' => 'assessment',
        'duration_minutes' => 45, 'is_active' => true, 'user_id' => $this->owner->id,
    ]);
    app(CurrentOrganization::class)->forget();

    $this->post(route('public.booking.book', 'free-it-assessment'), [
        'name' => 'Assessed Andy', 'email' => 'andy@prospect.test',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ]);

    $contact = Contact::withoutGlobalScopes()->where('email', 'andy@prospect.test')->firstOrFail();
    expect($contact->lifecycle_stage)->toBe('lead')
        ->and($contact->lead_source)->toBe('booking')
        ->and(Booking::withoutGlobalScopes()->where('contact_id', $contact->id)->exists())->toBeTrue();
});

it('turns a converted phone call into a real lead without inventing an email (LEAD-009)', function () {
    app(CurrentOrganization::class)->set($this->org);
    $number = CallTrackingNumber::create(['label' => 'Site', 'source' => 'website', 'phone_number' => '+1 215 555 '.Str::random(4)]);
    $call = Call::create([
        'call_tracking_number_id' => $number->id, 'from_number' => '+1 215 555 0142',
        'direction' => 'inbound', 'duration_seconds' => 300, 'status' => 'completed', 'source' => 'website',
    ]);

    $calls = app(CallTrackingService::class);
    $calls->markConverted($call);

    $contact = Contact::where('phone', '+1 215 555 0142')->firstOrFail();
    expect($contact->lifecycle_stage)->toBe('lead')
        ->and($contact->lead_source)->toBe('website')
        ->and($contact->email)->toBeNull()
        ->and((int) $call->fresh()->contact_id)->toBe((int) $contact->id);

    // Converting again links, never duplicates.
    $second = Call::create([
        'call_tracking_number_id' => $number->id, 'from_number' => '+1 215 555 0142',
        'direction' => 'inbound', 'duration_seconds' => 60, 'status' => 'completed', 'source' => 'website',
    ]);
    $calls->markConverted($second);
    expect(Contact::where('phone', '+1 215 555 0142')->count())->toBe(1)
        ->and((int) $second->fresh()->contact_id)->toBe((int) $contact->id);
});
