<?php

namespace App\Services\Sales;

use App\Messaging\MessagingProviderManager;
use App\Messaging\SmsMessage;
use App\Models\Activity;
use App\Models\AlertRule;
use App\Models\Contact;
use App\Models\SalesAlert;
use App\Notifications\SalesAlertNotification;
use App\Services\Integrations\WebhookDispatcher;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use App\Support\NotificationDispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sales alerts (ALERT + INTENT-014/015). Evaluates a contact against active
 * alert rules and, when triggered, raises an in-app alert (deduped per
 * contact+type), notifies the organization's owners (in-app + email per their
 * preferences), records a CRM timeline entry on the contact (ALERT-003), and
 * fans out to the org's configured extra channels: SMS through the messaging
 * provider (ALERT-002) and a Slack/Teams incoming webhook (ALERT-004). Extra
 * channels are best-effort — a failed delivery never fails the trigger.
 */
class AlertService
{
    public function __construct(
        private IntentService $intent,
        private NotificationDispatcher $notifications,
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
        private MessagingProviderManager $messaging,
        private WebhookDispatcher $webhooks,
    ) {}

    /**
     * Evaluate all threshold-based rules for a contact, returning the number of
     * alerts fired.
     */
    public function evaluate(Contact $contact): int
    {
        $fired = 0;

        foreach (AlertRule::where('is_active', true)->get() as $rule) {
            if ($this->triggered($rule, $contact) && $this->fire($rule->trigger, $contact)) {
                $fired++;
            }
        }

        return $fired;
    }

    /**
     * Raise an alert of the given type for a contact (deduped while unread).
     */
    public function fire(string $type, Contact $contact, ?string $message = null): bool
    {
        $exists = SalesAlert::where('contact_id', $contact->id)
            ->where('type', $type)->where('is_read', false)->exists();

        if ($exists) {
            return false;
        }

        $alert = SalesAlert::create([
            'contact_id' => $contact->id,
            'type' => $type,
            'message' => $message ?? $this->defaultMessage($type, $contact),
        ]);

        // ALERT-003: the alert is part of the contact's CRM timeline, so a rep
        // opening the record sees the signal without visiting the alerts page.
        Activity::create([
            'subject_type' => 'contact',
            'subject_id' => $contact->id,
            'type' => 'note',
            'title' => 'Sales alert: '.$alert->message,
            'occurred_at' => now(),
        ]);

        $organization = $this->currentOrganization->get();
        if ($organization !== null) {
            $this->notifications->toOrganizationOwners($organization, new SalesAlertNotification($alert->message));
            $this->deliverExtraChannels((array) ($organization->alert_channels ?? []), $alert->message);
        }

        // INTG-009: outbound webhook fan-out.
        $this->webhooks->dispatch('alert.fired', [
            'type' => $type,
            'message' => $alert->message,
            'contact_id' => $contact->id,
            'contact_name' => $contact->fullName(),
        ]);

        $this->audit->log('sales.alert.created', context: ['type' => $type], resourceType: 'contact', resourceId: (string) $contact->id, organizationId: $contact->organization_id);

        return true;
    }

    /**
     * SMS + webhook fan-out (ALERT-002/004). Best-effort by design: alerting
     * must never break the visitor/booking flow that triggered it.
     *
     * @param  array{sms_to?: ?string, webhook_url?: ?string}  $channels
     */
    private function deliverExtraChannels(array $channels, string $message): void
    {
        $smsTo = trim((string) ($channels['sms_to'] ?? ''));
        if ($smsTo !== '') {
            try {
                $this->messaging->sms()->send(new SmsMessage($smsTo, $message));
            } catch (\Throwable $e) {
                Log::warning('Sales alert SMS delivery failed', ['error' => $e->getMessage()]);
            }
        }

        $webhook = trim((string) ($channels['webhook_url'] ?? ''));
        if ($webhook !== '') {
            try {
                // {text: …} is the payload both Slack and Teams incoming
                // webhooks accept.
                Http::timeout(5)->post($webhook, ['text' => $message]);
            } catch (\Throwable $e) {
                Log::warning('Sales alert webhook delivery failed', ['error' => $e->getMessage()]);
            }
        }
    }

    private function triggered(AlertRule $rule, Contact $contact): bool
    {
        return match ($rule->trigger) {
            'score_threshold' => $contact->lead_score >= $rule->threshold,
            'high_intent' => $this->intent->intentScore($contact) >= $rule->threshold,
            default => false, // meeting_request/repeat_visit/bottom_funnel/content_engagement fire from their own events
        };
    }

    private function defaultMessage(string $type, Contact $contact): string
    {
        return match ($type) {
            'score_threshold' => "{$contact->fullName()} reached a hot lead score ({$contact->lead_score}).",
            'high_intent' => "{$contact->fullName()} is showing high buying intent.",
            'meeting_request' => "{$contact->fullName()} requested a meeting.",
            'repeat_visit' => "{$contact->fullName()} is back on your website.",
            'bottom_funnel' => "{$contact->fullName()} is reading a bottom-funnel page.",
            'content_engagement' => "{$contact->fullName()} is deep in your content.",
            default => "New sales signal for {$contact->fullName()}.",
        };
    }
}
