<?php

namespace App\Http\Controllers\Strategy;

use App\Http\Controllers\Controller;
use App\Models\BrandAsset;
use App\Models\BrandProfile;
use App\Models\Engagement;
use App\Models\KpiTarget;
use App\Models\StrategyItem;
use App\Models\StrategyPlan;
use App\Services\Strategy\BrandPositioningService;
use App\Services\Strategy\KpiTargetService;
use App\Services\Strategy\MethodologyService;
use App\Services\Strategy\StrategyInsights;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use App\Support\Pdf;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The strategy, brand and training workspaces (STRAT / BRAND / TRAIN) plus the
 * five-P methodology view (METH). These surfaces STRUCTURE consulting work —
 * the analysis and creative remain human; the platform records, assigns,
 * schedules and reports on it.
 */
class StrategyController extends Controller
{
    public function index(MethodologyService $methodology, KpiTargetService $targets, StrategyInsights $insights): Response
    {
        return Inertia::render('strategy/dashboard', [
            // Computed analyses from the tenant's own records (STRAT close-out).
            'insights' => $insights->all(),
            'plans' => StrategyPlan::withCount('items')->latest('id')->get()->map(fn (StrategyPlan $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'summary' => $p->summary,
                'status' => $p->status,
                'items_count' => $p->getAttribute('items_count'),
                'period_start' => $p->period_start?->toDateString(),
                'period_end' => $p->period_end?->toDateString(),
            ]),
            'items' => StrategyItem::latest('id')->limit(200)->get()->map(fn (StrategyItem $i) => [
                'id' => $i->id,
                'strategy_plan_id' => $i->strategy_plan_id,
                'type' => $i->type,
                'title' => $i->title,
                'findings' => $i->findings,
                'recommendation' => $i->recommendation,
                'priority' => $i->priority,
                'status' => $i->status,
                'due_on' => $i->due_on?->toDateString(),
                'source_module' => $i->source_module,
            ]),
            'kpis' => $targets->attainment(),
            'methodology' => [
                'overall' => $methodology->overall(),
                'stages' => $methodology->assess(),
            ],
            'types' => StrategyItem::TYPES,
            'metrics' => KpiTarget::METRICS,
        ]);
    }

    public function storePlan(Request $request, AuditLogger $audit): RedirectResponse
    {
        $plan = StrategyPlan::create($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'completed'])],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
        ]));

        $audit->log('strategy.plan.created', context: ['name' => $plan->name], resourceType: 'strategy_plan', resourceId: (string) $plan->id);

        return back()->with('status', __('Strategy plan created.'));
    }

    public function storeItem(Request $request): RedirectResponse
    {
        StrategyItem::create($request->validate([
            'strategy_plan_id' => ['nullable', 'integer', TenantExists::in('strategy_plans')],
            'type' => ['required', Rule::in(StrategyItem::TYPES)],
            'title' => ['required', 'string', 'max:200'],
            'findings' => ['nullable', 'string', 'max:5000'],
            'recommendation' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'complete'])],
            'due_on' => ['nullable', 'date'],
            'source_module' => ['nullable', 'string', 'max:50'],
        ]));

        return back()->with('status', __('Strategy item added.'));
    }

    public function updateItem(Request $request, StrategyItem $item): RedirectResponse
    {
        $item->update($request->validate([
            'status' => ['sometimes', Rule::in(['open', 'in_progress', 'complete'])],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'findings' => ['nullable', 'string', 'max:5000'],
            'recommendation' => ['nullable', 'string', 'max:5000'],
        ]));

        return back()->with('status', __('Strategy item updated.'));
    }

    public function destroyItem(StrategyItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('status', __('Strategy item removed.'));
    }

    public function storeTarget(Request $request): RedirectResponse
    {
        KpiTarget::create($request->validate([
            'metric' => ['required', Rule::in(KpiTarget::METRICS)],
            'target_value' => ['required', 'integer', 'min:0'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
        ]));

        return back()->with('status', __('KPI target set.'));
    }

    public function destroyTarget(KpiTarget $target): RedirectResponse
    {
        $target->delete();

        return back()->with('status', __('KPI target removed.'));
    }

    /**
     * The brand workspace (BRAND) and training engagements (TRAIN).
     */
    public function brand(BrandPositioningService $positioning): Response
    {
        return Inertia::render('strategy/brand', [
            'profile' => BrandProfile::first(),
            // BRAND-001..012: positioning evidence from real records.
            'positioning' => [
                'discovery' => $positioning->discovery(),
                'competitorMessaging' => $positioning->competitorMessaging(),
                'differentiators' => $positioning->differentiators(),
                'icpAlignment' => $positioning->icpAlignment(),
                'evidence' => $positioning->positioningEvidence(),
            ],
            'assets' => BrandAsset::latest('id')->get()->map(fn (BrandAsset $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'title' => $a->title,
                'url' => $a->url,
                'notes' => $a->notes,
            ]),
            'asset_types' => BrandAsset::TYPES,
            'engagements' => Engagement::latest('id')->get()->map(fn (Engagement $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'topic' => $e->topic,
                'title' => $e->title,
                'status' => $e->status,
                'attendees' => $e->attendees,
                'notes' => $e->notes,
                'scheduled_at' => $e->scheduled_at?->toIso8601String(),
            ]),
            'engagement_types' => Engagement::TYPES,
            'engagement_topics' => Engagement::TOPICS,
        ]);
    }

    /**
     * BRAND-020/021/022: the captured identity as a shareable deliverable —
     * a native one-page style guide PDF.
     */
    public function styleGuide(): StreamedResponse
    {
        $brand = BrandProfile::first();
        $palette = $brand->palette ?? [];
        $typography = $brand->typography ?? [];

        $lines = [
            ['text' => 'Positioning: '.($brand?->positioning_statement ?: '(not captured yet)'), 'size' => 10],
            ['text' => 'Tagline: '.($brand?->tagline ?: '(not captured yet)'), 'size' => 10],
            ['text' => ''],
            ['text' => 'Color palette', 'size' => 13, 'bold' => true],
        ];
        foreach ($palette as $role => $value) {
            $lines[] = ['text' => ucfirst((string) $role).': '.$value];
        }
        if ($palette === []) {
            $lines[] = ['text' => '(no palette captured yet)', 'size' => 9];
        }
        $lines[] = ['text' => ''];
        $lines[] = ['text' => 'Typography', 'size' => 13, 'bold' => true];
        foreach ($typography as $role => $value) {
            $lines[] = ['text' => ucfirst((string) $role).': '.$value];
        }
        if ($typography === []) {
            $lines[] = ['text' => '(no typography captured yet)', 'size' => 9];
        }
        $lines[] = ['text' => ''];
        $lines[] = ['text' => 'Imagery direction', 'size' => 13, 'bold' => true];
        $lines[] = ['text' => $brand?->imagery_direction ?: '(not captured yet)', 'size' => 10];
        $lines[] = ['text' => ''];
        $lines[] = ['text' => 'Tone of voice: '.($brand?->tone_of_voice ?: '(not captured yet)'), 'size' => 10];

        $pdf = Pdf::document(
            (app(CurrentOrganization::class)->get()->name ?? 'Brand').' - Style Guide',
            $lines,
        );

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, 'style-guide.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function saveBrand(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'positioning_statement' => ['nullable', 'string', 'max:2000'],
            'usp' => ['nullable', 'string', 'max:2000'],
            'value_proposition' => ['nullable', 'string', 'max:2000'],
            'differentiators' => ['nullable', 'array'],
            'narrative' => ['nullable', 'string', 'max:5000'],
            'story' => ['nullable', 'string', 'max:5000'],
            'tone_of_voice' => ['nullable', 'string', 'max:255'],
            'messaging_hierarchy' => ['nullable', 'array'],
            'elevator_pitch' => ['nullable', 'string', 'max:2000'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'palette' => ['nullable', 'array'],
            'typography' => ['nullable', 'array'],
            'imagery_direction' => ['nullable', 'string', 'max:2000'],
            'guidelines_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $profile = BrandProfile::first();
        $profile !== null ? $profile->update($data) : BrandProfile::create($data);

        return back()->with('status', __('Brand profile saved.'));
    }

    public function storeAsset(Request $request): RedirectResponse
    {
        BrandAsset::create($request->validate([
            'type' => ['required', Rule::in(BrandAsset::TYPES)],
            'title' => ['required', 'string', 'max:200'],
            'url' => ['nullable', 'url', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('status', __('Brand asset added.'));
    }

    public function destroyAsset(BrandAsset $asset): RedirectResponse
    {
        $asset->delete();

        return back()->with('status', __('Brand asset removed.'));
    }

    public function storeEngagement(Request $request): RedirectResponse
    {
        Engagement::create($request->validate([
            'type' => ['required', Rule::in(Engagement::TYPES)],
            'topic' => ['nullable', Rule::in(Engagement::TOPICS)],
            'title' => ['required', 'string', 'max:200'],
            'scheduled_at' => ['nullable', 'date'],
            'attendees' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]));

        return back()->with('status', __('Engagement scheduled.'));
    }

    public function updateEngagement(Request $request, Engagement $engagement): RedirectResponse
    {
        $engagement->update($request->validate([
            'status' => ['required', Rule::in(['scheduled', 'completed', 'canceled'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]));

        return back()->with('status', __('Engagement updated.'));
    }
}
