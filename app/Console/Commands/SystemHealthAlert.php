<?php

namespace App\Console\Commands;

use App\Services\Ops\HealthAlerter;
use App\Services\Ops\HealthCheckService;
use Illuminate\Console\Command;

/**
 * OBS-004: the scheduled half of admin alerting — runs the same checks as
 * /health every five minutes and lets HealthAlerter decide whether the state
 * changed enough to notify platform admins.
 */
class SystemHealthAlert extends Command
{
    protected $signature = 'system:health-alert';

    protected $description = 'Evaluate platform health and alert platform admins on critical-failure transitions';

    public function handle(HealthCheckService $health, HealthAlerter $alerter): int
    {
        $outcome = $alerter->evaluate($health->checks());
        $this->info("health-alert: {$outcome}");

        return self::SUCCESS;
    }
}
