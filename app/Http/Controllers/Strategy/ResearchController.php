<?php

namespace App\Http\Controllers\Strategy;

use App\Http\Controllers\Controller;
use App\Models\BuyerPersona;
use App\Services\Strategy\JourneyMapService;
use App\Services\Strategy\MarketSizingService;
use App\Services\Strategy\MessagingAnalysisService;
use App\Services\Strategy\PainPointResearchService;
use App\Services\Strategy\PersonaEvidenceService;
use App\Services\Strategy\PositioningResearchService;
use App\Services\Strategy\ServiceLineOpportunityService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * STRAT-005/007/008/009/014/015/016: the research workspace. Every panel is
 * computed from the tenant's own records; the genuinely human work (persona
 * narrative, interview findings, positioning prose) is authored HERE against
 * that evidence instead of in a vacuum.
 */
class ResearchController extends Controller
{
    public function index(
        MarketSizingService $sizing,
        PersonaEvidenceService $personaEvidence,
        PainPointResearchService $painPoints,
        JourneyMapService $journey,
        ServiceLineOpportunityService $serviceLines,
        PositioningResearchService $positioning,
        MessagingAnalysisService $messaging,
    ): Response {
        return Inertia::render('strategy/research', [
            'tam_defaults' => $sizing->derivedDefaults(),
            'personas' => BuyerPersona::orderBy('name')->get(),
            'persona_evidence' => $personaEvidence->evidence(),
            'seniorities' => BuyerPersona::SENIORITIES,
            'pain_points' => $painPoints->themes(),
            'journey' => $journey->map(),
            'service_lines' => $serviceLines->analysis(),
            'positioning' => $positioning->research(),
            'messaging' => $messaging->analysis(),
        ]);
    }

    /**
     * STRAT-005: compute the TAM/SAM/SOM model server-side and store it as an
     * auditable research item.
     */
    public function storeTam(Request $request, MarketSizingService $sizing, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'market_businesses' => ['required', 'integer', 'min:1', 'max:100000000'],
            'addressable_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reachable_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'avg_mrr' => ['nullable', 'numeric', 'min:0'],
            'win_rate_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $model = $sizing->model($validated);
        $item = $sizing->save($model, $validated['title']);

        $audit->log('strategy.tam.computed', context: ['title' => $validated['title']], resourceType: 'strategy_item', resourceId: (string) $item->id);

        return back()->with('status', __('Market sizing saved as a research item.'));
    }

    public function storePersona(Request $request): RedirectResponse
    {
        BuyerPersona::create($this->validatePersona($request));

        return back()->with('status', __('Persona created.'));
    }

    public function updatePersona(Request $request, BuyerPersona $persona): RedirectResponse
    {
        $persona->update($this->validatePersona($request));

        return back()->with('status', __('Persona updated.'));
    }

    public function destroyPersona(BuyerPersona $persona): RedirectResponse
    {
        $persona->delete();

        return back()->with('status', __('Persona removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePersona(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'role_title' => ['nullable', 'string', 'max:150'],
            'seniority' => ['nullable', Rule::in(BuyerPersona::SENIORITIES)],
            'goals' => ['nullable', 'string', 'max:2000'],
            'pains' => ['nullable', 'string', 'max:2000'],
            'channels' => ['nullable', 'string', 'max:2000'],
            'objections' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
