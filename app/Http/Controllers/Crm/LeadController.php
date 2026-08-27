<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\LeadConversionService;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
        private LeadConversionService $conversion,
    ) {}

    public function index(Request $request): Response
    {
        // Sortable columns (CRMT): allow-listed, never raw input into orderBy.
        $sorts = ['name' => 'first_name', 'company_name' => 'company_name', 'created_at' => 'created_at'];

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Lead::STATUSES)],
            'sort' => ['nullable', Rule::in(array_keys($sorts))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $sorts[$filters['sort'] ?? ''] ?? null;

        $leads = Lead::with('owner:id,name')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->whereLike('first_name', "%{$s}%")->orWhereLike('last_name', "%{$s}%")
                ->orWhereLike('email', "%{$s}%")->orWhereLike('company_name', "%{$s}%")))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($sort !== null, fn ($q) => $q->orderBy($sort, $filters['dir'] ?? 'asc'), fn ($q) => $q->latest('id'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Lead $l) => [
                'id' => $l->id,
                'name' => $l->fullName(),
                'email' => $l->email,
                'company_name' => $l->company_name,
                'source' => $l->source,
                'status' => $l->status,
                'owner' => $l->owner?->name,
            ]);

        return Inertia::render('crm/leads/index', [
            'leads' => $leads,
            'filters' => $filters,
            'statuses' => Lead::STATUSES,
            'owners' => $this->memberOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['owner_id'] ??= $request->user()->id;
        $lead = Lead::create($data);

        $this->audit->log('crm.lead.created', context: ['name' => $lead->fullName()], resourceType: 'lead', resourceId: (string) $lead->id, organizationId: $lead->organization_id);

        return back()->with('status', __('Lead created.'));
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $lead->update($this->validateData($request));
        $this->audit->log('crm.lead.updated', resourceType: 'lead', resourceId: (string) $lead->id, organizationId: $lead->organization_id);

        return back()->with('status', __('Lead updated.'));
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $this->audit->log('crm.lead.deleted', resourceType: 'lead', resourceId: (string) $lead->id, organizationId: $lead->organization_id);
        $lead->delete();

        return redirect()->route('crm.leads.index')->with('status', __('Lead deleted.'));
    }

    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        if ($lead->isConverted()) {
            return back()->withErrors(['lead' => __('This lead has already been converted.')]);
        }

        $validated = $request->validate([
            'create_deal' => ['nullable', 'boolean'],
            'deal_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = $this->conversion->convert(
            $lead,
            createDeal: (bool) ($validated['create_deal'] ?? false),
            dealValue: isset($validated['deal_value']) ? (int) round($validated['deal_value'] * 100) : null,
        );

        return redirect()->route('crm.contacts.show', $result['contact']->id)
            ->with('status', __('Lead converted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'source' => ['nullable', 'string', 'max:120'],
            'campaign' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(Lead::STATUSES)],
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
