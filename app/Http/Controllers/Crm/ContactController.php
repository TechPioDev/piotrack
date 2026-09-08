<?php

namespace App\Http\Controllers\Crm;

use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Crm\Contracts\EnrichmentProvider;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\SavedView;
use App\Services\Marketing\MessageDispatcher;
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

    /**
     * VID-016: send a personalized sales video — recorded on any host, the
     * link rides a real dispatcher email (merge-tag personalized,
     * suppression-honoring, click-tracked CTA) and lands on the timeline.
     */
    public function videoMessage(Request $request, Contact $contact, MessageDispatcher $dispatcher): RedirectResponse
    {
        $data = $request->validate([
            'video_url' => ['required', 'url', 'starts_with:https://', 'max:500'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if ($contact->email === null || $contact->email === '') {
            return back()->withErrors(['video_url' => __('This contact has no email address.')]);
        }

        $body = '<p>'.nl2br(e($data['message'])).'</p>'
            .'<p><a href="'.e($data['video_url']).'" style="display:inline-block;padding:12px 20px;background:#111;color:#fff;border-radius:6px;text-decoration:none">▶ '.__('Watch your video').'</a></p>';

        $message = $dispatcher->sendEmail($contact, $data['subject'], $body, source: 'sales_video');

        Activity::create([
            'subject_type' => 'contact', 'subject_id' => $contact->id, 'type' => 'email',
            'user_id' => $request->user()->id,
            'title' => __('Sales video sent'),
            'body' => $data['video_url'],
            'occurred_at' => now(),
        ]);

        return back()->with('status', $message->status === 'sent'
            ? __('Video message sent — the click lands in engagement tracking.')
            : __('Video message could not be sent (:reason).', ['reason' => (string) $message->error]));
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

    /**
     * CRM-027: enrich a contact through the provider seam. Enrichment fills
     * ONLY empty fields — operator-entered data is never overwritten — and
     * the audit trail records which driver supplied the values.
     */
    public function enrich(Contact $contact, EnrichmentProvider $enrichment, AuditLogger $audit): RedirectResponse
    {
        if ($contact->email === null || $contact->email === '') {
            return back()->withErrors(['email' => __('Enrichment needs an email address.')]);
        }

        $data = $enrichment->enrich((string) $contact->email);
        $filled = [];

        if ($contact->company_id === null && $data['company_name'] !== null) {
            $company = Company::firstOrCreate(
                ['name' => $data['company_name']],
                array_filter(['industry' => $data['industry'], 'size' => $data['employee_range'], 'region' => $data['region']], fn ($v) => $v !== null),
            );
            $contact->update(['company_id' => $company->id]);
            $filled[] = 'company';
        } elseif ($contact->company_id !== null) {
            // Fill the linked company's OWN empty fields, never overwrite.
            $company = Company::find($contact->company_id);
            if ($company !== null) {
                $updates = array_filter([
                    'industry' => $company->industry === null ? $data['industry'] : null,
                    'size' => $company->size === null ? $data['employee_range'] : null,
                    'region' => $company->region === null ? $data['region'] : null,
                ], fn ($v) => $v !== null);
                if ($updates !== []) {
                    $company->update($updates);
                    $filled = array_merge($filled, array_keys($updates));
                }
            }
        }

        $audit->log('crm.contact.enriched', context: ['provider' => $enrichment->name(), 'filled' => $filled], resourceType: 'contact', resourceId: (string) $contact->id, organizationId: $contact->organization_id);

        if ($filled === []) {
            return back()->with('status', __('Nothing to enrich - every field the :provider driver knows is already filled.', ['provider' => $enrichment->name()]));
        }

        return back()->with('status', __('Enriched via the :provider driver: :fields.', ['provider' => $enrichment->name(), 'fields' => implode(', ', $filled)]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        // Duplicate detection (CRM-026): reject a contact whose email already
        // exists in this organization.
        if (! empty($data['email']) && Contact::where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => __('A contact with this email already exists.')]);
        }

        // ENTL-004: plan contact limit at the human-driven creation point.
        // Public capture endpoints are deliberately never blocked - a lead is
        // never dropped over a plan limit.
        app(UsageMeter::class)->assertWithin(
            app(CurrentOrganization::class)->get(), Limit::Contacts, errorKey: 'email',
        );

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
                'client_visible' => $a->client_visible,
                'user' => $a->user?->name,
                'created_at' => $a->created_at,
            ])->all();
    }
}
