<?php

namespace App\Services\Calendar;

use Carbon\CarbonImmutable;

/**
 * A team calendar Piotrack can read and write: Microsoft 365 today, Google
 * Workspace beside it, and whatever comes next.
 *
 * Booking asks two things of a calendar and nothing more: when are these people
 * busy, and please hold this hour with a joining link on it. Everything else -
 * which API, which token, which flavour of online meeting - belongs behind this
 * line, so the booking code never grows a branch per vendor.
 *
 * Implementations answer honestly and quietly: no connection or a provider
 * having a bad day means "I know nothing", never an exception into a visitor's
 * booking.
 */
interface CalendarProvider
{
    /** The connector key this provider connects with. */
    public function key(): string;

    /** What to call it in front of an owner. */
    public function name(): string;

    public function isConnected(): bool;

    /** The address whose calendar is written to, once connected. */
    public function account(): ?string;

    /**
     * When each person is busy, between two times.
     *
     * @param  list<string>  $emails
     * @return list<array{from: CarbonImmutable, to: CarbonImmutable}>
     */
    public function busy(array $emails, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Hold the time, invite the people, and ask for a joining link.
     *
     * @param  list<string>  $attendees
     * @return array{id: string, join_url: ?string}|null
     */
    public function createEvent(string $subject, CarbonImmutable $at, int $minutes, array $attendees, string $body = ''): ?array;

    public function moveEvent(string $eventId, CarbonImmutable $at, int $minutes): bool;

    public function cancelEvent(string $eventId, string $comment = 'This meeting has been cancelled.'): bool;
}
