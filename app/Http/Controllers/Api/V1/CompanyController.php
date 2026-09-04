<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Company;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends ApiController
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            // API-001: filtering + sorting parity with the web app.
            'industry' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in($this->sortKeys(['id', 'created_at', 'name']))],
        ]);

        $companies = Company::withCount('contacts', 'deals')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->whereLike('name', "%{$s}%")->orWhereLike('domain', "%{$s}%"))
            ->when($filters['industry'] ?? null, fn ($q, $industry) => $q->where('industry', $industry))
            ->tap(fn ($q) => $this->applySort($q, $filters['sort'] ?? null))
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString()
            ->through(fn (Company $c) => $this->transform($c));

        return $this->collection($companies);
    }

    public function show(Company $company): JsonResponse
    {
        $company->loadCount('contacts', 'deals');

        return $this->item($this->transform($company));
    }

    /** API-005: create a company. */
    public function store(Request $request): JsonResponse
    {
        $company = Company::create($this->validateData($request, required: true));

        $this->audit->log('crm.company.created', context: ['name' => $company->name, 'via' => 'api'], resourceType: 'company', resourceId: (string) $company->id, organizationId: $company->organization_id);

        $company->loadCount('contacts', 'deals');

        return $this->item($this->transform($company), 201);
    }

    /** API-005: update a company. */
    public function update(Request $request, Company $company): JsonResponse
    {
        $company->update($this->validateData($request, required: false));

        $this->audit->log('crm.company.updated', context: ['via' => 'api'], resourceType: 'company', resourceId: (string) $company->id, organizationId: $company->organization_id);

        $company->loadCount('contacts', 'deals');

        return $this->item($this->transform($company));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, bool $required): array
    {
        return $request->validate([
            'name' => [$required ? 'required' : 'sometimes', 'string', 'max:200'],
            'domain' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:120'],
            'size' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'domain' => $company->domain,
            'industry' => $company->industry,
            'size' => $company->size,
            'phone' => $company->phone,
            'website' => $company->website,
            'contacts_count' => $company->contacts_count,
            'deals_count' => $company->deals_count,
            'created_at' => $company->created_at?->toIso8601String(),
        ];
    }
}
