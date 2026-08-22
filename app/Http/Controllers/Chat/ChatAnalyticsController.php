<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatWidget;
use App\Services\Chat\ChatAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Website chat reporting: engagement, the conversion funnel, where visitors stop
 * answering, and a plain per-widget comparison.
 */
class ChatAnalyticsController extends Controller
{
    public function __construct(private readonly ChatAnalyticsService $analytics) {}

    public function __invoke(Request $request): Response
    {
        $days = (int) $request->integer('days', 30);
        $days = in_array($days, [7, 30, 90, 365], true) ? $days : 30;
        $since = now()->subDays($days);

        $widgetId = $request->integer('widget') ?: null;
        // A widget id from the query string must belong to this tenant.
        if ($widgetId !== null && ! ChatWidget::query()->whereKey($widgetId)->exists()) {
            $widgetId = null;
        }

        return Inertia::render('chat/analytics/index', [
            'summary' => $this->analytics->summary($since, $widgetId),
            'funnel' => $this->analytics->funnel($since, $widgetId),
            'dropOff' => $this->analytics->dropOff($since, $widgetId),
            'widgets' => $this->analytics->byWidget($since),
            'filters' => ['days' => $days, 'widget' => $widgetId],
            'widgetOptions' => ChatWidget::query()->get(['id', 'name'])
                ->map(fn (ChatWidget $w) => ['id' => $w->id, 'name' => $w->name])->all(),
        ]);
    }
}
