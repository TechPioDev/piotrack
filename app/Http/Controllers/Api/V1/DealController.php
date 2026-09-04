<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Deal;
use App\Models\Pipeline;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use App\Validation\TenantExists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DealController extends ApiController
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:open,won,lost'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            // API-001: filtering + sorting parity with the web app.
            'pipeline_id' => ['nullable', 'integer'],
            'stage_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in($this->sortKeys(['id', 'created_at', 'value']))],
        ]);

        $deals = Deal::with('company:id,name', 'stage:id,name', 'owner:id,name')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['pipeline_id'] ?? null, fn ($q, $id) => $q->where('pipeline_id', (int) $id))
            ->when($filters['stage_id'] ?? null, fn ($q, $id) => $q->where('stage_id', (int) $id))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', (int) $id))
            ->tap(fn ($q) => $this->applySort($q, $filters['sort'] ?? null))
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString()
            ->through(fn (Deal $d) => $this->transform($d));

        return $this->collection($deals);
    }

    public function show(Deal $deal): JsonResponse
    {
        $deal->load('company:id,name', 'stage:id,name', 'owner:id,name', 'contact:id,first_name,last_name');

        return $this->item($this->transform($deal));
    }

    /**
     * API-005: create a deal — default pipeline/stage resolution mirrored from
     * the web controller, and a given stage must belong to that pipeline.
     */
    public function store(Request $request): JsonResponse
    {
        $pipeline = Pipeline::where('is_default', true)->with('stages')->firstOrFail();
        $data = $this->validateData($request, $pipeline, required: true);

        $data['pipeline_id'] = $pipeline->id;
        $data['stage_id'] ??= ($pipeline->stages->firstWhere('is_won', false) ?? $pipeline->stages->first())->id;
        $data['owner_id'] ??= $request->user()->getAuthIdentifier();
        $deal = Deal::create($data);

        $this->audit->log('crm.deal.created', context: ['name' => $deal->name, 'via' => 'api'], resourceType: 'deal', resourceId: (string) $deal->id, organizationId: $deal->organization_id);

        $deal->load('company:id,name', 'stage:id,name', 'owner:id,name');

        return $this->item($this->transform($deal), 201);
    }

    /** API-005: update a deal (stage stays within the deal's own pipeline). */
    public function update(Request $request, Deal $deal): JsonResponse
    {
        $deal->update($this->validateData($request, $deal->pipeline, required: false));

        $this->audit->log('crm.deal.updated', context: ['via' => 'api'], resourceType: 'deal', resourceId: (string) $deal->id, organizationId: $deal->organization_id);

        $deal->load('company:id,name', 'stage:id,name', 'owner:id,name');

        return $this->item($this->transform($deal));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, Pipeline $pipeline, bool $required): array
    {
        return $request->validate([
            'name' => [$required ? 'required' : 'sometimes', 'string', 'max:200'],
            'contact_id' => ['nullable', TenantExists::active('contacts')],
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $this->currentOrganization->id())],
            'value' => ['nullable', 'numeric', 'min:0'],
            'mrr' => ['nullable', 'numeric', 'min:0'],
            'stage_id' => ['nullable', Rule::exists('pipeline_stages', 'id')->where('pipeline_id', $pipeline->id)],
            'lead_source' => ['nullable', 'string', 'max:120'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id' => ['nullable', Rule::exists('organization_user', 'user_id')->where('organization_id', $this->currentOrganization->id())],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Deal $deal): array
    {
        return [
            'id' => $deal->id,
            'name' => $deal->name,
            'value' => $deal->value,
            'currency' => $deal->currency,
            'status' => $deal->status,
            'stage' => $deal->stage?->name,
            'company' => $deal->company !== null
                ? ['id' => $deal->company->id, 'name' => $deal->company->name]
                : null,
            'owner' => $deal->owner !== null
                ? ['id' => $deal->owner->id, 'name' => $deal->owner->name]
                : null,
            'expected_close_date' => $deal->expected_close_date?->toDateString(),
            'created_at' => $deal->created_at?->toIso8601String(),
        ];
    }
}
