<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\BrandProfile;
use App\Models\Keyword;
use App\Seo\Contracts\RankProvider;
use App\Seo\SeoProviderManager;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\FunnelInsights;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsDashboardController extends Controller
{
    public function __invoke(AnalyticsService $analytics, FunnelInsights $insights): Response
    {
        return Inertia::render('analytics/dashboard', [
            'metrics' => $analytics->dashboard(),
            // CRO-012/013/015/016: step drop-off, winning paths and the
            // recommendations they trigger — every line cites its number.
            'funnel_insights' => [
                'drop_off' => $insights->dropOff(),
                'paths' => $insights->conversionPaths(),
                'recommendations' => $insights->recommendations(),
            ],
            // ANLY-012: map-pack positions through the rank seam, provider-labeled.
            'map_rankings' => $this->mapRankings(),
        ]);
    }

    /**
     * @return array{provider: string, rows: list<array{keyword: string, location: string|null, position: int|null}>}
     */
    private function mapRankings(): array
    {
        $provider = app(RankProvider::class);
        $business = BrandProfile::first()?->legal_name;

        $rows = [];
        if ($business !== null && $business !== '') {
            foreach (Keyword::where('is_tracked', true)->whereNotNull('location')->limit(10)->get() as $keyword) {
                $rows[] = [
                    'keyword' => (string) $keyword->phrase,
                    'location' => $keyword->location,
                    'position' => $provider->localPack((string) $keyword->phrase, $business, $keyword->location),
                ];
            }
        }

        return [
            'provider' => app(SeoProviderManager::class)->rankProviderName(),
            'rows' => $rows,
        ];
    }
}
