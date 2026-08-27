<?php

namespace App\Notifications;

use Illuminate\Support\Carbon;

/**
 * A meeting was booked (ALRT / NOTIF-006) — from a public booking page or
 * in-chat slot picking, which share the booking service.
 */
class BookingCreatedNotification extends PlatformNotification
{
    public function __construct(
        private string $prospectName,
        private Carbon $scheduledAt,
        private string $source,
    ) {}

    public function category(): string
    {
        return 'sales';
    }

    public function title(): string
    {
        return 'New meeting booked';
    }

    public function body(): string
    {
        return "{$this->prospectName} booked a meeting for {$this->scheduledAt->format('D, M j \a\t g:ia')} (via {$this->source}).";
    }

    public function url(): ?string
    {
        return '/sales/booking';
    }
}
