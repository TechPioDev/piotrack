<?php

namespace App\Billing;

/**
 * The code source of truth for the plan catalog (BILL-001). Seeded into the
 * plans/plan_prices/plan_entitlements tables by PlanSeeder / `billing:sync-plans`,
 * which remain the runtime source (configurable, admin-editable in Stage 13).
 *
 * Amounts are minor units (cents). Annual amounts are the full yearly price
 * (already discounted vs. 12× monthly). A `null` limit means unlimited.
 */
class PlanCatalog
{
    public const DEFAULT_TRIAL_PLAN = 'growth';

    public const TRIAL_DAYS = 14;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function plans(): array
    {
        return [
            [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'For a single practitioner getting started.',
                'sort_order' => 1,
                'prices' => ['monthly' => 4900, 'annual' => 47000],
                'features' => [Feature::Crm],
                'limits' => [Limit::Members->value => 3, Limit::Contacts->value => 1000],
            ],
            [
                'code' => 'growth',
                'name' => 'Growth',
                'description' => 'For growing MSP marketing teams.',
                'sort_order' => 2,
                'prices' => ['monthly' => 14900, 'annual' => 143000],
                'features' => [Feature::Crm, Feature::Marketing, Feature::Content, Feature::Seo, Feature::Automation, Feature::Teams, Feature::AuditLog, Feature::Chat],
                'limits' => [Limit::Members->value => 10, Limit::Contacts->value => 10000, Limit::Emails->value => 25000],
            ],
            [
                'code' => 'professional',
                'name' => 'Professional',
                'description' => 'Full-funnel marketing and sales operations.',
                'sort_order' => 3,
                'prices' => ['monthly' => 34900, 'annual' => 335000],
                'features' => [
                    Feature::Crm, Feature::Marketing, Feature::Content, Feature::Seo, Feature::Advertising, Feature::Sales,
                    Feature::Analytics, Feature::Ai, Feature::Automation, Feature::Teams, Feature::AuditLog,
                    Feature::AiVisibility, Feature::Api, Feature::Chat,
                ],
                'limits' => [
                    Limit::Members->value => 25, Limit::Contacts->value => 50000,
                    Limit::Emails->value => 100000, Limit::AiCredits->value => 5000,
                ],
                // BILL-004: metered overage, cents per unit past the limit.
                'overages' => [Limit::AiCredits->value => 2],
            ],
            [
                'code' => 'agency',
                'name' => 'Agency',
                'description' => 'For agencies managing many MSP clients.',
                'sort_order' => 4,
                'prices' => ['monthly' => 74900, 'annual' => 719000],
                'features' => [
                    Feature::Crm, Feature::Marketing, Feature::Content, Feature::Seo, Feature::Advertising, Feature::Sales,
                    Feature::Analytics, Feature::Ai, Feature::Automation, Feature::Teams, Feature::AuditLog,
                    Feature::AiVisibility, Feature::Api, Feature::WhiteLabel, Feature::Chat,
                ],
                'limits' => [
                    Limit::Members->value => 100, Limit::Emails->value => 500000,
                    Limit::AiCredits->value => 20000,
                ],
                'overages' => [Limit::AiCredits->value => 1],
            ],
            [
                // BILL-003: per-seat pricing — the amount is per user per period.
                'code' => 'team',
                'name' => 'Team',
                'description' => 'Per-seat pricing for teams that scale headcount before scope.',
                'sort_order' => 5,
                'per_seat' => true,
                'prices' => ['monthly' => 4900, 'annual' => 47000],
                'features' => [Feature::Crm, Feature::Marketing, Feature::Content, Feature::Seo, Feature::Automation, Feature::Teams, Feature::AuditLog, Feature::Chat],
                'limits' => [Limit::Contacts->value => 25000, Limit::Emails->value => 50000],
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'Custom scale, security and support.',
                'sort_order' => 6,
                'is_custom_priced' => true,
                'prices' => [],
                'features' => Feature::cases(),
                'limits' => [], // all unlimited
            ],
        ];
    }

    /**
     * The most restrictive baseline used when an organization has no active
     * subscription (free fallback).
     *
     * @return array{features: list<string>, limits: array<string, int|null>}
     */
    public static function freeFallback(): array
    {
        return [
            'features' => [Feature::Crm->value],
            'limits' => [Limit::Members->value => 1],
        ];
    }

    /**
     * Purchasable add-ons (BILL-005): a monthly price plus entitlement grants.
     * Attached per subscription; the central Entitlements resolver applies the
     * limit boosts, and renewals bill one line per add-on.
     *
     * @return array<string, array{name: string, price: int, grants: array<string, int>}>
     */
    public static function addons(): array
    {
        return [
            'ai_credit_pack' => ['name' => 'AI credit pack (+500/mo)', 'price' => 2000, 'grants' => [Limit::AiCredits->value => 500]],
            'extra_locations' => ['name' => 'Extra locations (+5)', 'price' => 1500, 'grants' => [Limit::Locations->value => 5]],
            'extra_keywords' => ['name' => 'Extra tracked keywords (+100)', 'price' => 1000, 'grants' => [Limit::Keywords->value => 100]],
        ];
    }
}
