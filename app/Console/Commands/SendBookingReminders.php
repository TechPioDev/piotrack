<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingReminderNotification;
use App\Services\Marketing\MessageDispatcher;
use App\Support\CurrentOrganization;
use App\Support\NotificationDispatcher;
use Illuminate\Console\Command;

/**
 * Reminds booking owners about meetings in the next 24 hours (BOOK-007). Runs
 * across all tenants (bookings are unscoped in the console); each reminder goes
 * to the assigned owner. Scheduled once daily so a booking is reminded ~once.
 */
class SendBookingReminders extends Command
{
    protected $signature = 'sales:send-booking-reminders';

    protected $description = 'Notify owners of bookings scheduled in the next 24 hours';

    public function handle(NotificationDispatcher $notifications, MessageDispatcher $messages, CurrentOrganization $current): int
    {
        $sent = 0;

        Booking::query()
            ->where('status', 'booked')
            ->whereBetween('scheduled_at', [now(), now()->addDay()])
            // No owner filter: the ATTENDEE's reminder must not depend on the
            // booking having been assigned to a rep yet.
            ->each(function (Booking $booking) use ($notifications, $messages, $current, &$sent) {
                $when = $booking->scheduled_at->toDayDateTimeString();
                $owner = $booking->owner_id !== null ? User::find($booking->owner_id) : null;

                if ($owner !== null) {
                    $notifications->toUser($owner, new BookingReminderNotification($booking->name, $when));
                    $sent++;
                }

                // The attendee is the one the public page promised a reminder
                // to. The owner's in-app notification points at an
                // authenticated route they cannot open.
                // Mail is written per tenant, so the booking's own organization
                // has to be the active context - the scheduler runs with none.
                $organization = $booking->organization()->first();
                $contact = $booking->contact()->first();

                if ($organization !== null && $contact !== null) {
                    $current->set($organization);

                    if ($contact->email !== null) {
                        $messages->sendEmail(
                            $contact,
                            __('Reminder: your meeting on :when', ['when' => $when]),
                            __('This is a reminder of your meeting on :when (UTC).', ['when' => $when]),
                            'booking',
                        );
                    }

                    // SMS-002/006: the attendee SMS reminder — consent and
                    // suppression enforced per contact by the dispatcher. Copy
                    // is type-aware: an event-shaped booking page reads as an
                    // event reminder, anything else as an appointment.
                    if (! empty($contact->phone)) {
                        $meetingType = (string) $booking->page()->first()?->meeting_type;
                        $noun = in_array($meetingType, ['event', 'webinar', 'workshop'], true) ? __('event') : __('appointment');
                        $messages->sendSms(
                            $contact,
                            __('Reminder: your :noun ":name" is on :when (UTC).', ['noun' => $noun, 'name' => $booking->name, 'when' => $when]),
                            'booking_reminder',
                        );
                    }

                    $current->forget();
                }
            });

        $this->components->info("Sent {$sent} booking reminder(s).");

        return self::SUCCESS;
    }
}
