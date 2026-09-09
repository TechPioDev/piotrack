<?php

namespace App\Services\Ops;

use App\Models\User;
use App\Notifications\SystemHealthAlertNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * OBS-004: administrator alerting on critical failures. Evaluates the health
 * checks and notifies every platform admin on STATE TRANSITIONS only — a new
 * failing set alerts once (naming the checks), repeats of the same state are
 * deduped, and recovery sends a single all-clear. Never a timer, never spam.
 */
class HealthAlerter
{
    private const STATE_KEY = 'ops:health-alert:failing';

    /**
     * @param  array<string, bool>  $checks
     * @return string alerted|unchanged|recovered|healthy
     */
    public function evaluate(array $checks): string
    {
        $failing = array_keys(array_filter($checks, fn (bool $ok) => ! $ok));
        sort($failing);
        $previous = Cache::get(self::STATE_KEY);

        if ($failing === []) {
            if ($previous === null) {
                return 'healthy';
            }

            Cache::forget(self::STATE_KEY);
            $this->notify([]);

            return 'recovered';
        }

        if ($previous === implode(',', $failing)) {
            return 'unchanged';
        }

        Cache::put(self::STATE_KEY, implode(',', $failing), now()->addDay());
        $this->notify($failing);

        return 'alerted';
    }

    /**
     * @param  list<string>  $failing
     */
    private function notify(array $failing): void
    {
        $admins = User::whereNotNull('platform_role')->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new SystemHealthAlertNotification($failing));
    }
}
