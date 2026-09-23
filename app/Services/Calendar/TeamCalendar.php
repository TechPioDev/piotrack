<?php

namespace App\Services\Calendar;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Whatever calendars this tenant has connected, as one calendar.
 *
 * Booking should not care whether a customer runs Microsoft 365 or Google
 * Workspace, and a busy hour is busy whichever diary it is in - so free/busy is
 * asked of every connection and merged. A meeting has to live somewhere though,
 * so it is written to the first connected provider, and the booking remembers
 * which one, because that is who must be told when it moves or is called off.
 *
 * Nothing connected is the ordinary case, not an error: everything here then
 * answers "I know nothing" and booking behaves exactly as it always did.
 */
class TeamCalendar
{
    /** @var list<CalendarProvider> */
    private array $providers;

    public function __construct(MicrosoftCalendar $microsoft, GoogleCalendar $google)
    {
        // Order matters only for where a meeting is created: first connected wins.
        $this->providers = [$microsoft, $google];
    }

    /** @return list<CalendarProvider> */
    public function connected(): array
    {
        return array_values(array_filter($this->providers, fn (CalendarProvider $provider) => $provider->isConnected()));
    }

    public function isConnected(): bool
    {
        return $this->connected() !== [];
    }

    public function provider(string $key): ?CalendarProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->key() === $key) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Every busy window any connected calendar knows about.
     *
     * @param  list<string>  $emails
     * @return list<array{from: CarbonImmutable, to: CarbonImmutable}>
     */
    public function busy(array $emails, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $busy = [];
        foreach ($this->connected() as $provider) {
            foreach ($provider->busy($emails, $from, $to) as $window) {
                $busy[] = $window;
            }
        }

        return $busy;
    }

    /**
     * Hold the hour in the first connected calendar.
     *
     * @param  list<string>  $attendees
     * @return array{provider: string, id: string, join_url: ?string}|null
     */
    public function createEvent(string $subject, CarbonImmutable $at, int $minutes, array $attendees, string $body = ''): ?array
    {
        foreach ($this->connected() as $provider) {
            $event = $provider->createEvent($subject, $at, $minutes, $attendees, $body);
            if ($event !== null) {
                return ['provider' => $provider->key(), ...$event];
            }
        }

        return null;
    }

    public function moveEvent(?string $providerKey, string $eventId, CarbonImmutable $at, int $minutes): bool
    {
        return (bool) $this->owner($providerKey)?->moveEvent($eventId, $at, $minutes);
    }

    public function cancelEvent(?string $providerKey, string $eventId, string $comment = 'This meeting has been cancelled.'): bool
    {
        return (bool) $this->owner($providerKey)?->cancelEvent($eventId, $comment);
    }

    /**
     * The people whose calendars a booking should respect: whoever owns it, and
     * the accounts the tenant connected.
     *
     * @return list<string>
     */
    public function calendarsFor(?int $ownerId): array
    {
        $owner = $ownerId !== null ? User::query()->find($ownerId) : null;
        $accounts = array_map(fn (CalendarProvider $provider) => $provider->account(), $this->connected());

        return array_values(array_filter(array_unique([$owner?->email, ...$accounts])));
    }

    /** Who holds an existing meeting: the named provider, or the only one connected. */
    private function owner(?string $providerKey): ?CalendarProvider
    {
        if ($providerKey !== null && $providerKey !== '') {
            $provider = $this->provider($providerKey);

            return $provider?->isConnected() === true ? $provider : null;
        }

        // Bookings made before a provider was recorded: if exactly one calendar
        // is connected it is unambiguous, and guessing between two is not.
        $connected = $this->connected();

        return count($connected) === 1 ? $connected[0] : null;
    }
}
