<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\FunnelAsset;
use App\Models\FunnelStage;
use App\Services\Marketing\FunnelService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FunnelController extends Controller
{
    public function __construct(
        private FunnelService $funnels,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('marketing/funnels/index', [
            'funnels' => Funnel::with('stages')->latest('id')->get()->map(fn (Funnel $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'description' => $f->description,
                'stages' => $this->funnels->stageCounts($f),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'stages' => ['nullable', 'array'],
            'stages.*.name' => ['required', 'string', 'max:120'],
            'stages.*.category' => ['required', 'string', 'max:10'],
            'stages.*.lifecycle_stage' => ['nullable', 'string', 'max:40'],
        ]);

        $funnel = Funnel::create(['name' => $data['name'], 'description' => $data['description'] ?? null]);

        foreach ($data['stages'] ?? [] as $i => $stage) {
            $funnel->stages()->create([
                'name' => $stage['name'],
                'position' => $i + 1,
                'category' => $stage['category'],
                'lifecycle_stage' => $stage['lifecycle_stage'] ?? null,
            ]);
        }

        $this->audit->log('marketing.funnel.created', context: ['name' => $funnel->name], resourceType: 'funnel', resourceId: (string) $funnel->id, organizationId: $funnel->organization_id);

        return back()->with('status', __('Funnel created.'));
    }

    public function destroy(Funnel $funnel): RedirectResponse
    {
        $this->audit->log('marketing.funnel.deleted', context: ['name' => $funnel->name], resourceType: 'funnel', resourceId: (string) $funnel->id, organizationId: $funnel->organization_id);
        $funnel->delete();

        return back()->with('status', __('Funnel deleted.'));
    }

    /** The funnel as an executable map (FUNL): stages, conversion, assets, gaps. */
    public function show(Funnel $funnel): Response
    {
        return Inertia::render('marketing/funnels/show', [
            'funnel' => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'description' => $funnel->description,
            ],
            'stages' => $this->funnels->detail($funnel),
            'attachable' => $this->funnels->attachableOptions(),
        ]);
    }

    public function attachAsset(Request $request, Funnel $funnel, FunnelStage $stage): RedirectResponse
    {
        abort_unless((int) $stage->funnel_id === (int) $funnel->id, 404);

        $data = $request->validate([
            'asset_type' => ['required', Rule::in(array_keys(FunnelService::TYPES))],
            'asset_id' => ['required', 'integer'],
        ]);

        $this->funnels->attach($stage, $data['asset_type'], (int) $data['asset_id']);
        $this->audit->log('marketing.funnel.asset_attached', context: ['stage' => $stage->name, 'type' => $data['asset_type']], resourceType: 'funnel', resourceId: (string) $funnel->id, organizationId: $funnel->organization_id);

        return back()->with('status', __('Asset attached.'));
    }

    public function detachAsset(Funnel $funnel, FunnelStage $stage, FunnelAsset $asset): RedirectResponse
    {
        abort_unless((int) $stage->funnel_id === (int) $funnel->id && (int) $asset->funnel_stage_id === (int) $stage->id, 404);

        $asset->delete();
        $this->audit->log('marketing.funnel.asset_detached', context: ['stage' => $stage->name, 'type' => $asset->asset_type], resourceType: 'funnel', resourceId: (string) $funnel->id, organizationId: $funnel->organization_id);

        return back()->with('status', __('Asset removed.'));
    }
}
