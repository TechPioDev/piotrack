<?php

namespace App\Services\Chat;

use App\Models\Booking;
use App\Models\BookingPage;
use Carbon\CarbonImmutable;

/**
 * The bookable time slots a chat conversation can offer.
 *
 * This is the availability engine whose absence kept in-chat booking Partial
 * (CHAT-023): the booking page knows its duration and, optionally, its working
 * hours, but nothing could answer "which times are actually free". Free means
 * inside the page's hours, in the future, and not already taken by a booking on
 * the same page.
 *
 * A chat bubble is not a calendar, so this offers a handful of near-term slots
 * rather than paging through weeks — a visitor who needs a specific distant
 * time still gets the booking page link as the fallback.
 */
class ChatBookingSlots
{
    /** Chat shows at most this many choices; more is a wall of buttons. */
    public const MAX_SLOTS = 6;

    /** How far ahead to look. Chat leads cool fast; next week is far enough. */
    public const DAYS_AHEAD = 7;

    /** Working hours when the page has none configured. */
    private const DEFAULT_START = '09:00';

    private const DEFAULT_END = '17:00';

    /** ISO weekdays worked when the page has none configured (Mon–Fri). */
    private const DEFAULT_DAYS = [1, 2, 3, 4, 5];

    /**
     * @return list<array{id: string, label: string, at: CarbonImmutable}>
     */
    public function available(BookingPage $page, int $max = self::MAX_SLOTS, int $daysAhead = self::DAYS_AHEAD): array
    {
        $availability = $page->availability ?? [];
        $days = array_map(intval(...), (array) ($availability['days'] ?? self::DEFAULT_DAYS));
        $start = (string) ($availability['start'] ?? self::DEFAULT_START);
        $end = (string) ($availability['end'] ?? self::DEFAULT_END);
        $duration = max(5, (int) $page->duration_minutes);

        // Taken means any non-cancelled booking on this page at that exact time.
        // Overlap with differently-sized old bookings is deliberately not solved
        // here: slots are generated on the same grid they are booked on.
        $taken = Booking::query()
            ->where('booking_page_id', $page->id)
            ->whereIn('status', ['booked', 'completed'])
            ->where('scheduled_at', '>=', now())
            ->pluck('scheduled_at')
            ->map(fn ($at) => CarbonImmutable::parse($at)->format('Y-m-d H:i'))
            ->all();

        $slots = [];
        $day = CarbonImmutable::now()->startOfDay();

        for ($offset = 0; $offset < $daysAhead && count($slots) < $max; $offset++) {
            $date = $day->addDays($offset);
            if (! in_array($date->isoWeekday(), $days, true)) {
                continue;
            }

            $cursor = $date->setTimeFromTimeString($start);
            $close = $date->setTimeFromTimeString($end);

            while ($cursor->addMinutes($duration) <= $close && count($slots) < $max) {
                // Nothing in the past, and nothing so soon nobody could join it.
                if ($cursor > now()->addMinutes(30) && ! in_array($cursor->format('Y-m-d H:i'), $taken, true)) {
                    $slots[] = [
                        'id' => 'slot_'.$cursor->format('Y-m-d\TH:i'),
                        'label' => $this->label($cursor),
                        'at' => $cursor,
                    ];
                }
                $cursor = $cursor->addMinutes($duration);
            }
        }

        return $slots;
    }

    /**
     * The slot a visitor picked, revalidated — the option list they saw may be
     * minutes old, and the server is the authority on what is still free.
     */
    public function resolve(BookingPage $page, string $slotId, int $max = self::MAX_SLOTS, int $daysAhead = self::DAYS_AHEAD): ?CarbonImmutable
    {
        foreach ($this->available($page, $max, $daysAhead) as $slot) {
            if ($slot['id'] === $slotId) {
                return $slot['at'];
            }
        }

        return null;
    }

    /** "Tomorrow 10:00" beats an ISO timestamp in a chat bubble. */
    private function label(CarbonImmutable $at): string
    {
        $day = match (true) {
            $at->isToday() => 'Today',
            $at->isTomorrow() => 'Tomorrow',
            default => $at->format('D j M'),
        };

        return $day.' '.$at->format('H:i');
    }
}
