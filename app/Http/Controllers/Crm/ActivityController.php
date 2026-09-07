<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Contracts\HasActivities;
use App\Models\Deal;
use App\Models\Lead;
use App\Services\Sales\IntentService;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Polymorphic activity timeline (CRM-009…014). Activities attach to a contact,
 * company, lead or deal in the current organization.
 */
class ActivityController extends Controller
{
    /** @var array<string, class-string<Model>> */
    private const SUBJECTS = [
        'contact' => Contact::class,
        'company' => Company::class,
        'lead' => Lead::class,
        'deal' => Deal::class,
    ];

    public function __construct(private AuditLogger $audit) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject_type' => ['required', Rule::in(array_keys(self::SUBJECTS))],
            'subject_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(Activity::TYPES)],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $subject = $this->resolveSubject($validated['subject_type'], (int) $validated['subject_id']);

        $activity = $subject->activities()->create([
            'type' => $validated['type'],
            'user_id' => $request->user()->id,
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'occurred_at' => $validated['occurred_at'] ?? ($validated['type'] === 'task' ? null : now()),
        ]);

        $this->audit->log('crm.activity.created', context: ['type' => $activity->type], resourceType: 'activity', resourceId: (string) $activity->id, organizationId: $activity->organization_id);

        // INTENT-012: real sales touchpoints on a contact (a call, a meeting,
        // an email exchange) are buyer-intent evidence; notes and tasks are not.
        if ($subject instanceof Contact && in_array($activity->type, ['call', 'email', 'meeting'], true)) {
            app(IntentService::class)->record($subject, 'crm_activity', 5);
        }

        return back();
    }

    public function complete(Activity $activity): RedirectResponse
    {
        $activity->update(['completed_at' => $activity->completed_at === null ? now() : null]);

        return back();
    }

    /** PORTAL-014: flip whether this meeting note shows in the client portal. */
    public function visibility(Activity $activity): RedirectResponse
    {
        $activity->update(['client_visible' => ! $activity->client_visible]);

        return back()->with('status', $activity->client_visible
            ? __('Visible in the client portal.')
            : __('Hidden from the client portal.'));
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        $activity->delete();

        return back();
    }

    private function resolveSubject(string $type, int $id): HasActivities
    {
        /** @var class-string<Model> $class */
        $class = self::SUBJECTS[$type];

        // Tenant-scoped find (BelongsToTenant global scope) → 404 if not ours.
        $subject = $class::query()->find($id);
        abort_unless($subject instanceof HasActivities, 404);

        return $subject;
    }
}
