<?php

namespace App\Notifications;

/**
 * A contact crossed the SQL threshold (ALRT / NOTIF-006). Fires from the
 * scoring service's promotion point, which by construction happens at most
 * once per contact — so this needs no dedupe key.
 */
class SqlPromotedNotification extends PlatformNotification
{
    public function __construct(
        private int $contactId,
        private string $contactName,
        private int $score,
    ) {}

    public function category(): string
    {
        return 'sales';
    }

    public function title(): string
    {
        return 'New sales-qualified lead';
    }

    public function body(): string
    {
        return "{$this->contactName} reached a lead score of {$this->score} and is now an SQL.";
    }

    public function url(): ?string
    {
        return "/crm/contacts/{$this->contactId}";
    }
}
