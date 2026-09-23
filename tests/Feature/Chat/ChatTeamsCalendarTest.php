<?php

declare(strict_types=1);

/**
 * Booking against the team's real Microsoft 365 calendar.
 *
 * Before this, a booking lived only in Piotrack: the chat offered times from an
 * availability grid without ever asking whether the salesperson was free, and
 * nothing appeared in Outlook or Teams unless somebody clicked an .ics link.
 * Connected, the chat respects their diary, the meeting lands in it with the
 * visitor invited, and Microsoft puts a Teams link on it.
 *
 * The other half of this is what happens when Microsoft is not there at all -
 * no connection, expired consent, an outage. A visitor's booking must never
 * depend on Graph answering.
 */

use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Integration;
use App\Services\Calendar\MicrosoftCalendar;
use App\Services\Chat\ChatBookingSlots;
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

/** A tenant that has connected Microsoft 365. */
function connectMicrosoft($test, string $account = 'sales@piomanage.test'): Integration
{
    app(CurrentOrganization::class)->set($test->org);
    $integration = Integration::create([
        'provider' => MicrosoftCalendar::PROVIDER,
        'name' => 'Microsoft 365 / Outlook',
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'graph-token',
            'refresh_token' => 'graph-refresh',
            'expires_at' => now()->addHour()->toIso8601String(),
        ],
        'settings' => ['account' => $account],
    ]);
    app(CurrentOrganization::class)->forget();

    return $integration;
}

/**
 * One responder for every Graph call, answering from variables the test owns.
 *
 * Http::fake keeps the first stub registered for a pattern, so re-faking the
 * same URL later in a test silently changes nothing - which is exactly the trap
 * this used to fall into. A single closure, reading state by reference, always
 * answers with what the test means right now.
 *
 * @param  list<array{start: string, end: string}>  $busy
 */
function graphAnswers(array &$busy, ?array $event = null): void
{
    Http::fake(function ($request) use (&$busy, $event) {
        if (str_contains($request->url(), 'getSchedule')) {
            return Http::response(['value' => [[
                'scheduleId' => 'sales@piomanage.test',
                'scheduleItems' => array_map(fn (array $window) => [
                    'status' => 'busy',
                    'start' => ['dateTime' => $window['start']],
                    'end' => ['dateTime' => $window['end']],
                ], $busy),
            ]]]);
        }

        if (str_contains($request->url(), 'login.microsoftonline.com')) {
            return Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]);
        }

        return Http::response($event ?? []);
    });
}

it('does not offer a time the team is already busy for', function () {
    connectMicrosoft($this);
    $busy = [];
    graphAnswers($busy);

    app(CurrentOrganization::class)->set($this->org);
    $free = app(ChatBookingSlots::class)->available($this->page);

    // Now Outlook says that whole day is taken.
    $day = CarbonImmutable::parse($free[0]['at'])->startOfDay();
    $busy = [['start' => $day->format('Y-m-d\TH:i:s'), 'end' => $day->setTime(23, 59)->format('Y-m-d\TH:i:s')]];
    Cache::flush();
    $after = app(ChatBookingSlots::class)->available($this->page);
    app(CurrentOrganization::class)->forget();

    expect($free)->not->toBeEmpty();
    expect(collect($free)->filter(fn ($slot) => CarbonImmutable::parse($slot['at'])->isSameDay($day)))->not->toBeEmpty();
    expect(collect($after)->filter(fn ($slot) => CarbonImmutable::parse($slot['at'])->isSameDay($day)))->toBeEmpty();
});

it('puts the meeting in the calendar with the visitor invited, and keeps the Teams link', function () {
    connectMicrosoft($this);
    $busy = [];
    graphAnswers($busy, ['id' => 'AAMkAGI2...', 'onlineMeeting' => ['joinUrl' => 'https://teams.microsoft.com/l/meetup-join/19%3ameeting']]);

    app(CurrentOrganization::class)->set($this->org);
    $booking = app(BookingService::class)->book($this->page, [
        'name' => 'Ram Singh',
        'email' => 'ram@techpio.com',
        'scheduled_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
        'source' => 'website_chat',
    ]);
    app(CurrentOrganization::class)->forget();

    expect($booking->calendar_event_id)->toBe('AAMkAGI2...');
    expect($booking->meeting_url)->toContain('teams.microsoft.com');

    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), '/me/events')) {
            return false;
        }
        $emails = collect($request['attendees'])->pluck('emailAddress.address')->all();

        return $request['isOnlineMeeting'] === true
            && $request['onlineMeetingProvider'] === 'teamsForBusiness'
            && in_array('ram@techpio.com', $emails, true);
    });
});

it('moves and cancels that meeting when the booking does', function () {
    connectMicrosoft($this);
    $busy = [];
    graphAnswers($busy, ['id' => 'AAMkAGI2...']);

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
        'calendar_event_id' => 'AAMkAGI2...',
    ]);

    app(BookingService::class)->reschedule($booking, now()->addDays(3)->setTime(15, 0));
    app(BookingService::class)->setStatus($booking, 'cancelled');
    app(CurrentOrganization::class)->forget();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && str_contains($request->url(), '/me/events/'));
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/cancel'));
});

it('books exactly as before when Microsoft is not connected', function () {
    Http::fake();

    app(CurrentOrganization::class)->set($this->org);
    $slots = app(ChatBookingSlots::class)->available($this->page);
    $booking = app(BookingService::class)->book($this->page, [
        'name' => 'Ram Singh',
        'email' => 'ram@techpio.com',
        'scheduled_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
        'source' => 'website_chat',
    ]);
    app(CurrentOrganization::class)->forget();

    expect($slots)->not->toBeEmpty();
    expect($booking->calendar_event_id)->toBeNull();
    expect($booking->meeting_url)->toBeNull();
    Http::assertNothingSent();
});

it('still books when Graph is having a bad day', function () {
    connectMicrosoft($this);
    Http::fake(['graph.microsoft.com/*' => Http::response('bad gateway', 502)]);

    app(CurrentOrganization::class)->set($this->org);
    $slots = app(ChatBookingSlots::class)->available($this->page);
    $booking = app(BookingService::class)->book($this->page, [
        'name' => 'Ram Singh',
        'email' => 'ram@techpio.com',
        'scheduled_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
        'source' => 'website_chat',
    ]);

    // The failure is kept where the owner can see it, not shown to a visitor.
    $connection = Integration::query()->where('provider', MicrosoftCalendar::PROVIDER)->first();
    app(CurrentOrganization::class)->forget();

    expect($slots)->not->toBeEmpty();
    expect($booking->id)->not->toBeNull();
    expect($booking->meeting_url)->toBeNull();
    expect($connection->last_error)->toContain('502');
});

it('renews an expired token rather than asking anyone to connect again', function () {
    $integration = connectMicrosoft($this);
    app(CurrentOrganization::class)->set($this->org);
    $integration->forceFill(['credentials' => [
        'access_token' => 'stale',
        'refresh_token' => 'graph-refresh',
        'expires_at' => now()->subMinute()->toIso8601String(),
    ]])->save();

    $busy = [];
    graphAnswers($busy);
    config(['services.connectors.microsoft_365.client_id' => 'id', 'services.connectors.microsoft_365.client_secret' => 'secret']);

    app(MicrosoftCalendar::class)->busy(['sales@piomanage.test'], CarbonImmutable::now(), CarbonImmutable::now()->addDay());
    $after = Integration::query()->where('provider', MicrosoftCalendar::PROVIDER)->first();
    app(CurrentOrganization::class)->forget();

    expect($after->credentials['access_token'])->toBe('fresh-token');
    // The refreshed token is what Graph was called with.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.microsoft.com')
        && $request->header('Authorization')[0] === 'Bearer fresh-token');
});
