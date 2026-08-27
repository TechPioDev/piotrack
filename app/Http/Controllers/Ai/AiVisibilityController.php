<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiPrompt;
use App\Models\AiVisibilityCheck;
use App\Seo\SeoProviderManager;
use App\Services\Ai\AiVisibilityDashboard;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiVisibilityController extends Controller
{
    public function __construct(private AiVisibilityDashboard $dashboard) {}

    public function index(): Response
    {
        return Inertia::render('ai/visibility', [
            // The same disclosure the SEO surface carries: this screen shows
            // competitors and citations the fixture driver invents.
            'aiSource' => [
                'name' => app(SeoProviderManager::class)->aiProviderName(),
                'live' => app(SeoProviderManager::class)->isAiLive(),
            ],
            'frequencies' => $this->dashboard->frequencies(),
            'share_of_voice' => $this->dashboard->shareOfVoice(),
            'by_engine' => $this->dashboard->byEngine(),
            'competitors' => $this->dashboard->competitorComparison(),
            'by_service' => $this->dashboard->byDimension('service'),
            'by_city' => $this->dashboard->byDimension('city'),
            'by_vertical' => $this->dashboard->byDimension('vertical'),
            'trend' => $this->dashboard->trend(),
            'alert' => $this->dashboard->alert(),
            'prompts' => AiPrompt::latest('id')->get()->map(fn (AiPrompt $p) => [
                'id' => $p->id,
                'text' => $p->text,
                'category' => $p->category,
                'service' => $p->service,
                'city' => $p->city,
                'vertical' => $p->vertical,
                'is_active' => $p->is_active,
            ]),
            'engines' => AiVisibilityDashboard::ENGINES,
            // live|simulated per engine (AIVM): ChatGPT/Gemini flip to live the
            // moment a key exists; the rest stay labeled simulated.
            'engineStatuses' => app(SeoProviderManager::class)->engineStatuses(AiVisibilityDashboard::ENGINES),
            // The receipts: latest checks with the answer text behind the numbers.
            'evidence' => AiVisibilityCheck::latest('checked_at')->limit(10)
                ->get(['id', 'prompt', 'engine', 'provider', 'mentioned', 'position', 'answer_excerpt', 'checked_at'])
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'prompt' => $c->prompt,
                    'engine' => $c->engine,
                    'provider' => $c->provider,
                    'mentioned' => (bool) $c->mentioned,
                    'position' => $c->position,
                    'answer_excerpt' => $c->answer_excerpt,
                    'checked_at' => $c->checked_at?->diffForHumans(),
                ]),
        ]);
    }

    public function storePrompt(Request $request): RedirectResponse
    {
        AiPrompt::create($request->validate([
            'text' => ['required', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:100'],
            'service' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'vertical' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]));

        return back()->with('status', __('Prompt added to the library.'));
    }

    public function destroyPrompt(AiPrompt $prompt): RedirectResponse
    {
        $prompt->delete();

        return back()->with('status', __('Prompt removed.'));
    }

    public function run(Request $request, CurrentOrganization $current): RedirectResponse
    {
        $data = $request->validate(['brand' => ['nullable', 'string', 'max:150']]);
        $organization = $current->get();
        $brand = $data['brand'] ?? ($organization !== null ? $organization->name : 'our brand');

        $checks = $this->dashboard->runLibrary($brand);

        return back()->with('status', __(':count visibility checks recorded.', ['count' => $checks]));
    }
}
