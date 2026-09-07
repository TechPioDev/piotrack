<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\AiPrompt;
use App\Models\BrandProfile;
use App\Models\ExpertProfile;
use App\Models\StructuredData;
use App\Services\Seo\AnswerEngineOptimizer;
use App\Services\Seo\KnowledgeGraphService;
use App\Services\Seo\LlmoContentScorer;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLM Optimization (LLMO): the tenant's knowledge graph, its retrieval-
 * readiness audit, expert profiles, and LLMO content scoring. Read requires
 * seo.view; changes require seo.ai.manage (routes enforce both, plus the
 * ai_visibility entitlement).
 */
class LlmoController extends Controller
{
    public function __construct(
        private KnowledgeGraphService $graph,
        private LlmoContentScorer $scorer,
        private AuditLogger $audit,
    ) {}

    public function index(AnswerEngineOptimizer $aeo): Response
    {
        $brand = BrandProfile::first();
        $graph = $this->graph->build();

        return Inertia::render('seo/llmo/index', [
            'completeness' => $this->graph->completeness(),
            // AEO-001/004/006/019: the answer-engine optimization layer.
            'aeo' => [
                'mined_questions' => $aeo->mineQuestions(),
                'snippet_targets' => $aeo->snippetTargets(),
                'conversational' => $aeo->conversationalCoverage(),
                'ai_overview' => $aeo->aiOverviewReadiness(),
            ],
            'entity' => [
                'legal_name' => $brand?->legal_name,
                'alternate_names' => $brand->alternate_names ?? [],
                'website_url' => $brand?->website_url,
                'logo_url' => $brand?->logo_url,
                'founded_year' => $brand?->founded_year,
                'same_as' => $brand->same_as ?? [],
                'disambiguation' => $brand?->disambiguation,
            ],
            'experts' => ExpertProfile::orderBy('name')->get()->map(fn (ExpertProfile $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'title' => $e->title,
                'credentials' => $e->credentials ?? [],
                'knows_about' => $e->knows_about ?? [],
                'is_active' => $e->is_active,
            ]),
            'graphJson' => json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'nodeCount' => count($graph['@graph']),
            'published' => StructuredData::where('schema_type', 'KnowledgeGraph')->latest('id')
                ->first(['id', 'created_at'])?->only(['id', 'created_at']),
            'scoreResult' => session('llmo_score'),
        ]);
    }

    /** AEO-001: add a mined question straight into the prompt library. */
    public function storeQuestion(Request $request): RedirectResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:255']]);

        AiPrompt::firstOrCreate(['text' => $data['text']], ['category' => 'mined', 'is_active' => true]);

        return back()->with('status', __('Question added to the prompt library — the next visibility run includes it.'));
    }

    public function updateEntity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:255'],
            'alternate_names' => ['array', 'max:10'],
            'alternate_names.*' => ['string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'founded_year' => ['nullable', 'integer', 'between:1900,2100'],
            'same_as' => ['array', 'max:15'],
            'same_as.*' => ['url', 'max:2048'],
            'disambiguation' => ['nullable', 'string', 'max:500'],
        ]);

        $brand = BrandProfile::firstOrCreate([]);
        $brand->update($data);

        $this->audit->log('seo.entity.updated', resourceType: 'brand_profile', resourceId: (string) $brand->id);

        return back()->with('status', __('Organization entity updated.'));
    }

    public function storeExpert(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'credentials' => ['array', 'max:20'],
            'credentials.*' => ['string', 'max:120'],
            'knows_about' => ['array', 'max:20'],
            'knows_about.*' => ['string', 'max:120'],
            'same_as' => ['array', 'max:10'],
            'same_as.*' => ['url', 'max:2048'],
        ]);

        $expert = ExpertProfile::create($data + ['is_active' => true]);

        $this->audit->log('seo.expert.created', context: ['name' => $expert->name], resourceType: 'expert_profile', resourceId: (string) $expert->id);

        return back()->with('status', __('Expert profile added.'));
    }

    public function destroyExpert(ExpertProfile $expert): RedirectResponse
    {
        $expert->delete();

        return back()->with('status', __('Expert profile removed.'));
    }

    public function publishGraph(): RedirectResponse
    {
        $item = $this->graph->publish();

        return back()->with('status', __('Knowledge graph published as structured data #:id.', ['id' => $item->id]));
    }

    public function scoreContent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'html' => ['required', 'string', 'max:200000'],
        ]);

        return back()->with('llmo_score', $this->scorer->score($data['html']));
    }
}
