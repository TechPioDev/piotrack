<?php

namespace App\Http\Controllers\Advertising;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\AdGroup;
use App\Models\AdKeyword;
use App\Models\AdMetric;
use App\Services\Advertising\AdCampaignService;
use App\Services\Advertising\AdMetricsService;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    private const PLATFORMS = ['google_search', 'microsoft', 'linkedin', 'meta', 'youtube'];

    public function __construct(
        private AdCampaignService $campaigns,
        private AdMetricsService $metrics,
    ) {}

    public function index(): Response
    {
        return Inertia::render('advertising/campaigns/index', [
            'campaigns' => AdCampaign::latest('id')->get()->map(fn (AdCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'platform' => $c->platform,
                'type' => $c->type,
                'objective' => $c->objective,
                'status' => $c->status,
                'daily_budget' => $c->daily_budget,
            ]),
            'platforms' => self::PLATFORMS,
        ]);
    }

    public function show(AdCampaign $campaign): Response
    {
        $campaign->load(['groups.ads', 'groups.keywords']);

        return Inertia::render('advertising/campaigns/show', [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'platform' => $campaign->platform,
                'type' => $campaign->type,
                'objective' => $campaign->objective,
                'status' => $campaign->status,
                'daily_budget' => $campaign->daily_budget,
                'total_budget' => $campaign->total_budget,
            ],
            'groups' => $campaign->groups->map(fn (AdGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'status' => $g->status,
                'bid_strategy' => $g->bid_strategy,
                'ads' => $g->ads->map(fn (Ad $a) => ['id' => $a->id, 'name' => $a->name, 'headline' => $a->headline, 'status' => $a->status])->all(),
                'keywords' => $g->keywords->map(fn (AdKeyword $k) => ['id' => $k->id, 'phrase' => $k->phrase, 'match_type' => $k->match_type, 'is_negative' => $k->is_negative])->all(),
            ])->all(),
            'kpi' => $this->metrics->campaignKpi($campaign, now()->subDays(30))->toArray(),
            'metrics' => $campaign->metrics()->orderByDesc('date')->limit(30)->get()
                ->map(fn (AdMetric $m) => [
                    'date' => $m->date->toDateString(),
                    'impressions' => $m->impressions,
                    'clicks' => $m->clicks,
                    'spend' => $m->spend,
                    'conversions' => $m->conversions,
                    'revenue' => $m->revenue,
                ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campaign = $this->campaigns->create($this->validateData($request));

        return redirect()->route('ads.campaigns.show', $campaign->id)->with('status', __('Campaign created.'));
    }

    public function update(Request $request, AdCampaign $campaign): RedirectResponse
    {
        $this->campaigns->update($campaign, $this->validateData($request));

        return back()->with('status', __('Campaign saved.'));
    }

    public function status(Request $request, AdCampaign $campaign): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'active', 'paused', 'ended'])]]);
        $this->campaigns->setStatus($campaign, $data['status']);

        return back()->with('status', __('Campaign :status.', ['status' => $data['status']]));
    }

    public function refreshMetrics(AdCampaign $campaign): RedirectResponse
    {
        $count = $this->metrics->refresh($campaign);

        return back()->with('status', __('Refreshed :n days of metrics.', ['n' => $count]));
    }

    public function destroy(AdCampaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return redirect()->route('ads.campaigns.index')->with('status', __('Campaign deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'platform' => ['required', Rule::in(self::PLATFORMS)],
            'type' => ['nullable', 'string', 'max:40'],
            'objective' => ['required', Rule::in(['leads', 'awareness', 'traffic', 'conversions'])],
            'daily_budget' => ['required', 'integer', 'min:0'],
            'total_budget' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'targeting' => ['nullable', 'array'],
            // MLOC-006: a local campaign is bound to its branch; null = central.
            'seo_location_id' => ['nullable', 'integer', TenantExists::in('seo_locations')],
        ]);
    }
}
