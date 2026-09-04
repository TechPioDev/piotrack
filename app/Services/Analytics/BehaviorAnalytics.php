<?php

namespace App\Services\Analytics;

use App\Models\VisitorEvent;
use Illuminate\Support\Collection;

/**
 * First-party behavior analytics (CRO-010/011/014): heatmaps, per-page
 * behavior and bounce rates computed from the pixel's own events — clicks,
 * scroll depths and pageviews. What a third-party recorder would add beyond
 * this is session replay; nothing here pretends to be that.
 */
class BehaviorAnalytics
{
    /** The tracker's session window (minutes) — sessions rebuilt to match. */
    public const SESSION_MINUTES = 30;

    /** A landing path with fewer sessions than this reports no bounce rate. */
    public const MIN_SESSIONS = 5;

    /**
     * Click-density heatmap + top targets + scroll-depth distribution for one
     * page, on a 10×10 grid (x = viewport %, y = document %).
     *
     * @return array{path: string, clicks: int, grid: list<list<int>>, targets: list<array{label: string, clicks: int}>, scroll: array{samples: int, avg_depth: int|null, buckets: array<string, int>}}
     */
    public function heatmap(string $path): array
    {
        $clicks = VisitorEvent::where('type', 'click')->where('path', $path)
            ->whereNotNull('x_pct')->whereNotNull('y_pct')->get(['x_pct', 'y_pct', 'title']);

        $grid = array_fill(0, 10, array_fill(0, 10, 0));
        foreach ($clicks as $click) {
            $grid[min(9, intdiv($click->y_pct, 10))][min(9, intdiv($click->x_pct, 10))]++;
        }

        $targets = $clicks->filter(fn (VisitorEvent $c) => (string) $c->title !== '')
            ->groupBy('title')
            ->map(fn (Collection $group, string $label) => ['label' => $label, 'clicks' => $group->count()])
            ->sortByDesc('clicks')->take(10)->values()->all();

        $depths = VisitorEvent::where('type', 'scroll')->where('path', $path)
            ->whereNotNull('y_pct')->pluck('y_pct');
        $buckets = ['0-25' => 0, '26-50' => 0, '51-75' => 0, '76-100' => 0];
        foreach ($depths as $depth) {
            $buckets[$depth <= 25 ? '0-25' : ($depth <= 50 ? '26-50' : ($depth <= 75 ? '51-75' : '76-100'))]++;
        }

        return [
            'path' => $path,
            'clicks' => $clicks->count(),
            'grid' => $grid,
            'targets' => $targets,
            'scroll' => [
                'samples' => $depths->count(),
                'avg_depth' => $depths->isEmpty() ? null : (int) round($depths->avg()),
                'buckets' => $buckets,
            ],
        ];
    }

    /**
     * Per-page behavior (CRO-011): views, unique visitors, clicks and average
     * scroll depth from the pixel's own events.
     *
     * @return list<array{path: string, pageviews: int, visitors: int, clicks: int, avg_scroll_depth: int|null}>
     */
    public function pages(): array
    {
        $rows = VisitorEvent::whereNotNull('path')
            ->toBase()
            ->selectRaw("path, sum(case when type = 'pageview' then 1 else 0 end) as views")
            ->selectRaw("count(distinct case when type = 'pageview' then visitor_id end) as visitors")
            ->selectRaw("sum(case when type = 'click' then 1 else 0 end) as clicks")
            ->selectRaw("avg(case when type = 'scroll' then y_pct end) as depth")
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(50)
            ->get();

        return $rows->filter(fn ($row) => (int) $row->views > 0)->map(fn ($row) => [
            'path' => (string) $row->path,
            'pageviews' => (int) $row->views,
            'visitors' => (int) $row->visitors,
            'clicks' => (int) $row->clicks,
            'avg_scroll_depth' => $row->depth !== null ? (int) round((float) $row->depth) : null,
        ])->values()->all();
    }

    /**
     * Bounce rates per landing path (CRO-014). Sessions are rebuilt from
     * pageview timestamps with the tracker's own 30-minute window; a session
     * of exactly one pageview bounced. Guarded: under MIN_SESSIONS the path
     * reports insufficient instead of a rate a single visit could swing.
     *
     * @return list<array{landing_path: string, sessions: int, bounces: int, bounce_rate: int|null, insufficient: bool}>
     */
    public function bounceRates(): array
    {
        $events = VisitorEvent::where('type', 'pageview')->whereNotNull('path')
            ->orderBy('visitor_id')->orderBy('created_at')->orderBy('id')
            ->get(['visitor_id', 'path', 'created_at']);

        $paths = [];
        foreach ($events->groupBy('visitor_id') as $visits) {
            /** @var list<array{landing: string, pageviews: int}> $sessions */
            $sessions = [];
            $last = null;

            foreach ($visits as $event) {
                if ($last === null || $event->created_at->greaterThan($last->copy()->addMinutes(self::SESSION_MINUTES))) {
                    $sessions[] = ['landing' => (string) $event->path, 'pageviews' => 0];
                }
                $sessions[count($sessions) - 1]['pageviews']++;
                $last = $event->created_at;
            }

            foreach ($sessions as $session) {
                $paths[$session['landing']] ??= ['sessions' => 0, 'bounces' => 0];
                $paths[$session['landing']]['sessions']++;
                if ($session['pageviews'] === 1) {
                    $paths[$session['landing']]['bounces']++;
                }
            }
        }

        return collect($paths)->map(function (array $row, string $path) {
            $insufficient = $row['sessions'] < self::MIN_SESSIONS;

            return [
                'landing_path' => $path,
                'sessions' => $row['sessions'],
                'bounces' => $row['bounces'],
                'bounce_rate' => $insufficient ? null : (int) round($row['bounces'] / $row['sessions'] * 100),
                'insufficient' => $insufficient,
            ];
        })->sortByDesc('sessions')->values()->all();
    }
}
