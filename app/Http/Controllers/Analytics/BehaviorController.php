<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\BehaviorAnalytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First-party behavior analytics (CRO-010/011/014): per-page behavior, click
 * heatmaps and bounce rates from the pixel's own events.
 */
class BehaviorController extends Controller
{
    public function index(Request $request, BehaviorAnalytics $behavior): Response
    {
        $data = $request->validate(['path' => ['nullable', 'string', 'max:300']]);

        $pages = $behavior->pages();
        $selected = $data['path'] ?? ($pages[0]['path'] ?? null);

        return Inertia::render('analytics/behavior', [
            'pages' => $pages,
            'bounces' => $behavior->bounceRates(),
            'heatmap' => $selected !== null ? $behavior->heatmap($selected) : null,
        ]);
    }
}
