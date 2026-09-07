<?php

namespace App\Http\Controllers\Api\V1;

use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Models\Contact;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactController extends ApiController
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            // API-001: filtering + sorting parity with the web app.
            'lifecycle_stage' => ['nullable', Rule::in(Contact::LIFECYCLE_STAGES)],
            'lead_source' => ['nullable', 'string', 'max:120'],
            'company_id' => ['nullable', 'integer'],
            'owner_id' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in($this->sortKeys(['id', 'created_at', 'lead_score', 'last_name']))],
        ]);

        $contacts = Contact::with('company:id,name', 'owner:id,name')
            ->search($filters['search'] ?? null)
            ->when($filters['lifecycle_stage'] ?? null, fn ($q, $stage) => $q->where('lifecycle_stage', $stage))
            ->when($filters['lead_source'] ?? null, fn ($q, $source) => $q->where('lead_source', $source))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', (int) $id))
            ->when($filters['owner_id'] ?? null, fn ($q, $id) => $q->where('owner_id', (int) $id))
            ->tap(fn ($q) => $this->applySort($q, $filters['sort'] ?? null))
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString()
            ->through(fn (Contact $c) => $this->transform($c));

        return $this->collection($contacts);
    }

    public function show(Contact $contact): JsonResponse
    {
        $contact->load('company:id,name', 'owner:id,name');

        return $this->item($this->transform($contact));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:120'],
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $this->currentOrganization->id())],
            'lead_source' => ['nullable', 'string', 'max:120'],
            'owner_id' => ['nullable', Rule::exists('organization_user', 'user_id')->where('organization_id', $this->currentOrganization->id())],
        ]);

        // Duplicate detection (CRM-026), mirrored from the web controller.
        if (! empty($data['email']) && Contact::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => __('A contact with this email already exists.')]);
        }

        $data['owner_id'] ??= $request->user()->getAuthIdentifier();
        app(UsageMeter::class)->assertWithin(
            $this->currentOrganization->get(), Limit::Contacts, errorKey: 'email',
        );

        $contact = Contact::create($data);

        $this->audit->log('crm.contact.created', context: ['name' => $contact->fullName(), 'via' => 'api'], resourceType: 'contact', resourceId: (string) $contact->id, organizationId: $contact->organization_id);

        $contact->load('company:id,name', 'owner:id,name');

        return $this->item($this->transform($contact), 201);
    }

    /**
     * API-005: update a contact. Duplicate email refused the same way store is.
     */
    public function update(Request $request, Contact $contact): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:120'],
            'lifecycle_stage' => ['sometimes', Rule::in(Contact::LIFECYCLE_STAGES)],
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $this->currentOrganization->id())],
            'lead_source' => ['nullable', 'string', 'max:120'],
            'owner_id' => ['nullable', Rule::exists('organization_user', 'user_id')->where('organization_id', $this->currentOrganization->id())],
        ]);

        if (! empty($data['email']) && Contact::where('email', $data['email'])->whereKeyNot($contact->getKey())->exists()) {
            throw ValidationException::withMessages(['email' => __('A contact with this email already exists.')]);
        }

        $contact->update($data);
        $this->audit->log('crm.contact.updated', context: ['via' => 'api'], resourceType: 'contact', resourceId: (string) $contact->id, organizationId: $contact->organization_id);

        $contact->load('company:id,name', 'owner:id,name');

        return $this->item($this->transform($contact));
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'name' => $contact->fullName(),
            'email' => $contact->email,
            'phone' => $contact->phone,
            'title' => $contact->title,
            'lifecycle_stage' => $contact->lifecycle_stage,
            'lead_score' => $contact->lead_score,
            'lead_source' => $contact->lead_source,
            'company' => $contact->company !== null
                ? ['id' => $contact->company->id, 'name' => $contact->company->name]
                : null,
            'owner' => $contact->owner !== null
                ? ['id' => $contact->owner->id, 'name' => $contact->owner->name]
                : null,
            'created_at' => $contact->created_at?->toIso8601String(),
        ];
    }
}
