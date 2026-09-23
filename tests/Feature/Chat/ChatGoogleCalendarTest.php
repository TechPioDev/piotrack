<?php

declare(strict_types=1);

/**
 * The same booking behaviour for a team on Google Workspace: their real
 * free/busy respected, the meeting in their calendar with the visitor invited,
 * and a Google Meet link on it.
 *
 * Booking must not care which calendar a customer runs, so the interesting
 * cases are the ones where the two differ: which provider a meeting was created
 * in (and so who to tell when it moves), and a tenant who has connected both.
 */

use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Integration;
use App\Services\Calendar\GoogleCalendar;
use App\Services\Calendar\MicrosoftCalendar;
use App\Services\Chat\ChatBookingSlots;
use App\Services\Integrations\OAuthFlow;
use App\Services\Sales\BookingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');
    Cache::flush();

    app(CurrentOrganization::class)->set($this->org);
    $this->page = BookingPage::create([
        'user_id' => $this->owner->id,
        'name' => 'Discovery call',
        'slug' => 'discovery-call',
        'meeting_type' => 'call',
        'duration_minutes' => 30,
        'is_active' => true,
        'availability' => ['days' => [1, 2, 3, 4, 5], 'start' => '09:00', 'end' => '17:00'],
    ]);
    app(CurrentOrganization::class)->forget();
});

function connectCalendar($test, string $provider, string $account): Integration
{
    app(CurrentOrganization::class)->set($test->org);
    $integration = Integration::create([
        'provider' => $provider,
        'name' => $provider,
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'token-'.$provider,
            'refresh_token' => 'refresh-'.$provider,
            'expires_at' => now()->addHour()->toIso8601String(),
        ],
        'settings' => ['account' => $account],
    ]);
    app(CurrentOrganization::class)->forget();

    return $integration;
}

/**
 * One responder for every Google call. As with Graph, re-faking the same URL
 * pattern later in a test does nothing, so the test drives a variable instead.
 *
 * @param  list<array{start: string, end: string}>  $busy
 */
function googleAnswers(array &$busy, ?array $event = null): void
{
    Http::fake(function ($request) use (&$busy, $event) {
        if (str_contains($request->url(), 'freeBusy')) {
            return Http::response(['calendars' => ['sales@piomanage.test' => ['busy' => $busy]]]);
        }

        if (str_contains($request->url(), 'graph.microsoft.com')) {
            return Http::response(str_contains($request->url(), 'getSchedule') ? ['value' => []] : ['id' => 'ms_1', 'onlineMeeting' => ['joinUrl' => 'https://teams.microsoft.com/l/x']]);
        }

        return Http::response($event ?? []);
    });
}

function bookThrough($test): Booking
{
    app(CurrentOrganization::class)->set($test->org);
    $booking = app(BookingService::class)->book($test->page, [
        'name' => 'Ram Singh',
        'email' => 'ram@techpio.com',
        'scheduled_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
        'source' => 'website_chat',
    ]);
    app(CurrentOrganization::class)->forget();

    return $booking;
}

it('books into Google Calendar with a Meet link and the visitor invited', function () {
    connectCalendar($this, GoogleCalendar::PROVIDER, 'sales@piomanage.test');
    $busy = [];
    googleAnswers($busy, ['id' => 'evt_9f3', 'hangoutLink' => 'https://meet.google.com/abc-defg-hij']);

    $booking = bookThrough($this);

    expect($booking->calendar_provider)->toBe(GoogleCalendar::PROVIDER);
    expect($booking->calendar_event_id)->toBe('evt_9f3');
    expect($booking->meeting_url)->toBe('https://meet.google.com/abc-defg-hij');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/calendars/primary/events')) {
            return false;
        }

        return $request['conferenceData']['createRequest']['conferenceSolutionKey']['type'] === 'hangoutsMeet'
            && collect($request['attendees'])->pluck('email')->contains('ram@techpio.com')
            // Google only creates the link when asked to at this version.
            && str_contains($request->url(), 'conferenceDataVersion=1');
    });
});

it('does not offer a time Google says the team is busy', function () {
    connectCalendar($this, GoogleCalendar::PROVIDER, 'sales@piomanage.test');
    $busy = [];
    googleAnswers($busy);

    app(CurrentOrganization::class)->set($this->org);
    $free = app(ChatBookingSlots::class)->available($this->page);

    $day = CarbonImmutable::parse($free[0]['at'])->startOfDay();
    $busy = [['start' => $day->toRfc3339String(), 'end' => $day->setTime(23, 59)->toRfc3339String()]];
    Cache::flush();
    $after = app(ChatBookingSlots::class)->available($this->page);
    app(CurrentOrganization::class)->forget();

    expect(collect($free)->filter(fn ($slot) => CarbonImmutable::parse($slot['at'])->isSameDay($day)))->not->toBeEmpty();
    expect(collect($after)->filter(fn ($slot) => CarbonImmutable::parse($slot['at'])->isSameDay($day)))->toBeEmpty();
});

it('tells Google, not Microsoft, when a Google meeting moves or is called off', function () {
    connectCalendar($this, MicrosoftCalendar::PROVIDER, 'sales@piomanage.test');
    connectCalendar($this, GoogleCalendar::PROVIDER, 'sales@piomanage.test');
    $busy = [];
    googleAnswers($busy, ['id' => 'evt_9f3']);

    app(CurrentOrganization::class)->set($this->org);
    $booking = Booking::create([
        'booking_page_id' => $this->page->id,
        'owner_id' => $this->owner->id,
        'name' => 'Ram Singh',
        'email' => 'ram@techpio.com',
        'scheduled_at' => now()->addDays(2)->setTime(10, 0),
        'status' => 'booked',
        'source' => 'website_chat',
        'ics_token' => 'tok',
        'calendar_provider' => GoogleCalendar::PROVIDER,
        'calendar_event_id' => 'evt_9f3',
    ]);

    app(BookingService::class)->reschedule($booking, now()->addDays(3)->setTime(15, 0));
    app(BookingService::class)->setStatus($booking, 'cancelled');
    app(CurrentOrganization::class)->forget();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && str_contains($request->url(), 'googleapis.com'));
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'googleapis.com'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.microsoft.com'));
});

it('checks both diaries when a tenant runs both, and creates the meeting in one', function () {
    connectCalendar($this, MicrosoftCalendar::PROVIDER, 'sales@piomanage.test');
    connectCalendar($this, GoogleCalendar::PROVIDER, 'hello@piomanage.test');
    $busy = [];
    googleAnswers($busy);

    // Offering times is what consults the diaries; booking then holds the hour.
    app(CurrentOrganization::class)->set($this->org);
    app(ChatBookingSlots::class)->available($this->page);
    app(CurrentOrganization::class)->forget();

    $booking = bookThrough($this);

    // Both were asked about free/busy; the meeting went to the first connected.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'getSchedule'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'freeBusy'));
    expect($booking->calendar_provider)->toBe(MicrosoftCalendar::PROVIDER);
    expect($booking->meeting_url)->toContain('teams.microsoft.com');
});

it('asks Google for a refresh token, which it only gives when asked', function () {
    config([
        'services.connectors.google_calendar.client_id' => 'id',
        'services.connectors.google_calendar.client_secret' => 'secret',
    ]);

    $url = app(OAuthFlow::class)->authorizeUrl(GoogleCalendar::PROVIDER, 'state-123');

    expect($url)->toContain('access_type=offline')
        ->and($url)->toContain('prompt=consent')
        ->and($url)->toContain('calendar.events');
});
