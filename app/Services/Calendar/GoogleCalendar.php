<?php

namespace App\Services\Calendar;

use App\Models\Integration;
use App\Services\Integrations\OAuthFlow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The same job as {@see MicrosoftCalendar}, for a team on Google Workspace:
 * real free/busy before a time is offered, the meeting written into their
 * calendar with the visitor invited, and a Google Meet link on it.
 *
 * Two differences worth knowing. Google only hands back a refresh token when it
 * is asked to (`access_type=offline`), which the connector configuration does.
 * And a Meet link is not a flag but a request - the event is created with a
 * conference request and Google fills the link in, so the created event is read
 * back rather than assumed.
 */
class GoogleCalendar implements CalendarProvider
{
    public const PROVIDER = 'google_calendar';

    private const API = 'https://www.googleapis.com/calendar/v3';

    private const BUSY_CACHE_SECONDS = 60;

    public function __construct(private readonly OAuthFlow $oauth) {}

    public function key(): string
    {
        return self::PROVIDER;
    }

    public function name(): string
    {
        return 'Google Calendar';
    }

    public function connection(): ?Integration
    {
        $integration = Integration::query()
            ->where('provider', self::PROVIDER)
            ->where('status', 'connected')
            ->first();

        return ($integration?->credentials['access_token'] ?? null) ? $integration : null;
    }

    public function isConnected(): bool
    {
        return $this->connection() !== null;
    }

    public function account(): ?string
    {
        return $this->connection()?->settings['account'] ?? null;
    }

    /**
     * @param  list<string>  $emails
     * @return list<array{from: CarbonImmutable, to: CarbonImmutable}>
     */
    public function busy(array $emails, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $emails = array_values(array_filter(array_unique(array_map('strtolower', $emails))));
        if ($emails === [] || ! $this->isConnected()) {
            return [];
        }

        $key = 'google-busy:'.md5(implode(',', $emails).$from->toIso8601String().$to->toIso8601String());

        $windows = Cache::remember($key, self::BUSY_CACHE_SECONDS, function () use ($emails, $from, $to): array {
            $response = $this->call('post', '/freeBusy', [
                'timeMin' => $from->utc()->toRfc3339String(),
                'timeMax' => $to->utc()->toRfc3339String(),
                'items' => array_map(fn (string $email) => ['id' => $email], $emails),
            ]);

            $busy = [];
            foreach ((array) ($response['calendars'] ?? []) as $calendar) {
                foreach ((array) ($calendar['busy'] ?? []) as $window) {
                    $start = $window['start'] ?? null;
                    $end = $window['end'] ?? null;
                    if (is_string($start) && is_string($end)) {
                        $busy[] = [
                            'from' => CarbonImmutable::parse($start)->utc()->toIso8601String(),
                            'to' => CarbonImmutable::parse($end)->utc()->toIso8601String(),
                        ];
                    }
                }
            }

            return $busy;
        });

        return array_map(
            fn (array $window): array => [
                'from' => CarbonImmutable::parse($window['from']),
                'to' => CarbonImmutable::parse($window['to']),
            ],
            $windows,
        );
    }

    /**
     * @param  list<string>  $attendees
     * @return array{id: string, join_url: ?string}|null
     */
    public function createEvent(string $subject, CarbonImmutable $at, int $minutes, array $attendees, string $body = ''): ?array
    {
        if (! $this->isConnected()) {
            return null;
        }

        $event = $this->call('post', '/calendars/primary/events?conferenceDataVersion=1&sendUpdates=all', [
            'summary' => $subject,
            'description' => $body,
            'start' => ['dateTime' => $at->utc()->toRfc3339String(), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $at->utc()->addMinutes($minutes)->toRfc3339String(), 'timeZone' => 'UTC'],
            'attendees' => array_map(fn (string $email) => ['email' => $email], array_values(array_filter($attendees))),
            // Asking for the Meet link, which Google creates and returns.
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => (string) Str::uuid(),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
        ]);

        $id = $event['id'] ?? null;
        if (! is_string($id) || $id === '') {
            return null;
        }

        $entry = collect((array) ($event['conferenceData']['entryPoints'] ?? []))
            ->first(fn ($point) => ($point['entryPointType'] ?? '') === 'video');

        return ['id' => $id, 'join_url' => $event['hangoutLink'] ?? $entry['uri'] ?? null];
    }

    public function moveEvent(string $eventId, CarbonImmutable $at, int $minutes): bool
    {
        if (! $this->isConnected()) {
            return false;
        }

        return $this->call('patch', '/calendars/primary/events/'.rawurlencode($eventId).'?sendUpdates=all', [
            'start' => ['dateTime' => $at->utc()->toRfc3339String(), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $at->utc()->addMinutes($minutes)->toRfc3339String(), 'timeZone' => 'UTC'],
        ]) !== [];
    }

    public function cancelEvent(string $eventId, string $comment = 'This meeting has been cancelled.'): bool
    {
        if (! $this->isConnected()) {
            return false;
        }

        // Google has no cancellation note: deleting with sendUpdates tells the
        // attendees, which is the part that matters.
        return $this->call('delete', '/calendars/primary/events/'.rawurlencode($eventId).'?sendUpdates=all') !== [];
    }

    /** Ask Google who just connected, and keep it. */
    public function rememberAccount(Integration $integration): void
    {
        $token = $integration->credentials['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            return;
        }

        try {
            $me = Http::withToken($token)->acceptJson()->timeout(8)->get('https://www.googleapis.com/oauth2/v2/userinfo');
            $account = $me->json('email');
        } catch (Throwable) {
            $account = null;
        }

        if (is_string($account) && $account !== '') {
            $integration->forceFill(['settings' => [...($integration->settings ?? []), 'account' => $account]])->save();
        }
    }

    /**
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
            $request = Http::withToken($token)->acceptJson()->timeout(8);
            $response = $method === 'delete'
                ? $request->delete(self::API.$path)
                : $request->{$method}(self::API.$path, $payload);

            if ($response->failed()) {
                $this->remember('Google Calendar refused a '.$method.' to '.$path.': HTTP '.$response->status());

                return [];
            }

            return is_array($response->json()) ? $response->json() : ['ok' => true];
        } catch (Throwable $e) {
            $this->remember('Google Calendar could not be reached: '.$e->getMessage());

            return [];
        }
    }

    private function token(): ?string
    {
        $connection = $this->connection();
        if ($connection === null) {
            return null;
        }

        $credentials = $connection->credentials ?? [];
        $expires = isset($credentials['expires_at']) ? CarbonImmutable::parse((string) $credentials['expires_at']) : null;

        if ($expires !== null && $expires->isAfter(now()->addMinute())) {
            return (string) $credentials['access_token'];
        }

        $refresh = $credentials['refresh_token'] ?? null;
        if (! is_string($refresh) || $refresh === '') {
            return (string) ($credentials['access_token'] ?? '') ?: null;
        }

        try {
            $fresh = $this->oauth->refresh(self::PROVIDER, $refresh);
        } catch (Throwable $e) {
            $this->remember('Google Calendar needs connecting again: '.$e->getMessage());

            return null;
        }

        $connection->forceFill(['credentials' => [...$credentials, ...$fresh], 'last_error' => null])->save();

        return $fresh['access_token'];
    }

    private function remember(string $problem): void
    {
        Log::warning($problem);
        $this->connection()?->forceFill(['last_error' => mb_substr($problem, 0, 500)])->save();
    }
}
