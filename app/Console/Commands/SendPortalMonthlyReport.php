<?php

namespace App\Console\Commands;

use App\Authorization\Role;
use App\Models\Organization;
use App\Notifications\PortalReportReadyNotification;
use App\Support\CurrentOrganization;
use App\Support\NotificationDispatcher;
use Illuminate\Console\Command;

/**
 * PORTAL-013: the scheduled half of client reporting — on the first of each
 * month, every client-portal user is told last month's performance report is
 * ready, with the portal download link. The report itself is the P21 PDF,
 * generated fresh on download so it is never stale.
 */
class SendPortalMonthlyReport extends Command
{
    protected $signature = 'reports:portal-monthly';

    protected $description = 'Notify client-portal users that the monthly performance report is ready';

    public function handle(NotificationDispatcher $notifications, CurrentOrganization $current): int
    {
        $sent = 0;

        foreach (Organization::query()->cursor() as $organization) {
            $clients = $organization->members()
                ->wherePivot('status', 'active')
                ->wherePivot('role', Role::Client->value)
                ->get();

            if ($clients->isEmpty()) {
                continue;
            }

            $current->set($organization);
            try {
                foreach ($clients as $client) {
                    $notifications->toUser($client, new PortalReportReadyNotification(now()->subMonth()->format('F Y')));
                    $sent++;
                }
            } finally {
                $current->forget();
            }
        }

        $this->info("{$sent} report notifications sent.");

        return self::SUCCESS;
    }
}
