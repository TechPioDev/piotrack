<?php

namespace App\Services\Seo;

use App\Models\Citation;
use App\Models\Keyword;
use App\Models\OutreachProspect;
use App\Models\Review;
use App\Models\SeoLocation;
use App\Models\SitePage;

/**
 * LSEO-001/009/010/015/016: per-location GBP readiness and local authority,
 * computed from what the platform holds first-party — profile completeness,
 * citation coverage/consistency, the local page, local keyword tracking,
 * review strength (REP) and location-bound placements. These are the actual
 * local ranking factors; pushing the profile to Google itself stays behind
 * the GBP API and is never claimed.
 */
class LocalAuthorityService
{
    /**
     * The Map-Pack readiness checklist for one branch, each check citing the
     * number behind it.
     *
     * @return array{score: int, checks: list<array{key: string, label: string, ok: bool, detail: string}>}
     */
    public function gbpReadiness(SeoLocation $location): array
    {
        $checks = [];

        $missing = array_keys(array_filter([
            'street' => $location->street,
            'city' => $location->city,
            'region' => $location->region,
            'postal code' => $location->postal_code,
            'phone' => $location->phone,
            'website' => $location->website,
        ], fn ($v) => $v === null || $v === ''));
        $checks[] = [
            'key' => 'profile', 'label' => __('Profile completeness'),
            'ok' => $missing === [],
            'detail' => $missing === []
                ? __('Every NAP field is on file.')
                : __('Missing: :fields — an incomplete profile ranks behind complete competitors.', ['fields' => implode(', ', $missing)]),
        ];

        $checks[] = [
            'key' => 'place_id', 'label' => __('Google Business Profile linked'),
            'ok' => $location->gbp_place_id !== null && $location->gbp_place_id !== '',
            'detail' => $location->gbp_place_id
                ? __('Place ID :id recorded.', ['id' => $location->gbp_place_id])
                : __('No place ID recorded — claim the profile and paste its place ID here.'),
        ];

        $citations = Citation::where('seo_location_id', $location->id)->get();
        $consistent = $citations->where('status', 'consistent')->count();
        $checks[] = [
            'key' => 'citations', 'label' => __('Citations built & consistent'),
            'ok' => $citations->count() >= 3 && $consistent === $citations->count(),
            'detail' => $citations->isEmpty()
                ? __('No citations yet — directory listings are the Map Pack\'s trust signal.')
                : __(':consistent of :total citations consistent.', ['consistent' => $consistent, 'total' => $citations->count()]),
        ];

        $pagePublished = SitePage::where('seo_location_id', $location->id)
            ->where('status', SitePage::STATUS_PUBLISHED)->exists();
        $checks[] = [
            'key' => 'local_page', 'label' => __('Local page published'),
            'ok' => $pagePublished,
            'detail' => $pagePublished
                ? __('A published page targets this branch.')
                : __('No published local page — the Map Pack links somewhere; give it a page about this branch.'),
        ];

        $localKeywords = $location->city !== null && $location->city !== ''
            ? Keyword::whereLike('phrase', '%'.$location->city.'%')->count()
            : 0;
        $checks[] = [
            'key' => 'keywords', 'label' => __('Local keywords tracked'),
            'ok' => $localKeywords > 0,
            'detail' => $localKeywords > 0
                ? __(':n keywords mention :city.', ['n' => $localKeywords, 'city' => (string) $location->city])
                : __('No tracked keyword mentions the city — local rank cannot be measured untracked.'),
        ];

        // LSEO-013 rides REP: review strength feeds Map-Pack position.
        $reviews = Review::count();
        $avg = $reviews > 0 ? round((float) Review::avg('rating'), 1) : 0.0;
        $checks[] = [
            'key' => 'reviews', 'label' => __('Review strength (REP)'),
            'ok' => $reviews >= 10 && $avg >= 4.0,
            'detail' => $reviews === 0
                ? __('No reviews recorded — drive review requests from Reputation.')
                : __(':n reviews averaging :avg — the Map Pack shows both numbers.', ['n' => $reviews, 'avg' => $avg]),
        ];

        $ok = count(array_filter($checks, fn (array $c) => $c['ok']));

        return [
            'score' => (int) round($ok / count($checks) * 100),
            'checks' => $checks,
        ];
    }

    /**
     * LSEO-015/016: the branch's authority rollup — location-bound placements
     * (local backlinks) with average DA, citations, page and reviews.
     *
     * @return array{citations: int, consistent_citations: int, placements: list<array{name: string, domain: string|null, url: string|null, domain_authority: int|null}>, avg_da: int|null, recommendations: list<string>}
     */
    public function authority(SeoLocation $location): array
    {
        $citations = Citation::where('seo_location_id', $location->id)->get();
        $placed = OutreachProspect::where('seo_location_id', $location->id)
            ->whereNotNull('placement_url')->get();

        $avgDa = $placed->whereNotNull('domain_authority')->avg('domain_authority');

        $recommendations = [];
        if ($citations->count() < 3) {
            $recommendations[] = __('Only :n citations for this branch — build the core directories first (cheapest local authority there is).', ['n' => $citations->count()]);
        }
        $inconsistent = $citations->where('status', 'inconsistent')->count();
        if ($inconsistent > 0) {
            $recommendations[] = __(':n citations are inconsistent — mismatched NAP actively hurts Map Pack trust.', ['n' => $inconsistent]);
        }
        if ($placed->isEmpty()) {
            $recommendations[] = __('No placements are bound to this branch — bind local outreach wins so the branch builds its own link profile.');
        }

        return [
            'citations' => $citations->count(),
            'consistent_citations' => $citations->where('status', 'consistent')->count(),
            'placements' => $placed->map(fn (OutreachProspect $p) => [
                'name' => $p->name,
                'domain' => $p->domain,
                'url' => $p->placement_url,
                'domain_authority' => $p->domain_authority,
            ])->values()->all(),
            'avg_da' => $avgDa !== null ? (int) round((float) $avgDa) : null,
            'recommendations' => $recommendations,
        ];
    }
}
