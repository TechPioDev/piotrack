<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        // Sortable columns (CRMT): allow-listed, never raw input into orderBy.
        $sorts = ['name' => 'name', 'contacts_count' => 'contacts_count', 'deals_count' => 'deals_count', 'created_at' => 'created_at'];

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(array_keys($sorts))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $sorts[$filters['sort'] ?? ''] ?? null;

        $companies = Company::withCount('contacts', 'deals')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->whereLike('name', "%{$s}%")->orWhereLike('domain', "%{$s}%"))
            ->when($sort !== null, fn ($q) => $q->orderBy($sort, $filters['dir'] ?? 'asc'), fn ($q) => $q->latest('id'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Company $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'domain' => $c->domain,
                'industry' => $c->industry,
                'contacts_count' => $c->contacts_count,
                'deals_count' => $c->deals_count,
            ]);

        return Inertia::render('crm/companies/index', [
            'companies' => $companies,
            'filters' => $filters,
            'owners' => $this->memberOptions(),
        ]);
    }

    public function show(Company $company): Response
    {
        return Inertia::render('crm/companies/show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'domain' => $company->domain,
                'industry' => $company->industry,
                'size' => $company->size,
                'phone' => $company->phone,
                'website' => $company->website,
            ],
            'contacts' => $company->contacts()->get(['id', 'first_name', 'last_name', 'email', 'title'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->fullName(), 'email' => $c->email, 'title' => $c->title]),
            'deals' => $company->deals()->get(['id', 'name', 'value', 'status'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'value' => $d->value, 'status' => $d->status]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['owner_id'] ??= $request->user()->id;
        $company = Company::create($data);

        $this->audit->log('crm.company.created', context: ['name' => $company->name], resourceType: 'company', resourceId: (string) $company->id, organizationId: $company->organization_id);

        return back()->with('status', __('Company created.'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $company->update($this->validateData($request));
        $this->audit->log('crm.company.updated', resourceType: 'company', resourceId: (string) $company->id, organizationId: $company->organization_id);

        return back()->with('status', __('Company updated.'));
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->audit->log('crm.company.deleted', context: ['name' => $company->name], resourceType: 'company', resourceId: (string) $company->id, organizationId: $company->organization_id);
        $company->delete();

        return redirect()->route('crm.companies.index')->with('status', __('Company deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'domain' => ['nullable', 'string', 'max:200'],
            'industry' => ['nullable', 'string', 'max:120'],
            'size' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', Rule::exists('organization_user', 'user_id')->where('organization_id', $this->currentOrganization->id())],
        ]);
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
