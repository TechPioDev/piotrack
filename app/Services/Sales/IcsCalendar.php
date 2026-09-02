<?php

namespace App\Services\Sales;

use App\Models\Booking;
use App\Models\BookingPage;

/**
 * OAuth-free calendar integration (BOOK-001): standard iCalendar text every
 * calendar app understands — a per-booking invite file, and a per-page feed
 * URL Google/Outlook/Apple Calendar can subscribe to. Two-way sync arrives
 * with a calendar connector; publishing what we know needs no vendor at all.
 */
class IcsCalendar
{
    public function event(Booking $booking): string
    {
        return $this->calendar([$this->vevent($booking)]);
    }

    /** Upcoming, non-cancelled bookings on one page, as a subscribable feed. */
    public function feed(BookingPage $page): string
    {
        $events = Booking::withoutGlobalScope('tenant')
            ->where('booking_page_id', $page->id)
            ->whereIn('status', ['booked', 'completed'])
            ->where('scheduled_at', '>=', now()->subDay())
            ->orderBy('scheduled_at')
            ->limit(200)
            ->get()
            ->map(fn (Booking $b) => $this->vevent($b))
            ->all();

        return $this->calendar($events);
    }

    private function vevent(Booking $booking): string
    {
        $page = $booking->page()->withoutGlobalScope('tenant')->first();
        $duration = max(5, (int) ($page->duration_minutes ?? 30));
        $start = $booking->scheduled_at->copy()->utc();

        return implode("\r\n", array_filter([
            'BEGIN:VEVENT',
            'UID:booking-'.$booking->id.'@piotrack',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$start->format('Ymd\THis\Z'),
            'DTEND:'.$start->copy()->addMinutes($duration)->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape(ucfirst((string) ($page->meeting_type ?? 'meeting')).' — '.$booking->name),
            $booking->notes !== null && $booking->notes !== '' ? 'DESCRIPTION:'.$this->escape($booking->notes) : null,
            'STATUS:'.($booking->status === 'canceled' ? 'CANCELLED' : 'CONFIRMED'),
            'END:VEVENT',
        ]));
    }

    /**
     * @param  list<string>  $events
     */
    private function calendar(array $events): string
    {
        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Piotrack//Booking//EN',
            'CALSCALE:GREGORIAN',
            ...$events,
            'END:VCALENDAR',
        ])."\r\n";
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $text);
    }
}
