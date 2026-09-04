<?php

declare(strict_types=1);

/**
 * GEO + SMS Automation close-out (Phase 30 — GEO-011..016, SMS-002/003/006).
 *
 * Attendee SMS reminders ride the existing daily booking-reminder command
 * (consent enforced by the dispatcher, event-aware copy); GEO dimension gaps
 * become recommendations citing their own numbers; cited sources close the
 * loop into the tested outreach pipeline.
 */

use App\Models\AiPrompt;
use App\Models\AiVisibilityCheck;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Contact;
use App\Models\OutboundMessage;
use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use App\Services\Ai\AiVisibilityDashboard;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('GeoSms Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function geoBooking(string $meetingType, ?Contact $contact, string $name): Booking
{
    $page = BookingPage::create(['name' => $name.' page', 'slug' => 'gs-'.uniqid(), 'meeting_type' => $meetingType, 'duration_minutes' => 30, 'assignment' => 'fixed', 'is_active' => true]);

    return Booking::create([
        'booking_page_id' => $page->id, 'contact_id' => $contact?->id, 'name' => $name,
        'email' => $contact->email ?? 'anon@x.com', 'status' => 'booked', 'scheduled_at' => now()->addHours(6),
    ]);
}

it('sends attendee SMS reminders with event-aware copy, respecting consent', function () {
    $consenting = Contact::create(['first_name' => 'Ada', 'email' => 'ada@x.com', 'phone' => '+15550001', 'sms_opt_in' => true]);
    $optedOut = Contact::create(['first_name' => 'Bo', 'email' => 'bo@x.com', 'phone' => '+15550002', 'sms_opt_in' => false]);
    $noPhone = Contact::create(['first_name' => 'Cy', 'email' => 'cy@x.com']);

    geoBooking('consultation', $consenting, 'Fit call');
    geoBooking('webinar', $consenting, 'Ransomware webinar');
    geoBooking('consultation', $optedOut, 'Quiet call');
    geoBooking('consultation', $noPhone, 'Phoneless call');
    app(CurrentOrganization::class)->forget();

    $this->artisan('sales:send-booking-reminders')->assertExitCode(0);

    app(CurrentOrganization::class)->set($this->org);
    $sms = OutboundMessage::where('channel', 'sms')->where('source', 'booking_reminder')->get();

    // SMS-002: the appointment reminder, SMS-006: the event reminder.
    $appointment = $sms->first(fn (OutboundMessage $m) => str_contains($m->body, 'Fit call'));
    $event = $sms->first(fn (OutboundMessage $m) => str_contains($m->body, 'Ransomware webinar'));
    expect($appointment->status)->toBe('sent')
        ->and($appointment->body)->toContain('appointment')
        ->and($event->status)->toBe('sent')
        ->and($event->body)->toContain('event');

    // The opted-out contact is recorded as suppressed, never sent; the
    // phoneless contact produces no SMS attempt at all.
    $suppressed = $sms->first(fn (OutboundMessage $m) => str_contains($m->body, 'Quiet call'));
    expect($suppressed->status)->toBe('failed')->and($suppressed->error)->toBe('suppressed')
        ->and($sms->filter(fn (OutboundMessage $m) => str_contains($m->body, 'Phoneless'))->count())->toBe(0);
});

it('recommends against weak visibility dimensions, citing the numbers, and stays silent on strong ones', function () {
    $philly = AiPrompt::create(['text' => 'best msp in philadelphia', 'category' => 'city', 'city' => 'Philadelphia', 'is_active' => true]);
    $security = AiPrompt::create(['text' => 'best managed security provider', 'category' => 'service', 'service' => 'Cybersecurity', 'is_active' => true]);

    // Philadelphia: 0 of 2 mentions — weak. Cybersecurity: 2 of 2 — strong.
    foreach ([false, false] as $mentioned) {
        AiVisibilityCheck::create(['ai_prompt_id' => $philly->id, 'prompt' => $philly->text, 'engine' => 'chatgpt', 'provider' => 'fixture', 'brand' => 'Acme', 'mentioned' => $mentioned, 'checked_at' => now()]);
    }
    foreach ([true, true] as $mentioned) {
        AiVisibilityCheck::create(['ai_prompt_id' => $security->id, 'prompt' => $security->text, 'engine' => 'chatgpt', 'provider' => 'fixture', 'brand' => 'Acme', 'mentioned' => $mentioned, 'checked_at' => now()]);
    }

    $recommendations = collect(app(AiVisibilityDashboard::class)->dimensionRecommendations());

    $city = $recommendations->firstWhere('value', 'Philadelphia');
    expect($city['dimension'])->toBe('city')
        ->and($city['evidence'])->toContain('0% of 2 checks')
        ->and($city['action'])->toContain('location page');

    // Strong service: no recommendation for it.
    expect($recommendations->where('value', 'Cybersecurity'))->toHaveCount(0);

    // No prompt carries a vertical at all: the variant gap is called out.
    $vertical = $recommendations->firstWhere('dimension', 'vertical');
    expect($vertical['value'])->toBeNull()
        ->and($vertical['action'])->toContain('variants');
});

it('aggregates cited sources with covered/gap status and targets gaps through outreach, idempotently', function () {
    $prompt = AiPrompt::create(['text' => 'top msp companies', 'category' => 'general', 'is_active' => true]);
    AiVisibilityCheck::create(['ai_prompt_id' => $prompt->id, 'prompt' => $prompt->text, 'engine' => 'chatgpt', 'provider' => 'fixture', 'brand' => 'Acme', 'mentioned' => true, 'cited_sources' => ['https://www.clutch.co/msp-list', 'https://reddit.com/r/msp/thread'], 'checked_at' => now()]);
    AiVisibilityCheck::create(['ai_prompt_id' => $prompt->id, 'prompt' => $prompt->text, 'engine' => 'perplexity', 'provider' => 'fixture', 'brand' => 'Acme', 'mentioned' => false, 'cited_sources' => ['https://clutch.co/other-page'], 'checked_at' => now()]);

    // clutch.co is already worked through outreach: covered.
    $campaign = OutreachCampaign::create(['name' => 'Existing PR', 'type' => 'digital_pr', 'status' => 'active']);
    OutreachProspect::create(['outreach_campaign_id' => $campaign->id, 'name' => 'Clutch', 'domain' => 'clutch.co', 'status' => 'pitched']);

    $sources = collect(app(AiVisibilityDashboard::class)->citationSources());
    $clutch = $sources->firstWhere('host', 'clutch.co');
    $reddit = $sources->firstWhere('host', 'reddit.com');

    expect($clutch['citations'])->toBe(2) // www-stripped + deduped across checks
        ->and($clutch['status'])->toBe('covered')
        ->and($reddit['citations'])->toBe(1)
        ->and($reddit['status'])->toBe('gap');

    // Targeting the gap creates the campaign + prospect once, then reuses them.
    app(CurrentOrganization::class)->forget();
    $this->actingAs($this->owner)->post(route('seo.ai.target-source'), ['host' => 'reddit.com'])
        ->assertRedirect()->assertSessionHas('status');
    $this->actingAs($this->owner)->post(route('seo.ai.target-source'), ['host' => 'reddit.com'])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    expect(OutreachCampaign::where('name', 'AI citation sources')->count())->toBe(1)
        ->and(OutreachProspect::where('domain', 'reddit.com')->count())->toBe(1)
        ->and(collect(app(AiVisibilityDashboard::class)->citationSources())->firstWhere('host', 'reddit.com')['status'])->toBe('covered');
});
