<?php

namespace App\Console\Commands;

use App\Billing\PlanCatalog;
use App\Models\Subscription;
use App\Notifications\TrialEndingNotification;
use App\Support\NotificationDispatcher;
use Illuminate\Console\Command;

/**
 * Billing alert (NOTIF-008): remind owners when a trial is ending within the
 * next few days so they can pick a plan before losing access.
 */
class NotifyTrialEnding extends Command
{
    protected $signature = 'subscriptions:notify-trial-ending {--days='.PlanCatalog::TRIAL_WARNING_DAYS.'}';

    protected $description = 'Notify organization owners whose trial ends within N days';

    public function handle(NotificationDispatcher $notifications): int
    {
        $days = (int) $this->option('days');
        $count = 0;

        Subscription::query()
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays($days)])
            ->with('organization')
            ->each(function (Subscription $subscription) use ($notifications, &$count) {
                $daysLeft = max(1, (int) ceil(now()->diffInDays($subscription->trial_ends_at, absolute: false)));
                $notifications->toOrganizationOwners(
                    $subscription->organization,
                    new TrialEndingNotification($subscription->organization->name, $daysLeft),
                );
                $count++;
            });

        $this->components->info("Notified {$count} organization(s) of ending trials.");

        return self::SUCCESS;
    }
}
