<?php

namespace App\Services\Advertising;

use App\Models\Contact;
use App\Models\RetargetingAudience;
use App\Services\Marketing\MessageDispatcher;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds retargeting audiences from first-party CRM data (RETG). An audience is
 * resolved from its source (marketing list / funnel stage / behavior rules /
 * all contacts), with converted customers excluded when requested; the sync
 * payload is a list of hashed emails ready for a platform custom-audience push.
 */
class RetargetingService
{
    public function __construct(private AuditLogger $audit) {}

    public function rebuild(RetargetingAudience $audience): int
    {
        $count = $this->members($audience)->count();
        $audience->update(['member_count' => $count]);

        $this->audit->log('ads.retargeting.rebuilt', context: ['audience' => $audience->name, 'members' => $count], resourceType: 'retargeting_audience', resourceId: (string) $audience->id, organizationId: $audience->organization_id);

        return $count;
    }

    /**
     * @return Collection<int, Contact>
     */
    public function members(RetargetingAudience $audience): Collection
    {
        $rules = $audience->rules ?? [];

        $query = match ($audience->source) {
            'list' => $audience->marketing_list_id !== null
                ? Contact::whereHas('lists', fn (Builder $q) => $q->where('marketing_lists.id', $audience->marketing_list_id))
                : Contact::whereRaw('1 = 0'),
            'funnel_stage' => Contact::where('lifecycle_stage', (string) ($rules['lifecycle_stage'] ?? 'lead')),
            'behavior' => Contact::query()
                ->when($rules['min_lead_score'] ?? null, fn (Builder $q, $n) => $q->where('lead_score', '>=', (int) $n))
                ->when($rules['lead_source'] ?? null, fn (Builder $q, $s) => $q->where('lead_source', $s)),
            default => Contact::query(), // all_contacts
        };

        // Conversion exclusion (RETG-016): drop existing customers.
        if ($audience->exclude_converted) {
            $query->where('lifecycle_stage', '!=', 'customer');
        }

        return $query->get();
    }

    /**
     * Hashed-email payload for a platform custom-audience upload. Actual push
     * happens through the platform connector (Planned).
     *
     * @return list<string>
     */
    public function syncPayload(RetargetingAudience $audience): array
    {
        return $this->members($audience)
            ->filter(fn (Contact $c) => ! empty($c->email))
            ->map(fn (Contact $c) => hash('sha256', mb_strtolower(trim((string) $c->email))))
            ->values()
            ->all();
    }

    /** CSV header each platform's customer-list upload template expects. */
    public const EXPORT_PLATFORMS = ['google' => 'Email', 'meta' => 'email', 'linkedin' => 'email'];

    /**
     * Platform-ready customer-match file (RETG-001..005): SHA-256-hashed
     * lowercase emails under the platform's expected header — the exact CSV
     * their Ads UI accepts for a manual customer-list upload. The automated
     * API push remains a connector enhancement (ADR-0006); the file makes the
     * retargeting workflow complete today.
     */
    public function exportCsv(RetargetingAudience $audience, string $platform): string
    {
        $header = self::EXPORT_PLATFORMS[$platform] ?? 'email';

        $this->audit->log('ads.retargeting.exported', context: ['audience' => $audience->name, 'platform' => $platform], resourceType: 'retargeting_audience', resourceId: (string) $audience->id, organizationId: $audience->organization_id);

        return $header."\n".implode("\n", $this->syncPayload($audience))."\n";
    }

    /**
     * SMS re-engagement (RETG-009): the Stage 6 SMS engine driven by a
     * retargeting audience. Consent and suppression are enforced per contact
     * by the dispatcher; members without a phone are skipped and counted, and
     * the result reports what actually happened — never just "sent".
     *
     * @return array{targeted: int, sent: int, suppressed: int, no_phone: int}
     */
    public function smsReengage(RetargetingAudience $audience, string $body, MessageDispatcher $dispatcher): array
    {
        $members = $this->members($audience);
        $counts = ['targeted' => $members->count(), 'sent' => 0, 'suppressed' => 0, 'no_phone' => 0];

        foreach ($members as $contact) {
            if (empty($contact->phone)) {
                $counts['no_phone']++;

                continue;
            }

            $message = $dispatcher->sendSms($contact, $body, 'retargeting');
            if ($message->status === 'sent') {
                $counts['sent']++;
            } else {
                $counts['suppressed']++;
            }
        }

        $this->audit->log('ads.retargeting.sms_sent', context: ['audience' => $audience->name] + $counts, resourceType: 'retargeting_audience', resourceId: (string) $audience->id, organizationId: $audience->organization_id);

        return $counts;
    }
}
