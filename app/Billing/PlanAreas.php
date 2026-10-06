<?php

namespace App\Billing;

use App\Models\Organization;

/**
 * Which parts of the app a plan feature stands behind, by where they live.
 *
 * The `entitlement:` middleware is what actually refuses a page; this is the
 * same map read the other way round, so a menu can say "your plan does not
 * include this" before anyone clicks. It is deliberately a plain list rather
 * than something worked out from the routes on every request - and a test
 * (PlanAreasTest) walks the real route table to keep it honest, so a page moved
 * behind a feature, or added without one, fails the build instead of the menu
 * quietly telling a lie.
 *
 * The longest prefix that matches a path decides: `/ai/visibility` needs only
 * AI visibility although it sits under `/ai`, and `/seo/llmo` needs SEO and AI
 * visibility both.
 */
class PlanAreas
{
    /** @var array<string, list<string>> path prefix => plan features every page under it needs */
    public const AREAS = [
        '/crm' => ['crm'],
        '/marketing' => ['marketing'],
        '/marketing/automation' => ['marketing', 'automation'],
        '/website' => ['marketing'],
        '/seo' => ['seo'],
        '/seo/ai-visibility' => ['seo', 'ai_visibility'],
        '/seo/llmo' => ['seo', 'ai_visibility'],
        '/ads' => ['advertising'],
        '/content' => ['content'],
        '/sales' => ['sales'],
        '/analytics' => ['analytics'],
        '/ai' => ['ai'],
        '/ai/visibility' => ['ai_visibility'],
        '/chat' => ['chat'],
        '/settings/teams' => ['teams'],
        '/settings/audit-log' => ['audit_log'],
        '/api/v1' => ['api'],
    ];

    public function __construct(private readonly Entitlements $entitlements) {}

    /**
     * The plan features a path needs; none when it is open to every plan.
     *
     * @return list<string>
     */
    public static function featuresFor(string $path): array
    {
        $path = '/'.trim($path, '/');
        $best = null;

        foreach (array_keys(self::AREAS) as $prefix) {
            $under = $path === $prefix || str_starts_with($path, $prefix.'/');
            if ($under && ($best === null || strlen($prefix) > strlen($best))) {
                $best = $prefix;
            }
        }

        return $best === null ? [] : self::AREAS[$best];
    }

    /**
     * Each area, and whether this workspace's plan includes it.
     *
     * @return array<string, bool>
     */
    public function included(Organization $organization): array
    {
        $areas = [];
        foreach (self::AREAS as $prefix => $features) {
            $areas[$prefix] = true;
            foreach ($features as $feature) {
                $areas[$prefix] = $areas[$prefix] && $this->entitlements->feature($organization, $feature);
            }
        }

        return $areas;
    }
}
