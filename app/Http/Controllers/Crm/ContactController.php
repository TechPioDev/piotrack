<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\SavedView;
use App\Services\Sales\LeadScoringService;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    /** Sortable columns (CRMT): request input maps here, never into orderBy raw. */
    private const SORTS = [
        'name' => 'first_name',
        'email' => 'email',
        'lead_score' => 'lead_score',
        'created_at' => 'created_at',
    ];

    public function index(Request $request, LeadScoringService $scoring): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'owner' => ['nullable', 'integer'],
            'lifecycle' => ['nullable', Rule::in(Contact::LIFECYCLE_STAGES)],
            'source' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', TenantExists::in('companies')],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = self::SORTS[$filters['sort'] ?? ''] ?? null;
        $dir = $filters['dir'] ?? 'asc';

        $contacts = Contact::with('company:id,name', 'owner:id,name')
            ->search($filters['search'] ?? null)
            ->when($filters['owner'] ?? null, fn ($q, $owner) => $q->where('owner_id', $owner))
            ->when($filters['lifecycle'] ?? null, fn ($q, $stage) => $q->where('lifecycle_stage', $stage))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('lead_source', $source))
            ->when($filters['company'] ?? null, fn ($q, $company) => $q->where('company_id', $company))
            ->when($sort !== null, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->latest('id'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->fullName(),
                'email' => $c->email,
                'title' => $c->title,
                'company' => $c->company?->name,
                'owner' => $c->owner?->name,
                'lifecycle_stage' => $c->lifecycle_stage,
                'lead_score' => (int) $c->lead_score,
                'temperature' => $scoring->temperature((int) $c->lead_score),
            ]);

        return Inertia::render('crm/contacts/index', [
            'contacts' => $contacts,
            'filters' => $filters,
            'owners' => $this->memberOptions(),
            'companies' => $this->companyOptions(),
            'lifecycleStages' => Contact::LIFECYCLE_STAGES,
            // Distinct sources actually present, so the filter never offers a dead option.
            'sources' => Contact::query()->whereNotNull('lead_source')->where('lead_source', '!=', '')
                ->distinct()->orderBy('lead_source')->pluck('lead_source')->all(),
            'views' => SavedView::where('resource', 'contacts')->where('user_id', $request->user()->id)
                ->orderBy('name')->get(['id', 'name', 'filters'])
                ->map(fn (SavedView $v) => ['id' => $v->id, 'name' => $v->name, 'filters' => $v->filters])
                ->all(),
        ]);
    }

    /**
     * One action applied to many contacts (CRMT). The route requires
     * crm.contact.update; destructive bulk delete re-checks its own permission.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['assign', 'stage', 'delete'])],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', TenantExists::in('contacts')],
            'owner_id' => ['required_if:action,assign', 'nullable', Rule::exists('organization_user', 'user_id')->where('organization_id', $this->currentOrganization->id())],
            'lifecycle_stage' => ['required_if:action,stage', 'nullable', Rule::in(Contact::LIFECYCLE_STAGES)],
        ]);

        $action = (string) $data['action'];

        if ($action === 'delete') {
            abort_unless($request->user()->can('crm.contact.delete'), 403);
        }

        $contacts = Contact::whereIn('id', $data['ids'])->get();

        foreach ($contacts as $contact) {
            if ($action === 'assign') {
                $contact->update(['owner_id' => $data['owner_id']]);
            } elseif ($action === 'stage') {
                $contact->update(['lifecycle_stage' => $data['lifecycle_stage']]);
            } else {
                $contact->delete();
            }
        }

        $this->audit->log('crm.contact.bulk_'.$action, context: ['count' => $contacts->count()], resourceType: 'contact');

        $status = match ($action) {
            'assign' => __(':n contacts reassigned.', ['n' => $contacts->count()]),
            'stage' => __(':n contacts moved to :stage.', ['n' => $contacts->count(), 'stage' => $data['lifecycle_stage']]),
            default => __(':n contacts deleted.', ['n' => $contacts->count()]),
        };

        return back()->with('status', $status);
    }

    /** Save the current filter set as a personal view (CRM-030). */
    public function storeView(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'filters' => ['required', 'array'],
            'filters.search' => ['nullable', 'string', 'max:100'],
            'filters.owner' => ['nullable', 'integer'],
            'filters.lifecycle' => ['nullable', Rule::in(Contact::LIFECYCLE_STAGES)],
            'filters.source' => ['nullable', 'string', 'max:120'],
            'filters.sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'filters.dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        SavedView::create([
            'user_id' => $request->user()->id,
            'resource' => 'contacts',
            'name' => $data['name'],
            'filters' => array_filter($data['filters'], fn ($v) => $v !== null && $v !== ''),
        ]);

        return back()->with('status', __('View saved.'));
    }

    public function destroyView(Request $request, SavedView $view): RedirectResponse
    {
        // Views are personal: another member's view is not yours to delete.
        abort_unless((int) $view->user_id === (int) $request->user()->id, 403);
        $view->delete();

        return back()->with('status', __('View removed.'));
    }

    public function show(Contact $contact): Response
    {
        $contact->load('company:id,name', 'owner:id,name');

        return Inertia::render('crm/contacts/show', [
            'contact' => [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'title' => $contact->title,
                'lead_source' => $contact->lead_source,
                'campaign' => $contact->campaign,
                'company' => $contact->company !== null ? ['id' => $contact->company->id, 'name' => $contact->company->name] : null,
                'owner' => $contact->owner?->name,
            ],
            'activities' => $this->timeline($contact),
            'deals' => $contact->deals()->get(['id', 'name', 'value', 'status'])->map(fn ($d) => [
                'id' => $d->id, 'name' => $d->name, 'value' => $d->value, 'status' => $d->status,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        // Duplicate detection (CRM-026): reject a contact whose email already
        // exists in this organization.
        if (! empty($data['email']) && Contact::where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => __('A contact with this email already exists.')]);
        }

        $data['owner_id'] ??= $request->user()->id;
        $contact = Contact::create($data);

        $this->audit->log('crm.contact.created', context: ['name' => $contact->fullName()], resourceType: 'contact', resourceId: (string) $contact->id, organizationId: $contact->organization_id);

        return back()->with('status', __('Contact created.'));
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $data = $this->validateData($request);

        if (! empty($data['email']) && Contact::where('email', $data['email'])->where('id', '!=', $contact->id)->exists()) {
            return back()->withErrors(['email' => __('Another contact with this email already exists.')]);
        }

        $contact->update($data);
        $this->audit->log('crm.contact.updated', resourceType: 'contact', resourceId: (string) $contact->id, organizationId: $contact->organization_id);

        return back()->with('status', __('Contact updated.'));
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->audit->log('crm.contact.deleted', context: ['name' => $contact->fullName()], resourceType: 'contact', resourceId: (string) $contact->id, organizationId: $contact->organization_id);
        $contact->delete();

        return redirect()->route('crm.contacts.index')->with('status', __('Contact deleted.'));
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
            'title' => ['nullable', 'string', 'max:120'],
            'buying_role' => ['nullable', Rule::in(Contact::BUYING_ROLES)],
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $this->currentOrganization->id())],
            'lead_source' => ['nullable', 'string', 'max:120'],
            'campaign' => ['nullable', 'string', 'max:120'],
            'owner_id' => ['nullable', Rule::exists('organization_user', 'user_id')->where('organization_id', $this->currentOrganization->id())],
        ]);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function memberOptions(): array
    {
        return $this->currentOrganization->get()->members()
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function companyOptions(): array
    {
        return Company::orderBy('name')->limit(200)->get(['id', 'name'])
            ->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Contact $contact): array
    {
        return $contact->activities()
            ->with('user:id,name')
            ->latest('id')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'title' => $a->title,
                'body' => $a->body,
                'due_at' => $a->due_at,
                'completed_at' => $a->completed_at,
                'user' => $a->user?->name,
                'created_at' => $a->created_at,
            ])->all();
    }
}
