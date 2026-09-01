<?php

namespace App\Notifications;

/**
 * Ticket lifecycle notification (SUPP-004): sent to the requester and/or the
 * assignee when a ticket is replied to, assigned, or resolved. Internal notes
 * never notify the requester — the caller filters those.
 */
class TicketNotification extends PlatformNotification
{
    public function __construct(
        private string $event,
        private string $subject,
    ) {}

    public function category(): string
    {
        return 'operations';
    }

    public function title(): string
    {
        return match ($this->event) {
            'replied' => 'New reply on your ticket',
            'assigned' => 'Ticket assigned to you',
            'resolved' => 'Your ticket was resolved',
            default => 'Ticket updated',
        };
    }

    public function body(): string
    {
        return $this->subject;
    }

    public function url(): ?string
    {
        return '/support';
    }
}
