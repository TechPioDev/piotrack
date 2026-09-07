<?php

namespace App\Notifications;

/**
 * PORTAL-013: the monthly performance report is ready for the client.
 */
class PortalReportReadyNotification extends PlatformNotification
{
    public function __construct(private string $period) {}

    public function category(): string
    {
        return 'operations';
    }

    public function title(): string
    {
        return "Your {$this->period} performance report is ready";
    }

    public function body(): string
    {
        return 'Download the PDF from your portal — leads, pipeline and revenue for the month, generated fresh from your live data.';
    }

    public function url(): ?string
    {
        return '/portal/report';
    }

    public function dedupeKey(): ?string
    {
        return 'portal-report:'.$this->period;
    }
}
