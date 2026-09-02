<?php

declare(strict_types=1);

/**
 * Appointment Booking close-out (Phase 18 — BOOK-001/003/005/006/011/012).
 *
 * The public page offers real availability (the engine chat already used),
 * every booking carries a calendar invite and each page a subscribable feed,
 * territory pages route to the branch rep, qualification questions validate
 * and store, and no-show/completed statuses drive automation.
 */

use App\Authorization\Role;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Contact;
use App\Models\OutboundMessage;
use App\Models\SeoLocation;
use App\Models\Workflow;
use App\Services\Sales\BookingService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Str;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Booking Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function makeBookingPage(array $overrides = []): BookingPage
{
    return BookingPage::create(array_merge([
        'name' => 'Discovery call', 'slug' => 'disco-'.Str::lower(Str::random(6)),
        'meeting_type' => 'consultation', 'duration_minutes' => 30,
        'assignment' => 'round_robin', 'is_active' => true,
        'availability' => ['days' => [1, 2, 3, 4, 5, 6, 7], 'start' => '09:00', 'end' => '17:00'],
        'ics_feed_token' => Str::random(48),
    ], $overrides));
}

it('offers real availability slots and books exactly the picked one', function () {
    $page = makeBookingPage();
    app(CurrentOrganization::class)->forget();

    $show = $this->get('/b/'.$page->slug)->assertOk();
    $slots = $show->viewData('slots');
    expect(count($slots))->toBeGreaterThan(5);

    $picked = $slots[0];
    $this->post('/b/'.$page->slug, [
        'name' => 'Slot Sam', 'email' => 'slot@x.test', 'slot_id' => $picked['id'],
    ])->assertOk();

    $booking = Booking::withoutGlobalScopes()->firstOrFail();
    expect($booking->scheduled_at->format('Y-m-d H:i'))->toBe($picked['at']->format('Y-m-d H:i'))
        ->and($booking->ics_token)->not->toBeNull();

    // The taken slot is no longer offered, and a stale pick is refused.
    $again = $this->get('/b/'.$page->slug)->viewData('slots');
    expect(collect($again)->pluck('id'))->not->toContain($picked['id']);

    $this->post('/b/'.$page->slug, [
        'name' => 'Late Larry', 'email' => 'late@x.test', 'slot_id' => $picked['id'],
    ])->assertSessionHasErrors('slot_id');
});

it('validates required qualification questions and stores answers with UTM', function () {
    $page = makeBookingPage(['questions' => [
        ['label' => 'Current IT provider?', 'required' => true],
        ['label' => 'How many seats?', 'required' => false],
    ]]);
    app(CurrentOrganization::class)->forget();

    // Missing the required answer: refused.
    $this->post('/b/'.$page->slug, [
        'name' => 'Q Quinn', 'email' => 'q@x.test', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'),
    ])->assertSessionHasErrors('answers.0');

    $this->post('/b/'.$page->slug, [
        'name' => 'Q Quinn', 'email' => 'q@x.test', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'),
        'answers' => ['0' => 'In-house today', '1' => '45'],
        'utm_source' => 'newsletter', 'utm_campaign' => 'sept-audit',
    ])->assertOk();

    $booking = Booking::withoutGlobalScopes()->firstOrFail();
    expect($booking->answers)->toBe(['Current IT provider?' => 'In-house today', 'How many seats?' => '45'])
        ->and($booking->utm)->toBe(['source' => 'newsletter', 'campaign' => 'sept-audit']);
});

it('routes territory bookings to the branch rep, falling back to round-robin', function () {
    $rep = addMember($this->org, Role::SalesRepresentative);
    SeoLocation::create(['name' => 'Philly', 'city' => 'Philadelphia', 'territory' => 'Southeast PA', 'is_active' => true, 'owner_id' => $rep->id]);
    $page = makeBookingPage(['assignment' => 'territory']);
    app(CurrentOrganization::class)->forget();

    $this->post('/b/'.$page->slug, [
        'name' => 'Terry Tory', 'email' => 'terry@x.test',
        'scheduled_at' => now()->addDay()->format('Y-m-d H:i'), 'city' => 'philadelphia',
    ])->assertOk();

    expect(Booking::withoutGlobalScopes()->firstOrFail()->owner_id)->toBe($rep->id);

    // A city no branch covers still gets an owner — round-robin, not nobody.
    $this->post('/b/'.$page->slug, [
        'name' => 'No Match', 'email' => 'nomatch@x.test',
        'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i'), 'city' => 'Anchorage',
    ])->assertOk();

    expect(Booking::withoutGlobalScopes()->where('email', 'nomatch@x.test')->firstOrFail()->owner_id)->not->toBeNull();
});

it('serves the calendar invite and the page feed as iCalendar', function () {
    $page = makeBookingPage();
    app(CurrentOrganization::class)->forget();

    $this->post('/b/'.$page->slug, [
        'name' => 'Cal Endar', 'email' => 'cal@x.test', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'),
    ])->assertOk();

    $booking = Booking::withoutGlobalScopes()->firstOrFail();

    // The confirmation email links the invite.
    $confirmation = OutboundMessage::withoutGlobalScopes()->where('source', 'booking')->firstOrFail();
    expect($confirmation->body)->toContain('/b/ics/'.$booking->ics_token.'.ics');

    $this->get('/b/ics/'.$booking->ics_token.'.ics')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertSee('BEGIN:VEVENT', false)
        ->assertSee('DTSTART:'.$booking->scheduled_at->copy()->utc()->format('Ymd\THis\Z'), false);

    $this->get('/b/feed/'.$page->ics_feed_token.'.ics')
        ->assertOk()
        ->assertSee('SUMMARY:Consultation', false)
        ->assertSee('booking-'.$booking->id.'@piotrack', false);

    $this->get('/b/ics/definitely-wrong.ics')->assertNotFound();
    $this->get('/b/feed/definitely-wrong.ics')->assertNotFound();
});

it('drives no-show recovery and post-meeting follow-up automation', function () {
    $page = makeBookingPage();
    Workflow::create(['name' => 'No-show rescue', 'trigger_type' => 'booking_no_show', 'status' => 'active']);
    Workflow::create(['name' => 'Post-meeting nurture', 'trigger_type' => 'booking_completed', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();

    $this->post('/b/'.$page->slug, [
        'name' => 'Ghost Gary', 'email' => 'gary@x.test', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'),
    ])->assertOk();

    app(CurrentOrganization::class)->set($this->org);
    $booking = Booking::firstOrFail();
    $contact = Contact::where('email', 'gary@x.test')->firstOrFail();

    // BOOK-011: no-show → workflow enrolment + a re-book email with the page link.
    app(BookingService::class)->setStatus($booking, 'no_show');
    expect(DB::table('workflow_enrollments')->count())->toBe(1)
        ->and(OutboundMessage::where('contact_id', $contact->id)->where('subject', 'like', '%missed%')->value('body'))
        ->toContain('/b/'.$page->slug);

    // BOOK-012: completed → second enrolment + a dated follow-up task for the owner.
    app(BookingService::class)->setStatus($booking, 'completed');
    expect(DB::table('workflow_enrollments')->count())->toBe(2)
        ->and(DB::table('activities')->where('type', 'task')->where('title', 'like', 'Follow up%')->count())->toBe(1);
});

it('gates page configuration and keeps territory data tenant-scoped', function () {
    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)->post(route('sales.booking.store'), [
        'name' => 'X', 'meeting_type' => 'call', 'duration_minutes' => 30, 'assignment' => 'territory',
    ])->assertForbidden();

    $this->actingAs($this->owner)->post(route('sales.booking.store'), [
        'name' => 'Territory page', 'meeting_type' => 'call', 'duration_minutes' => 30, 'assignment' => 'territory',
        'questions' => [['label' => 'Seats?', 'required' => true]],
        'availability' => ['days' => [1, 2], 'start' => '10:00', 'end' => '12:00'],
    ])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    $created = BookingPage::where('name', 'Territory page')->firstOrFail();
    expect($created->assignment)->toBe('territory')
        ->and($created->questions[0]['label'])->toBe('Seats?')
        ->and($created->ics_feed_token)->not->toBeNull();
});
