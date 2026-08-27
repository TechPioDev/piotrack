<?php

namespace App\Services\Sales;

use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Contact;
use App\Models\User;
use App\Notifications\BookingCreatedNotification;
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
            __('Your :type is confirmed for :when (UTC). Reply to this email if you need to reschedule.', [
                'type' => $page->meeting_type,
                'when' => $when,
            ]),
            'booking',
        );
    }

    /**
     * @param  array{name: string, email: string, scheduled_at: mixed, source?: ?string, notes?: ?string}  $data
     */
    public function book(BookingPage $page, array $data): Booking
    {
        $email = Str::lower(trim($data['email']));

        $contact = Contact::where('email', $email)->first()
            ?? Contact::create([
                'first_name' => $data['name'],
                'email' => $email,
                'lead_source' => 'booking',
                'lifecycle_stage' => 'lead',
            ]);

        $ownerId = $this->assignOwner($page);

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

        $this->audit->log('sales.booking.created', context: ['page' => $page->name], resourceType: 'booking', resourceId: (string) $booking->id, organizationId: $booking->organization_id);

        return $booking;
    }

    public function setStatus(Booking $booking, string $status): Booking
    {
        $booking->update(['status' => $status]);
        $this->audit->log('sales.booking.status_changed', context: ['status' => $status], resourceType: 'booking', resourceId: (string) $booking->id, organizationId: $booking->organization_id);

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
    private function assignOwner(BookingPage $page): ?int
    {
        if ($page->assignment !== 'round_robin') {
            return $page->user_id;
        }

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
