<?php

namespace App\Services\Sales;

use App\Models\Contact;
use App\Models\Organization;
use App\Models\ScoringRule;
use App\Models\User;
use App\Notifications\SqlPromotedNotification;
use App\Support\AuditLogger;
use App\Support\NotificationDispatcher;

/**
 * Rules-based lead scoring (LSCR). Sums the points of every matched active rule
 * (demographic/firmographic/behavioral/intent) into the contact's lead_score,
 * derives a temperature (hot/warm/cold), and promotes the lifecycle to `sql`
 * once the SQL threshold is reached.
 */
class LeadScoringService
{
    public const HOT = 60;

    public const WARM = 30;

    public const SQL_THRESHOLD = 50;

    /** Scores from here up promote a plain lead to MQL (LEAD-003). */
    public const MQL_THRESHOLD = 20;

    public function __construct(
        private IntentService $intent,
        private AuditLogger $audit,
        private NotificationDispatcher $notifier,
    ) {}

    public function scoreContact(Contact $contact): int
    {
        $total = 0;

        foreach (ScoringRule::where('is_active', true)->get() as $rule) {
            if ($this->matches($rule, $contact)) {
                $total += $rule->points;
            }
        }

        return max(0, $total);
    }

    public function apply(Contact $contact): Contact
    {
        $score = $this->scoreContact($contact);
        $updates = ['lead_score' => $score];

        $promoted = false;
        if ($score >= self::SQL_THRESHOLD && ! in_array($contact->lifecycle_stage, ['sql', 'opportunity', 'customer'], true)) {
            $updates['lifecycle_stage'] = 'sql';
            $promoted = true;
        } elseif ($score >= self::MQL_THRESHOLD && $contact->lifecycle_stage === 'lead') {
            // MQL generation (LEAD-003): scoring promotes forward only — never
            // demotes, never touches stages beyond plain leads.
            $updates['lifecycle_stage'] = 'mql';
        }

        $contact->update($updates);

        // ALRT / NOTIF-006: the promotion guard above fires at most once per
        // contact, so this cannot repeat. Owner first; owners of the org when
        // nobody owns the contact yet.
        if ($promoted) {
            $notification = new SqlPromotedNotification($contact->id, $contact->fullName(), $score);
            $owner = $contact->owner_id !== null ? User::find($contact->owner_id) : null;

            if ($owner !== null) {
                $this->notifier->toUser($owner, $notification);
            } else {
                $organization = Organization::find($contact->organization_id);
                if ($organization !== null) {
                    $this->notifier->toOrganizationOwners($organization, $notification);
                }
            }
        }

        return $contact;
    }

    public function temperature(int $score): string
    {
        return $score >= self::HOT ? 'hot' : ($score >= self::WARM ? 'warm' : 'cold');
    }

    public function recomputeAll(): int
    {
        $contacts = Contact::all();

        foreach ($contacts as $contact) {
            $this->apply($contact);
        }

        $this->audit->log('sales.scoring.recomputed', context: ['contacts' => $contacts->count()]);

        return $contacts->count();
    }

    private function matches(ScoringRule $rule, Contact $contact): bool
    {
        $actual = $this->attributeValue($rule->attribute, $contact);

        return match ($rule->operator) {
            'equals' => (string) $actual === (string) $rule->value,
            'contains' => $rule->value !== null && str_contains(mb_strtolower((string) $actual), mb_strtolower($rule->value)),
            'gte' => (float) $actual >= (float) $rule->value,
            'is_true' => (bool) $actual,
            default => false,
        };
    }

    private function attributeValue(string $attribute, Contact $contact): mixed
    {
        return match ($attribute) {
            'lifecycle_stage' => $contact->lifecycle_stage,
            'lead_source' => $contact->lead_source,
            'title' => $contact->title,
            'buying_role' => $contact->buying_role,
            'email_opt_in' => $contact->email_opt_in,
            'has_company' => $contact->company_id !== null,
            'intent_score' => $this->intent->intentScore($contact),
            // Firmographics from the contact's own CRM company record
            // (LSCR-011/012) — first-party data, no enrichment provider needed
            // once the company profile is filled in.
            'company_size' => $contact->company?->size,
            'company_city' => $contact->company?->city,
            'company_region' => $contact->company?->region,
            'company_industry' => $contact->company?->industry,
            default => null,
        };
    }
}
