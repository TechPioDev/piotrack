<?php

namespace App\Services;

use App\Models\BrandProfile;
use App\Models\Competitor;
use App\Models\KpiTarget;
use App\Models\ScoringRule;
use App\Models\SeoAudit;
use App\Models\SeoLocation;
use App\Models\ServiceLine;
use App\Models\Vertical;
use App\Services\Seo\TechnicalSeoAuditor;
use App\Support\AuditLogger;

/**
 * The guided setup wizard's writes (ONBD-006..012). Every step lands in the
 * real records the rest of the platform runs on — taxonomy, brand entity,
 * KPI targets, live scoring rules, competitors — so onboarding leaves no
 * parallel data silo behind, and progress derives from state (resumable for
 * free, like the rest of the checklist).
 */
class OnboardingSetup
{
    public function __construct(
        private AuditLogger $audit,
        private TechnicalSeoAuditor $auditor,
    ) {}

    /**
     * ONBD-006: what they sell, who to, and where — as taxonomy activation
     * plus the first branch record.
     *
     * @param  list<string>  $serviceKeys
     * @param  list<string>  $verticalKeys
     */
    public function businessProfile(array $serviceKeys, array $verticalKeys, ?string $city, ?string $region): void
    {
        ServiceLine::query()->update(['is_active' => false]);
        if ($serviceKeys !== []) {
            ServiceLine::whereIn('key', $serviceKeys)->update(['is_active' => true]);
        }

        Vertical::query()->update(['is_active' => false]);
        if ($verticalKeys !== []) {
            Vertical::whereIn('key', $verticalKeys)->update(['is_active' => true]);
        }

        if ($city !== null && $city !== '' && ! SeoLocation::where('city', $city)->exists()) {
            SeoLocation::create(['name' => $city, 'city' => $city, 'region' => $region, 'is_active' => true]);
        }

        $this->audit->log('onboarding.business_profile', context: ['services' => count($serviceKeys), 'verticals' => count($verticalKeys)]);
    }

    /** ONBD-007: the canonical site everything else audits and anchors on. */
    public function website(string $url): void
    {
        BrandProfile::firstOrCreate([])->update(['website_url' => $url]);

        $this->audit->log('onboarding.website', context: ['url' => $url]);
    }

    /**
     * ONBD-008: goals as KPI targets for the next quarter — the same rows the
     * strategy dashboard tracks, not a separate wish list.
     *
     * @param  array{leads?: ?int, sqls?: ?int, mrr?: ?int}  $goals
     */
    public function goals(array $goals): int
    {
        $created = 0;
        foreach (['leads', 'sqls', 'mrr'] as $metric) {
            $value = $goals[$metric] ?? null;
            if ($value === null || (int) $value <= 0) {
                continue;
            }

            KpiTarget::updateOrCreate(
                ['metric' => $metric, 'period_start' => now()->startOfDay()],
                ['target_value' => (int) $value, 'period_end' => now()->addDays(90)->endOfDay()],
            );
            $created++;
        }

        if ($created > 0) {
            $this->audit->log('onboarding.goals', context: ['targets' => $created]);
        }

        return $created;
    }

    /**
     * ONBD-009: the ICP as LIVE firmographic scoring rules — a lead matching
     * the profile scores higher from the moment setup finishes (§20 weights:
     * size 15, region 10, industry 5). Idempotent by rule name.
     */
    public function icp(?string $industry, ?string $companySize, ?string $region): int
    {
        $rules = array_filter([
            $companySize !== null && $companySize !== '' ? ['name' => 'ICP: company size', 'attribute' => 'company_size', 'operator' => 'equals', 'value' => $companySize, 'points' => 15] : null,
            $region !== null && $region !== '' ? ['name' => 'ICP: region', 'attribute' => 'company_region', 'operator' => 'contains', 'value' => $region, 'points' => 10] : null,
            $industry !== null && $industry !== '' ? ['name' => 'ICP: industry', 'attribute' => 'company_industry', 'operator' => 'contains', 'value' => $industry, 'points' => 5] : null,
        ]);

        foreach ($rules as $rule) {
            ScoringRule::updateOrCreate(
                ['name' => $rule['name']],
                $rule + ['category' => 'firmographic', 'is_active' => true],
            );
        }

        if ($rules !== []) {
            $this->audit->log('onboarding.icp', context: ['rules' => count($rules)]);
        }

        return count($rules);
    }

    /**
     * ONBD-010: who they lose deals to, deduped by domain.
     *
     * @param  list<array{name: string, domain?: ?string}>  $competitors
     */
    public function competitors(array $competitors): int
    {
        $created = 0;
        foreach ($competitors as $competitor) {
            $domain = isset($competitor['domain']) && $competitor['domain'] !== '' ? $competitor['domain'] : null;

            $exists = $domain !== null
                ? Competitor::where('domain', $domain)->exists()
                : Competitor::where('name', $competitor['name'])->exists();

            if (! $exists) {
                Competitor::create(['name' => $competitor['name'], 'domain' => $domain, 'is_tracked' => true]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * ONBD-012: finishing setup starts the first real work — a technical
     * audit of the site they just told us about. No URL, no audit, no error.
     */
    public function complete(): ?SeoAudit
    {
        $url = BrandProfile::first()?->website_url;

        $this->audit->log('onboarding.completed', context: ['audit' => $url !== null]);

        if ($url === null || $url === '') {
            return null;
        }

        return $this->auditor->crawl($url);
    }
}
