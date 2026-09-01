<?php

namespace App\Services\Marketing;

use App\Models\Contact;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\MarketingList;
use App\Notifications\LeadCapturedNotification;
use App\Services\Integrations\WebhookDispatcher;
use App\Services\Sales\VisitorTracker;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use App\Support\NotificationDispatcher;
use Illuminate\Support\Str;

/**
 * Turns a public form submission into a tracked contact (LEAD-008/015/016/019).
 * Dedupes the contact by email within the tenant, records the submission, adds
 * the contact to the form's target list, sets its lifecycle stage, notifies
 * owners, and fires the form_submission automation trigger.
 *
 * Assumes the caller has established tenant context from the form's org (public
 * controllers do this after resolving the form by slug).
 */
class LeadCaptureService
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
        private ListService $lists,
        private NotificationDispatcher $notifications,
        private MarketingTrigger $trigger,
        private WebhookDispatcher $webhooks,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function capture(Form $form, array $payload, ?string $ip = null, ?string $userAgent = null, ?string $visitorKey = null): Contact
    {
        $email = isset($payload['email']) ? Str::lower(trim((string) $payload['email'])) : null;

        $contact = $email !== null ? Contact::where('email', $email)->first() : null;

        if ($contact === null) {
            $contact = Contact::create([
                'first_name' => (string) ($payload['first_name'] ?? $payload['name'] ?? 'Lead'),
                'last_name' => (string) ($payload['last_name'] ?? ''),
                'email' => $email,
                'phone' => isset($payload['phone']) ? (string) $payload['phone'] : null,
                'lead_source' => 'form',
                'lifecycle_stage' => $form->lifecycle_stage ?: 'lead',
            ]);
        }

        FormSubmission::create([
            'form_id' => $form->id,
            'contact_id' => $contact->id,
            'payload' => $payload,
            'ip' => $ip,
            'user_agent' => $userAgent !== null ? Str::limit($userAgent, 500, '') : null,
        ]);

        $form->increment('submission_count');

        if ($form->target_list_id !== null) {
            $list = MarketingList::find($form->target_list_id);

            if ($list !== null) {
                $this->lists->addContact($list, $contact);
            }
        }

        $this->audit->log(
            'lead.captured',
            context: ['form' => $form->name, 'email' => $email],
            resourceType: 'contact',
            resourceId: (string) $contact->id,
        );

        // INTG-009: the generic integration surface hears about every capture.
        $this->webhooks->dispatch('lead.captured', [
            'contact_id' => $contact->id,
            'name' => $contact->fullName(),
            'email' => $contact->email,
            'form' => $form->name,
            'lifecycle_stage' => $contact->lifecycle_stage,
        ]);

        $organization = $this->currentOrganization->get();
        if ($organization !== null) {
            $this->notifications->toOrganizationOwners(
                $organization,
                new LeadCapturedNotification($contact->fullName(), $form->name),
            );
        }

        $this->trigger->fire('form_submission', $contact, ['form_id' => $form->id]);

        // VINT: the pixel cookie rode the same-domain submit, so the browsing
        // trail that led here now belongs to a named contact.
        if ($visitorKey !== null) {
            app(VisitorTracker::class)->linkContact($visitorKey, $contact);
        }

        return $contact;
    }
}
