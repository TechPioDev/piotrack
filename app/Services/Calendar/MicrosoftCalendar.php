<?php

namespace App\Services\Calendar;

use App\Models\Integration;
use App\Models\User;
use App\Services\Integrations\OAuthFlow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The team's real calendar, through Microsoft Graph.
 *
 * Booking used to live entirely inside Piotrack: times typed into an
 * availability grid, and an .ics link in the confirmation email that somebody
 * had to click. Nothing looked at whether the salesperson was actually free, so
 * the chat would happily offer a time they were already in a meeting - and
 * nothing appeared in their Outlook or Teams calendar by itself.
 *
 * With Microsoft 365 connected this reads their real free/busy before offering
 * a time, writes the meeting into their calendar with the visitor invited, and
 * asks Microsoft to make it an online meeting so every booking carries a proper
 * Teams link.
 *
 * Every method is written to fail quietly: a tenant with no connection, an
 * expired consent, a Graph outage - all of them return "I know nothing" and the
 * product carries on exactly as it did before. A visitor's booking must never
 * depend on Microsoft answering.
 */
class MicrosoftCalendar
{
    public const PROVIDER = 'microsoft_365';

    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    /** Free/busy is asked for once a minute at most, per set of people. */
    private const BUSY_CACHE_SECONDS = 60;

    public function __construct(private readonly OAuthFlow $oauth) {}

    /** The tenant's connection, if they have one that still works. */
    public function connection(): ?Integration
    {
        $integration = Integration::query()
            ->where('provider', self::PROVIDER)
            ->where('status', 'connected')
            ->first();

        return $integration?->credentials['access_token'] ?? null ? $integration : null;
    }

    public function isConnected(): bool
    {
        return $this->connection() !== null;
    }

    /**
     * When each person is busy, between two times.
     *
     * @param  list<string>  $emails
     * @return list<array{from: CarbonImmutable, to: CarbonImmutable}>
     */
    public function busy(array $emails, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $emails = array_values(array_filter(array_unique(array_map('strtolower', $emails))));
        if ($emails === [] || ! $this->isConnected()) {
            return [];
        }

        $key = 'ms-busy:'.md5(implode(',', $emails).$from->toIso8601String().$to->toIso8601String());

        return Cache::remember($key, self::BUSY_CACHE_SECONDS, function () use ($emails, $from, $to): array {
            $response = $this->call('post', '/me/calendar/getSchedule', [
                'schedules' => $emails,
                'startTime' => ['dateTime' => $from->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
                'endTime' => ['dateTime' => $to->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
                'availabilityViewInterval' => 15,
            ]);

            $busy = [];
            foreach ((array) ($response['value'] ?? []) as $schedule) {
                foreach ((array) ($schedule['scheduleItems'] ?? []) as $item) {
                    // "Free" and "working elsewhere" are not busy; tentative is.
                    if (in_array($item['status'] ?? '', ['free', 'workingElsewhere'], true)) {
                        continue;
                    }
                    $start = $item['start']['dateTime'] ?? null;
                    $end = $item['end']['dateTime'] ?? null;
                    if (is_string($start) && is_string($end)) {
                        $busy[] = ['from' => CarbonImmutable::parse($start, 'UTC'), 'to' => CarbonImmutable::parse($end, 'UTC')];
                    }
                }
            }

            return $busy;
        });
    }

    /**
     * Whether anyone in the list is busy across a span.
     *
     * @param  list<string>  $emails
     */
    public function anyoneBusy(array $emails, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        foreach ($this->busy($emails, $from, $to) as $window) {
            if ($window['from'] < $to && $window['to'] > $from) {
                return true;
            }
        }

        return false;
    }

    /**
     * Put the meeting in the team's calendar, as an online meeting.
     *
     * @param  list<string>  $attendees  email addresses to invite
     * @return array{id: string, join_url: ?string}|null
     */
    public function createEvent(string $subject, CarbonImmutable $at, int $minutes, array $attendees, string $body = ''): ?array
    {
        if (! $this->isConnected()) {
            return null;
        }

        $event = $this->call('post', '/me/events', [
            'subject' => $subject,
            'body' => ['contentType' => 'text', 'content' => $body],
            'start' => ['dateTime' => $at->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $at->utc()->addMinutes($minutes)->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'attendees' => array_map(fn (string $email) => [
                'emailAddress' => ['address' => $email],
                'type' => 'required',
            ], array_values(array_filter($attendees))),
            // The whole point of connecting Microsoft: a real Teams link,
            // created by Microsoft, on every booking.
            'isOnlineMeeting' => true,
            'onlineMeetingProvider' => 'teamsForBusiness',
        ]);

        $id = $event['id'] ?? null;
        if (! is_string($id) || $id === '') {
            return null;
        }

        return ['id' => $id, 'join_url' => $event['onlineMeeting']['joinUrl'] ?? null];
    }

    /** Move an existing meeting. Silent if it is gone or we are not connected. */
    public function moveEvent(string $eventId, CarbonImmutable $at, int $minutes): bool
    {
        if (! $this->isConnected()) {
            return false;
        }

        $updated = $this->call('patch', '/me/events/'.rawurlencode($eventId), [
            'start' => ['dateTime' => $at->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $at->utc()->addMinutes($minutes)->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
        ]);

        return $updated !== [];
    }

    /** Cancel it, so the attendees are told rather than left holding a slot. */
    public function cancelEvent(string $eventId, string $comment = 'This meeting has been cancelled.'): bool
    {
        if (! $this->isConnected()) {
            return false;
        }

        return $this->call('post', '/me/events/'.rawurlencode($eventId).'/cancel', ['Comment' => $comment]) !== [];
    }

    /**
     * Ask Microsoft who just connected, and keep it.
     *
     * The address matters: it is the calendar meetings are written to, and one
     * of the diaries checked before a time is offered. Asked once, at connect.
     */
    public function rememberAccount(Integration $integration): void
    {
        $token = $integration->credentials['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            return;
        }

        try {
            $me = Http::withToken($token)->acceptJson()->timeout(8)->get(self::GRAPH.'/me');
            $account = $me->json('mail') ?: $me->json('userPrincipalName');
        } catch (Throwable) {
            $account = null;
        }

        if (is_string($account) && $account !== '') {
            $integration->forceFill(['settings' => [...($integration->settings ?? []), 'account' => $account]])->save();
        }
    }

    /** The account the tenant connected, for showing on the settings page. */
    public function account(): ?string
    {
        $connection = $this->connection();

        return $connection?->settings['account'] ?? null;
    }

    /**
     * One Graph call, with the token refreshed if it has expired.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, array $payload = []): array
    {
        $token = $this->token();
        if ($token === null) {
            return [];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(8)
                ->{$method}(self::GRAPH.$path, $payload);

            if ($response->failed()) {
                $this->remember('Microsoft Graph refused a '.$method.' to '.$path.': HTTP '.$response->status());

                return [];
            }

            return is_array($response->json()) ? $response->json() : ['ok' => true];
        } catch (Throwable $e) {
            $this->remember('Microsoft Graph could not be reached: '.$e->getMessage());

            return [];
        }
    }

    /** A live access token, refreshed when it is about to run out. */
    private function token(): ?string
    {
        $connection = $this->connection();
        if ($connection === null) {
            return null;
        }

        $credentials = $connection->credentials ?? [];
        $expires = isset($credentials['expires_at']) ? CarbonImmutable::parse((string) $credentials['expires_at']) : null;

        // Still good for at least another minute: use it as it is.
        if ($expires !== null && $expires->isAfter(now()->addMinute())) {
            return (string) $credentials['access_token'];
        }

        $refresh = $credentials['refresh_token'] ?? null;
        if (! is_string($refresh) || $refresh === '') {
            // No way to renew: use what we have and let the call fail honestly.
            return (string) ($credentials['access_token'] ?? '') ?: null;
        }

        try {
            $fresh = $this->oauth->refresh(self::PROVIDER, $refresh);
        } catch (Throwable $e) {
            $this->remember('Microsoft 365 needs connecting again: '.$e->getMessage());

            return null;
        }

        $connection->forceFill([
            'credentials' => [...$credentials, ...$fresh],
            'last_error' => null,
        ])->save();

        return $fresh['access_token'];
    }

    /** Whatever went wrong is kept on the connection, where the owner can see it. */
    private function remember(string $problem): void
    {
        Log::warning($problem);
        $this->connection()?->forceFill(['last_error' => mb_substr($problem, 0, 500)])->save();
    }

    /**
     * The people whose calendars a booking should respect.
     *
     * @return list<string>
     */
    public function calendarsFor(?int $ownerId): array
    {
        $owner = $ownerId !== null ? User::query()->find($ownerId) : null;
        $account = $this->account();

        return array_values(array_filter(array_unique([$owner?->email, $account])));
    }
}
