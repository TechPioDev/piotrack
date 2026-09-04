<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\AiVisibilityCheck;
use App\Seo\SeoProviderManager;
use App\Services\Ai\AiVisibilityDashboard;
use App\Services\Seo\AiVisibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AiVisibilityController extends Controller
{
    public function __construct(private AiVisibilityService $ai) {}

    public function index(): Response
    {
        $checks = AiVisibilityCheck::latest('id')->limit(100)->get();

        return Inertia::render('seo/ai-visibility/index', [
            'engines' => config('seo.ai_engines'),
            // Stated plainly: the fixture driver invents competitors and
            // citations, so a screen showing them must say where they came from.
            'aiSource' => [
                'name' => app(SeoProviderManager::class)->aiProviderName(),
                'live' => app(SeoProviderManager::class)->isAiLive(),
            ],
            'checks' => $checks->map(fn (AiVisibilityCheck $c) => [
                'id' => $c->id,
                'prompt' => $c->prompt,
                'engine' => $c->engine,
                'brand' => $c->brand,
                'mentioned' => $c->mentioned,
                'position' => $c->position,
                'share_of_answer' => $c->share_of_answer,
                'cited_sources' => $c->cited_sources ?? [],
                'competitors' => $c->competitors ?? [],
                'checked_at' => $c->checked_at?->toIso8601String(),
            ]),
            'summary' => [
                'total' => $checks->count(),
                'mentioned' => $checks->where('mentioned', true)->count(),
                'avg_share' => (int) round($checks->where('mentioned', true)->avg('share_of_answer') ?? 0),
            ],
            // GEO-011..013: per-dimension recommendations with their numbers.
            'dimension_recommendations' => app(AiVisibilityDashboard::class)->dimensionRecommendations(),
            // GEO-014..016: the sources AI answers cite, covered or gap.
            'citation_sources' => app(AiVisibilityDashboard::class)->citationSources(),
        ]);
    }

    /** GEO-015/016: target a cited source through the outreach pipeline. */
    public function targetSource(Request $request): RedirectResponse
    {
        $data = $request->validate(['host' => ['required', 'string', 'max:255']]);
        $host = strtolower(trim($data['host']));

        app(AiVisibilityDashboard::class)->targetSource($host);

        return back()->with('status', __('":host" added to the AI citation sources outreach campaign.', ['host' => $host]));
    }

    public function check(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:500'],
            'brand' => ['required', 'string', 'max:120'],
            'engine' => ['required', Rule::in(config('seo.ai_engines'))],
        ]);

        $check = $this->ai->check($data['prompt'], $data['brand'], $data['engine']);

        return back()->with('status', $check->mentioned
            ? __('Brand mentioned (share :n%).', ['n' => $check->share_of_answer])
            : __('Brand not mentioned by :engine.', ['engine' => $data['engine']]));
    }
}
