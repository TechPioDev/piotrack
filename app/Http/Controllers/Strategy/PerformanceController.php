<?php

namespace App\Http\Controllers\Strategy;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\LeadReplacement;
use App\Models\PerformanceAgreement;
use App\Models\PerformanceReview;
use App\Services\Strategy\PerformanceService;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    public function __construct(private PerformanceService $performance) {}

    public function index(): Response
    {
        return Inertia::render('strategy/performance', [
            'agreements' => PerformanceAgreement::latest('id')->get()->map(fn (PerformanceAgreement $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'model' => $a->model,
                'lead_target' => $a->lead_target,
                'sql_target' => $a->sql_target,
                'meeting_target' => $a->meeting_target,
                'quality_criteria' => $a->quality_criteria,
                'deliverables' => $a->deliverables,
                'sla_days' => $a->sla_days,
                'status' => $a->status,
                'period_start' => $a->period_start?->toDateString(),
                'period_end' => $a->period_end?->toDateString(),
                'attainment' => $this->performance->attainment($a),
                // PERF-004: automatic promised-vs-delivered reconciliation.
                'reconciliation' => $this->performance->reconcileDeliverables($a),
            ]),
            'replacements' => LeadReplacement::latest('id')->limit(50)->get()->map(fn (LeadReplacement $r) => [
                'id' => $r->id,
                'contact_id' => $r->contact_id,
                'reason' => $r->reason,
                'replaced_at' => $r->replaced_at?->toIso8601String(),
            ]),
            'models' => PerformanceAgreement::MODELS,
            // PERF-011: stored ROI review artefacts, newest first.
            'reviews' => PerformanceReview::with('agreement:id,name')->latest('id')->limit(20)->get()->map(fn (PerformanceReview $r) => [
                'id' => $r->id,
                'agreement' => $r->agreement?->name,
                'period_start' => $r->period_start?->toDateString(),
                'period_end' => $r->period_end?->toDateString(),
                'won_revenue' => $r->data['won_revenue'] ?? 0,
                'ad_spend' => $r->data['ad_spend'] ?? 0,
                'roi' => $r->data['roi'] ?? null,
                'all_targets_met' => $r->data['attainment']['all_targets_met'] ?? false,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /** PERF-011: generate and store the formal ROI review for an agreement. */
    public function roiReview(PerformanceAgreement $agreement): RedirectResponse
    {
        $review = $this->performance->generateRoiReview($agreement);

        return back()->with('status', __('ROI review stored for ":name" (:period).', [
            'name' => $agreement->name,
            'period' => ($review->period_start?->toDateString() ?? '—').' → '.($review->period_end?->toDateString() ?? '—'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->performance->create($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'model' => ['required', Rule::in(PerformanceAgreement::MODELS)],
            'lead_target' => ['nullable', 'integer', 'min:0'],
            'sql_target' => ['nullable', 'integer', 'min:0'],
            'meeting_target' => ['nullable', 'integer', 'min:0'],
            'quality_criteria' => ['nullable', 'array'],
            'deliverables' => ['nullable', 'array'],
            'sla_days' => ['nullable', 'integer', 'min:1'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
        ]));

        return back()->with('status', __('Performance agreement created.'));
    }

    public function replaceLead(Request $request, PerformanceAgreement $agreement): RedirectResponse
    {
        $data = $request->validate([
            'contact_id' => ['required', 'integer', TenantExists::active('contacts')],
            'replacement_contact_id' => ['nullable', 'integer', TenantExists::active('contacts')],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->performance->replaceLead(
            $agreement,
            Contact::findOrFail($data['contact_id']),
            $data['reason'],
            isset($data['replacement_contact_id']) ? Contact::find($data['replacement_contact_id']) : null,
        );

        return back()->with('status', __('Lead recorded as replaced.'));
    }

    public function destroy(PerformanceAgreement $agreement): RedirectResponse
    {
        $agreement->delete();

        return back()->with('status', __('Agreement removed.'));
    }
}
