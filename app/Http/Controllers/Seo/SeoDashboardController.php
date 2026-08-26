<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\AiVisibilityCheck;
use App\Models\Keyword;
use App\Models\SeoAudit;
use App\Models\SeoLocation;
use App\Seo\SeoProviderManager;
use App\Services\Seo\RankTracker;
use Inertia\Inertia;
use Inertia\Response;

class SeoDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $trackedKeywords = Keyword::where('is_tracked', true)->get();

        return Inertia::render('seo/dashboard', [
            // Stated plainly so fixture positions are never mistaken for real
            // rankings: the fixture driver derives a position from a hash.
            'rankSource' => [
                'name' => app(SeoProviderManager::class)->rankProviderName(),
                'live' => app(SeoProviderManager::class)->isRankLive(),
            ],

            'stats' => [
                'audits' => SeoAudit::count(),
                'avg_score' => (int) round(SeoAudit::avg('score') ?? 0),
                'keywords' => Keyword::count(),
                'page_one' => $trackedKeywords->filter(fn (Keyword $k) => RankTracker::isPageOne($k->current_position))->count(),
                'top_three' => $trackedKeywords->filter(fn (Keyword $k) => RankTracker::isTopThree($k->current_position))->count(),
                'locations' => SeoLocation::count(),
                'ai_checks' => AiVisibilityCheck::count(),
            ],
            'recentAudits' => SeoAudit::latest('id')->limit(5)->get()
                ->map(fn (SeoAudit $a) => ['id' => $a->id, 'url' => $a->url, 'score' => $a->score, 'issues_count' => $a->issues_count]),

            // Where tracked keywords actually sit (design-shell module):
            // unranked stays visible — pretending it away would flatter the number.
            'distribution' => [
                ['label' => 'Top 3', 'value' => $trackedKeywords->filter(fn (Keyword $k) => $k->current_position !== null && $k->current_position <= 3)->count()],
                ['label' => 'Page 1', 'value' => $trackedKeywords->filter(fn (Keyword $k) => $k->current_position !== null && $k->current_position >= 4 && $k->current_position <= 10)->count()],
                ['label' => '11–20', 'value' => $trackedKeywords->filter(fn (Keyword $k) => $k->current_position !== null && $k->current_position >= 11 && $k->current_position <= 20)->count()],
                ['label' => '21+', 'value' => $trackedKeywords->filter(fn (Keyword $k) => $k->current_position !== null && $k->current_position > 20)->count()],
                ['label' => 'Unranked', 'value' => $trackedKeywords->whereNull('current_position')->count()],
            ],

            // Audit score over the last audits, oldest first, as a trend.
            'auditTrend' => SeoAudit::latest('id')->limit(12)->get(['id', 'score', 'created_at'])
                ->reverse()->values()
                ->map(fn (SeoAudit $a) => ['label' => $a->created_at?->format('M j') ?? '#'.$a->id, 'value' => (int) $a->score]),
        ]);
    }
}
