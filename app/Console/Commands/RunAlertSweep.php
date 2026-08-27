<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\AlertSweep;
use App\Support\CurrentOrganization;
use Illuminate\Console\Command;

/**
 * Daily alert sweep for every organization (ALRT module): usage limits,
 * ranking drops, AI-visibility swings. Each tenant's context is set in turn
 * so detectors read only that tenant's data; per-day dedupe inside the sweep
 * makes re-runs safe.
 */
class RunAlertSweep extends Command
{
    protected $signature = 'alerts:sweep';

    protected $description = 'Detect and notify usage, ranking and AI-visibility alerts for every organization';

    public function handle(AlertSweep $sweep, CurrentOrganization $current): int
    {
        $totals = ['usage' => 0, 'rankings' => 0, 'ai_visibility' => 0, 'competitors' => 0];

        Organization::query()->each(function (Organization $organization) use ($sweep, $current, &$totals) {
            $current->set($organization);
            foreach ($sweep->run($organization) as $kind => $count) {
                $totals[$kind] += $count;
            }
        });

        $current->forget();

        $this->components->info(sprintf(
            'Alerts sent - usage: %d, ranking drops: %d, AI visibility: %d, competitor outranks: %d.',
            $totals['usage'],
            $totals['rankings'],
            $totals['ai_visibility'],
            $totals['competitors'],
        ));

        return self::SUCCESS;
    }
}
