<?php

namespace App\Services\Chat;

use App\Models\ChatWidget;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Whether a widget is inside its tenant's business hours (§33).
 *
 * Stored per widget as {timezone, days: {mon: [open, close], ...}, closed_message}.
 * A day that is missing or null is closed. With nothing configured at all the
 * widget is always open, which is the least surprising default.
 */
class ChatBusinessHours
{
    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function isOpen(ChatWidget $widget, ?Carbon $at = null): bool
    {
        $hours = $widget->business_hours ?? [];
        $days = is_array($hours['days'] ?? null) ? $hours['days'] : [];

        if ($days === []) {
            return true;
        }

        $timezone = is_string($hours['timezone'] ?? null) && $hours['timezone'] !== '' ? $hours['timezone'] : config('app.timezone', 'UTC');

        try {
            $now = CarbonImmutable::instance($at ?? now())->setTimezone($timezone);
        } catch (\Throwable) {
            // A bad timezone must not take the widget offline.
            $now = CarbonImmutable::instance($at ?? now());
        }

        $today = self::DAYS[(int) $now->dayOfWeekIso - 1] ?? null;
        $window = $today !== null ? ($days[$today] ?? null) : null;

        if (! is_array($window) || count($window) < 2) {
            return false;
        }

        [$open, $close] = [(string) $window[0], (string) $window[1]];
        $minutes = $now->hour * 60 + $now->minute;

        return $minutes >= $this->toMinutes($open) && $minutes < $this->toMinutes($close);
    }

    /** The message to show a visitor who arrives out of hours. */
    public function closedMessage(ChatWidget $widget): string
    {
        $hours = $widget->business_hours ?? [];

        return (string) ($hours['closed_message']
            ?? 'Our team is offline right now. Leave your details and we will get back to you next business day.');
    }

    private function toMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) $parts[0]) * 60 + ((int) ($parts[1] ?? 0));
    }
}
