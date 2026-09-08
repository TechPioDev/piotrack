<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\ServiceLine;
use App\Services\Integrations\WebhookDispatcher;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    /**
     * Kanban board grouped by pipeline stage.
     */
    public function index(): Response
    {
        $pipeline = $this->defaultPipeline();

        $deals = Deal::with('contact:id,first_name,last_name', 'company:id,name', 'owner:id,name')
            ->where('pipeline_id', $pipeline->id)
            ->latest('id')
            ->get();

        $stages = [];
        foreach ($pipeline->stages as $stage) {
            $stageDeals = $deals->where('stage_id', $stage->id);
            $total = 0;
            $items = [];
            foreach ($stageDeals as $deal) {
                $total += $deal->value;
                $items[] = $this->presentDealCard($deal);
            }

            $stages[] = [
                'id' => $stage->id,
                'name' => $stage->name,
                'is_won' => $stage->is_won,
                'is_lost' => $stage->is_lost,
                'deals' => $items,
                'total' => $total,
            ];
        }

        return Inertia::render('crm/deals/index', [
            'pipeline' => ['id' => $pipeline->id, 'name' => $pipeline->name],
            'stages' => $stages,
            'owners' => $this->memberOptions(),
            // STRAT-014: deals bind to service lines for opportunity analysis.
            'service_lines' => ServiceLine::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Deal $deal): Response
    {
        $deal->load('contact:id,first_name,last_name', 'company:id,name', 'stage:id,name', 'owner:id,name');

        return Inertia::render('crm/deals/show', [
            'deal' => [
                'id' => $deal->id,
                'name' => $deal->name,
                'value' => $deal->value,
                'mrr' => $deal->mrr,
                'arr' => $deal->arr,
                'ltv' => $deal->ltv,
                'contract_term_months' => $deal->contract_term_months,
                'status' => $deal->status,
                'stage' => $deal->stage?->name,
                'contact' => $deal->contact !== null ? ['id' => $deal->contact->id, 'name' => $deal->contact->fullName()] : null,
                'company' => $deal->company !== null ? ['id' => $deal->company->id, 'name' => $deal->company->name] : null,
                'lead_source' => $deal->lead_source,
                'campaign' => $deal->campaign,
                'owner' => $deal->owner?->name,
                'expected_close_date' => $deal->expected_close_date,
            ],
            'activities' => $deal->activities()->with('user:id,name')->latest('id')->get()->map(fn ($a) => [
                'id' => $a->id, 'type' => $a->type, 'title' => $a->title, 'body' => $a->body,
                'due_at' => $a->due_at, 'completed_at' => $a->completed_at, 'user' => $a->user?->name, 'created_at' => $a->created_at,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pipeline = $this->defaultPipeline();
        $data = $this->validateData($request, $pipeline);

        $data['pipeline_id'] = $pipeline->id;
        $data['stage_id'] ??= ($pipeline->stages->firstWhere('is_won', false) ?? $pipeline->stages->first())->id;
        $data['owner_id'] ??= $request->user()->id;
        $deal = Deal::create($data);

        $this->audit->log('crm.deal.created', context: ['name' => $deal->name, 'value' => $deal->value], resourceType: 'deal', resourceId: (string) $deal->id, organizationId: $deal->organization_id);

        return back()->with('status', __('Deal created.'));
    }

    public function update(Request $request, Deal $deal): RedirectResponse
    {
        $deal->update($this->validateData($request, $deal->pipeline));
        $this->audit->log('crm.deal.updated', resourceType: 'deal', resourceId: (string) $deal->id, organizationId: $deal->organization_id);

        return back()->with('status', __('Deal updated.'));
    }

    public function moveStage(Request $request, Deal $deal): RedirectResponse
    {
        $validated = $request->validate([
            'stage_id' => ['required', Rule::exists('pipeline_stages', 'id')->where('pipeline_id', $deal->pipeline_id)],
        ]);

        $stage = PipelineStage::findOrFail($validated['stage_id']);
        $wasWon = $deal->status === 'won';
        $deal->update([
            'stage_id' => $stage->id,
            'status' => $stage->is_won ? 'won' : ($stage->is_lost ? 'lost' : 'open'),
            'closed_at' => ($stage->is_won || $stage->is_lost) ? now() : null,
            // BENCH-008/009: first entry into a proposal stage stamps the deal
            // for the funnel benchmarks; re-entry never rewrites the date.
            'proposal_sent_at' => $deal->proposal_sent_at ?? ($stage->is_proposal ? now() : null),
        ]);

        $this->audit->log('crm.deal.stage_changed', context: ['stage' => $stage->name], resourceType: 'deal', resourceId: (string) $deal->id, organizationId: $deal->organization_id);

        // INTG-009: a deal crossing into won fans out to webhook subscribers
        // exactly once per transition.
        if ($stage->is_won && ! $wasWon) {
            app(WebhookDispatcher::class)->dispatch('deal.won', [
                'deal_id' => $deal->id,
                'name' => $deal->name,
                'value' => $deal->value,
                'mrr' => $deal->mrr,
                'contact_id' => $deal->contact_id,
            ]);
        }

        return back();
    }

    public function destroy(Deal $deal): RedirectResponse
    {
        $this->audit->log('crm.deal.deleted', resourceType: 'deal', resourceId: (string) $deal->id, organizationId: $deal->organization_id);
        $deal->delete();

        return redirect()->route('crm.deals.index')->with('status', __('Deal deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, Pipeline $pipeline): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'contact_id' => ['nullable', TenantExists::active('contacts')],
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $this->currentOrganization->id())],
            'value' => ['nullable', 'numeric', 'min:0'],
            'mrr' => ['nullable', 'numeric', 'min:0'],
            'arr' => ['nullable', 'numeric', 'min:0'],
            'ltv' => ['nullable', 'numeric', 'min:0'],
            'contract_term_months' => ['nullable', 'integer', 'min:0'],
            'stage_id' => ['nullable', Rule::exists('pipeline_stages', 'id')->where('pipeline_id', $pipeline->id)],
            'lead_source' => ['nullable', 'string', 'max:120'],
            'campaign' => ['nullable', 'string', 'max:120'],
            'service_line_id' => ['nullable', TenantExists::in('service_lines')],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id' => ['nullable', Rule::exists('organization_user', 'user_id')->where('organization_id', $this->currentOrganization->id())],
        ]);

        // Money fields arrive in major units; store minor units.
        foreach (['value', 'mrr', 'arr', 'ltv'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = (int) round($validated[$field] * 100);
            }
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDealCard(Deal $deal): array
    {
        return [
            'id' => $deal->id,
            'name' => $deal->name,
            'value' => $deal->value,
            'status' => $deal->status,
            'contact' => $deal->contact?->fullName(),
            'company' => $deal->company?->name,
            'owner' => $deal->owner?->name,
            'service_line_id' => $deal->service_line_id,
        ];
    }

    private function defaultPipeline(): Pipeline
    {
        return Pipeline::where('is_default', true)->with('stages')->firstOrFail();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function memberOptions(): array
    {
        return $this->currentOrganization->get()->members()
            ->orderBy('name')->get(['users.id', 'users.name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();
    }
}
