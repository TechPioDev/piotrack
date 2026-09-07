<?php

namespace App\Notifications;

/**
 * PERF-010: a live performance agreement's window closed with targets missed.
 * Deduped per agreement per day by the alert sweep.
 */
class SlaBreachNotification extends PlatformNotification
{
    public function __construct(
        private string $agreementName,
        private int $agreementId,
        private string $missed,
    ) {}

    public function category(): string
    {
        return 'operations';
    }

    public function title(): string
    {
        return 'Performance SLA breached';
    }

    public function body(): string
    {
        return "\"{$this->agreementName}\" ended with targets missed: {$this->missed}. Review the agreement and the lead-replacement queue.";
    }

    public function url(): ?string
    {
        return '/strategy/performance';
    }

    public function dedupeKey(): ?string
    {
        return "sla-breach:{$this->agreementId}";
    }
}
