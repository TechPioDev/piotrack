<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\SalesAsset;
use App\Models\SalesPlay;
use App\Models\ServiceLine;
use App\Models\Vertical;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EnablementController extends Controller
{
    private const TYPES = ['deck', 'one_pager', 'battlecard', 'script', 'email_template', 'roi_calculator', 'proof', 'persona', 'proposal_template', 'proposal'];

    public function index(): Response
    {
        return Inertia::render('sales/enablement/index', [
            'assets' => SalesAsset::latest('id')->get()->map(fn (SalesAsset $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'title' => $a->title,
                'description' => $a->description,
                'url' => $a->url,
                'content' => $a->content,
                'vertical_id' => $a->vertical_id,
                'service_line_id' => $a->service_line_id,
            ]),
            // ENAB-015/016: the binding taxonomies for collateral filters.
            'verticals' => Vertical::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'service_lines' => ServiceLine::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // ENAB-014: templates + deals for proposal generation.
            'proposal_templates' => SalesAsset::where('type', 'proposal_template')->orderBy('title')->get(['id', 'title']),
            'open_deals' => Deal::where('status', 'open')->latest('id')->limit(100)->get(['id', 'name']),
            'plays' => SalesPlay::latest('id')->get()->map(fn (SalesPlay $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'target_segment' => $p->target_segment,
                'steps' => $p->steps ?? [],
            ]),
            'types' => self::TYPES,
        ]);
    }

    public function storeAsset(Request $request): RedirectResponse
    {
        SalesAsset::create($request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string'],
            'url' => ['nullable', 'url', 'max:2048'],
            // ENAB-015/016: vertical/service collateral bindings.
            'vertical_id' => ['nullable', 'integer', TenantExists::in('verticals')],
            'service_line_id' => ['nullable', 'integer', TenantExists::in('service_lines')],
        ]));

        return back()->with('status', __('Asset added.'));
    }

    /**
     * ENAB-008: the ROI calculator, computed server-side and stored as a
     * roi_calculator asset snapshot - the math is auditable, never a claim.
     */
    public function roi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company' => ['required', 'string', 'max:150'],
            'employees' => ['required', 'integer', 'min:1', 'max:100000'],
            'downtime_hours_year' => ['required', 'numeric', 'min:0', 'max:8760'],
            'downtime_cost_hour' => ['required', 'integer', 'min:0'],       // minor units
            'managed_cost_month' => ['required', 'integer', 'min:0'],       // minor units
            'downtime_reduction_pct' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $exposure = (int) round($data['downtime_hours_year'] * $data['downtime_cost_hour']);
        $avoided = (int) round($exposure * $data['downtime_reduction_pct'] / 100);
        $annualCost = $data['managed_cost_month'] * 12;
        $net = $avoided - $annualCost;
        $roi = $annualCost > 0 ? round($avoided / $annualCost, 2) : null;

        $money = fn (int $minor): string => '$'.number_format($minor / 100, 2);
        $content = implode("\n", [
            __('ROI model for :company (:n employees)', ['company' => $data['company'], 'n' => $data['employees']]),
            __('Annual downtime exposure: :v (:h hrs x :c/hr)', ['v' => $money($exposure), 'h' => $data['downtime_hours_year'], 'c' => $money($data['downtime_cost_hour'])]),
            __('Downtime avoided at :p% reduction: :v', ['p' => $data['downtime_reduction_pct'], 'v' => $money($avoided)]),
            __('Managed services cost: :v/year', ['v' => $money($annualCost)]),
            __('Net annual benefit: :v', ['v' => $money($net)]),
            $roi !== null ? __('ROI: :rx return per dollar', ['r' => $roi]) : __('ROI: n/a (no cost entered)'),
            __('Assumptions entered by the rep; every figure above derives from them.'),
        ]);

        SalesAsset::create([
            'type' => 'roi_calculator',
            'title' => __('ROI model - :company', ['company' => $data['company']]),
            'description' => __('Net :v/year at :p% downtime reduction.', ['v' => $money($net), 'p' => $data['downtime_reduction_pct']]),
            'content' => $content,
        ]);

        return back()->with('status', __('ROI model saved to the library - net :v/year.', ['v' => $money($net)]));
    }

    /**
     * ENAB-014: render a proposal template's merge fields from a real deal
     * into a stored proposal asset.
     */
    public function generateProposal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', TenantExists::in('sales_assets')],
            'deal_id' => ['required', 'integer', TenantExists::in('deals')],
        ]);

        $template = SalesAsset::whereKey($data['asset_id'])->firstOrFail();
        if ($template->type !== 'proposal_template') {
            return back()->withErrors(['asset_id' => __('Pick a proposal template asset.')]);
        }

        $deal = Deal::with(['contact', 'contact.company'])->whereKey($data['deal_id'])->firstOrFail();

        $replacements = [
            '{{company}}' => $deal->contact?->company->name ?? '-',
            '{{contact}}' => $deal->contact?->fullName() ?? '-',
            '{{deal_name}}' => $deal->name,
            '{{deal_value}}' => '$'.number_format(((int) $deal->value) / 100, 2),
            '{{services}}' => ServiceLine::where('is_active', true)->orderBy('name')->pluck('name')->implode(', '),
            '{{date}}' => now()->toFormattedDateString(),
        ];

        SalesAsset::create([
            'type' => 'proposal',
            'title' => __('Proposal - :deal', ['deal' => $deal->name]),
            'description' => __('Generated from ":template".', ['template' => $template->title]),
            'content' => strtr((string) $template->content, $replacements),
            'tags' => ['deal:'.$deal->id],
        ]);

        return back()->with('status', __('Proposal generated for ":deal" - review it in the library before sending.', ['deal' => $deal->name]));
    }

    public function destroyAsset(SalesAsset $asset): RedirectResponse
    {
        $asset->delete();

        return back()->with('status', __('Asset removed.'));
    }

    public function storePlay(Request $request): RedirectResponse
    {
        SalesPlay::create($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_segment' => ['nullable', 'string', 'max:120'],
            'steps' => ['nullable', 'array'],
            'steps.*.title' => ['required', 'string', 'max:200'],
        ]));

        return back()->with('status', __('Play created.'));
    }

    public function destroyPlay(SalesPlay $play): RedirectResponse
    {
        $play->delete();

        return back()->with('status', __('Play removed.'));
    }
}
