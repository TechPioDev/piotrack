<?php

namespace Database\Seeders;

use App\Billing\Feature;
use App\Billing\PlanCatalog;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Syncs the PlanCatalog (code source of truth) into the database. Idempotent —
 * safe to re-run; also invoked by the `billing:sync-plans` command.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PlanCatalog::plans() as $definition) {
            /** @var Plan $plan */
            $plan = Plan::updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'is_custom_priced' => $definition['is_custom_priced'] ?? false,
                    'sort_order' => $definition['sort_order'] ?? 0,
                    'is_public' => true,
                    'is_active' => true,
                    // BILL-004: cents per unit past each metered limit.
                    'overage_prices' => $definition['overages'] ?? null,
                ],
            );

            foreach ($definition['prices'] as $interval => $amount) {
                $plan->prices()->updateOrCreate(
                    ['interval' => $interval, 'currency' => 'USD'],
                    // BILL-003: a per-seat plan's amount is per user per period.
                    ['amount' => $amount, 'per_seat' => $definition['per_seat'] ?? false],
                );
            }

            $keys = [];

            foreach ($definition['features'] as $feature) {
                $key = $feature instanceof Feature ? $feature->value : $feature;
                $keys[] = $key;
                $plan->entitlements()->updateOrCreate(
                    ['key' => $key],
                    ['kind' => 'feature', 'bool_value' => true, 'int_value' => null],
                );
            }

            foreach ($definition['limits'] as $key => $value) {
                $keys[] = $key;
                $plan->entitlements()->updateOrCreate(
                    ['key' => $key],
                    ['kind' => 'limit', 'bool_value' => null, 'int_value' => $value],
                );
            }

            // Remove entitlements no longer defined for this plan.
            $plan->entitlements()->whereNotIn('key', $keys)->delete();
        }
    }
}
