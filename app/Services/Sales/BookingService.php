<?php

namespace App\Services\Sales;

use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Contact;
use App\Models\SeoLocation;
use App\Models\User;
use App\Notifications\BookingCreatedNotification;
use App\Services\Integrations\WebhookDispatcher;
use App\Services\Marketing\MarketingTrigger;
use App\Services\Marketing\MessageDispatcher;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use App\Support\NotificationDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Appointment booking (BOOK). Turns a public booking into a Booking + a
 * deduped Contact + a CRM activity, assigning an owner (fixed or round-robin).
 * Assumes tenant context is set from the booking page's org (public controller
 * establishes it). Two-way calendar sync arrives via an INTG connector.
 */
class BookingService
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
        private MessageDispatcher $messages,
        private NotificationDispatcher $notifier,
        private AlertService $alerts,
        private VisitorTracker $visitors,
        private WebhookDispatcher $webhooks,
        private MarketingTrigger $trigger,
    ) {}

    /**
     * Confirm the appointment to the person who booked it.
     *
     * The public page tells them "Your :type is confirmed. We will send a
     * reminder." Nothing used to reach them: booking sent no mail at all, and
     * the reminder command notified only the owner, through an in-app
     * notification pointing at an authenticated route the attendee cannot open.
     * The promise on that page was never kept.
     */
    private function confirm(Booking $booking, BookingPage $page, Contact $contact): void
    {
        $when = $booking->scheduled_at->toDayDateTimeString();

        $this->messages->sendEmail(
            $contact,
            __(':type confirmed for :when', ['type' => ucfirst((string) $page->meeting_type), 'when' => $when]),
            __('Your :type is confirmed for :when (UTC). Add it to your calendar: :ics — or reply to this email if you need to reschedule.', [
                'type' => $page->meeting_type,
                'when' => $when,
                'ics' => url('/b/ics/'.$booking->ics_token.'.ics'),
            ]),
            'booking',
        );
    }

    /**
     * @param  array{name: string, email: string, scheduled_at: mixed, source?: ?string, notes?: ?string, city?: ?string, answers?: ?array<string, string>, utm?: ?array<string, string>}  $data
     */
    public function book(BookingPage $page, array $data, ?string $visitorKey = null): Booking
    {
        $email = Str::lower(trim($data['email']));

        $contact = Contact::where('email', $email)->first()
            ?? Contact::create([
                'first_name' => $data['name'],
                'email' => $email,
                'lead_source' => 'booking',
                'lifecycle_stage' => 'lead',
            ]);

        // BOOK-010: tie the meeting to the visitor's browsing history so the
        // booking inherits first-touch attribution (UTM/referrer) end to end.
        if ($visitorKey !== null && $visitorKey !== '') {
            $this->visitors->linkContact($visitorKey, $contact);
        }

        $ownerId = $this->assignOwner($page, isset($data['city']) ? (string) $data['city'] : null);

        $booking = Booking::create([
            'booking_page_id' => $page->id,
            'contact_id' => $contact->id,
            'owner_id' => $ownerId,
            'name' => $data['name'],
            'email' => $email,
            'scheduled_at' => $data['scheduled_at'],
            'status' => 'booked',
            'source' => $data['source'] ?? 'booking_page',
            'notes' => $data['notes'] ?? null,
            'ics_token' => Str::random(48),
            'answers' => $data['answers'] ?? null,
            'utm' => $data['utm'] ?? null,
        ]);

        Activity::create([
            'subject_type' => 'contact',
            'subject_id' => $contact->id,
            'type' => 'meeting',
            'user_id' => $ownerId,
            'title' => 'Booked: '.$page->meeting_type,
            'due_at' => $booking->scheduled_at,
        ]);

        $this->confirm($booking, $page, $contact);

        // ALRT / NOTIF-006: tell the seller too, not only the prospect. Covers
        // public booking pages and in-chat booking, which share this service.
        $notification = new BookingCreatedNotification($booking->name, $booking->scheduled_at, (string) $booking->source);
        $owner = $ownerId !== null ? User::find($ownerId) : null;
        if ($owner !== null) {
            $this->notifier->toUser($owner, $notification);
        } else {
            $this->notifier->toOrganizationOwners($this->currentOrganization->get(), $notification);
        }

        // ALERT-009: a requested meeting is the highest-signal sales event.
        $this->alerts->fire('meeting_request', $contact);

        // INTG-009: outbound webhook fan-out.
        $this->webhooks->dispatch('booking.created', [
            'booking_id' => $booking->id,
            'name' => $booking->name,
            'email' => $booking->email,
            'scheduled_at' => $booking->scheduled_at->toIso8601String(),
            'source' => $booking->source,
        ]);

        $this->audit->log('sales.booking.created', context: ['page' => $page->name], resourceType: 'booking', resourceId: (string) $booking->id, organizationId: $booking->organization_id);

        return $booking;
    }

    public function setStatus(Booking $booking, string $status): Booking
    {
        $booking->update(['status' => $status]);
        $this->audit->log('sales.booking.status_changed', context: ['status' => $status], resourceType: 'booking', resourceId: (string) $booking->id, organizationId: $booking->organization_id);

        $contact = $booking->contact()->first();
        $page = $booking->page()->first();

        // BOOK-011: a no-show starts the recovery, not the shrug — automation
        // workflows can enroll, and the prospect gets a one-click way back in.
        if ($status === 'no_show' && $contact !== null) {
            $this->trigger->fire('booking_no_show', $contact, ['booking_id' => $booking->id]);

            if ($page !== null) {
                $this->messages->sendEmail(
                    $contact,
                    __('Sorry we missed each other'),
                    __('We missed you for the :type. Grab a new time that works: :url', [
                        'type' => $page->meeting_type,
                        'url' => url('/b/'.$page->slug),
                    ]),
                    'booking',
                );
            }
        }

        // BOOK-012: a finished meeting queues the follow-up — automation plus
        // a dated task for the owner, so nobody relies on memory.
        if ($status === 'completed' && $contact !== null) {
            $this->trigger->fire('booking_completed', $contact, ['booking_id' => $booking->id]);

            Activity::create([
                'subject_type' => 'contact',
                'subject_id' => $contact->id,
                'type' => 'task',
                'user_id' => $booking->owner_id,
                'title' => 'Follow up after '.($page->meeting_type ?? 'meeting'),
                'due_at' => now()->addDay(),
            ]);
        }

        return $booking;
    }

    public function reschedule(Booking $booking, Carbon $when): Booking
    {
        $booking->update(['scheduled_at' => $when, 'status' => 'booked']);

        return $booking;
    }

    /**
     * Choose the owner for a booking: the page owner for fixed assignment, or
     * the least-loaded active member for round-robin.
     */
    private function assignOwner(BookingPage $page, ?string $city = null): ?int
    {
        // BOOK-005: a territory page routes to the matching branch's rep; an
        // unmatched city falls back to round-robin rather than guessing.
        if ($page->assignment === 'territory') {
            $ownerId = $city !== null ? $this->territoryOwner($city) : null;

            return $ownerId ?? $this->roundRobin($page);
        }

        if ($page->assignment !== 'round_robin') {
            return $page->user_id;
        }

        return $this->roundRobin($page);
    }

    /** The branch rep whose city/region/territory matches what they typed. */
    private function territoryOwner(string $city): ?int
    {
        $needle = mb_strtolower(trim($city));
        if ($needle === '') {
            return null;
        }

        $location = SeoLocation::where('is_active', true)->whereNotNull('owner_id')->get()
            ->first(fn (SeoLocation $l) => in_array($needle, array_map(
                fn ($v) => mb_strtolower(trim((string) $v)),
                array_filter([$l->city, $l->region, $l->territory]),
            ), true));

        return $location?->owner_id;
    }

    private function roundRobin(BookingPage $page): ?int
    {
        $organization = $this->currentOrganization->get() ?? $page->organization()->first();
        if ($organization === null) {
            return $page->user_id;
        }

        /** @var list<int> $members */
        $members = $organization->members()->wherePivot('status', 'active')->pluck('users.id')->all();
        if ($members === []) {
            return $page->user_id;
        }

        $counts = array_fill_keys($members, 0);
        foreach (Booking::whereIn('owner_id', $members)->pluck('owner_id') as $ownerId) {
            if (isset($counts[$ownerId])) {
                $counts[$ownerId]++;
            }
        }

        asort($counts);

        return (int) array_key_first($counts);
    }
}
