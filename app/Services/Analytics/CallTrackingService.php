<?php

namespace App\Services\Analytics;

use App\Analytics\CallProviderManager;
use App\Models\Call;
use App\Models\CallTrackingNumber;
use App\Models\Contact;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;

/**
 * Call tracking (CALL). Provisions dynamic numbers via the configured
 * {@see CallProviderManager}, logs calls with marketing-source/campaign/owner
 * attribution, scores lead quality from call duration, and flags conversions.
 * Recordings/transcription/AI summaries are provider-only (Planned).
 */
class CallTrackingService
{
    public const QUALIFIED_SECONDS = 120;

    public function __construct(
        private CallProviderManager $providers,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function provisionNumber(array $data): CallTrackingNumber
    {
        $source = (string) ($data['source'] ?? 'website');
        $campaign = isset($data['campaign']) ? (string) $data['campaign'] : null;
        $provisioned = $this->providers->driver()->provisionNumber($source, $campaign);

        return CallTrackingNumber::create([
            'phone_number' => $provisioned['phone_number'],
            'provider' => (string) config('analytics.calls_driver', 'fixture'),
            'provider_id' => $provisioned['provider_id'],
            'label' => $data['label'] ?? null,
            'source' => $source,
            'campaign' => $campaign,
            'is_active' => true,
        ]);
    }

    /**
     * Log an inbound/outbound call, inheriting source/campaign attribution from
     * the tracking number it came in on, then score it.
     *
     * @param  array<string, mixed>  $data
     */
    public function logCall(array $data): Call
    {
        $number = isset($data['call_tracking_number_id'])
            ? CallTrackingNumber::find($data['call_tracking_number_id'])
            : null;

        $duration = (int) ($data['duration_seconds'] ?? 0);

        $call = Call::create([
            'call_tracking_number_id' => $number?->id,
            'contact_id' => $data['contact_id'] ?? null,
            'owner_id' => $data['owner_id'] ?? null,
            'from_number' => $data['from_number'] ?? null,
            'to_number' => $data['to_number'] ?? $number?->phone_number,
            'direction' => $data['direction'] ?? 'inbound',
            'duration_seconds' => $duration,
            'status' => $data['status'] ?? 'completed',
            'source' => $data['source'] ?? $number?->source,
            'campaign' => $data['campaign'] ?? $number?->campaign,
            'converted' => (bool) ($data['converted'] ?? false),
            'occurred_at' => isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now(),
        ]);

        $call->update([
            'score' => $this->score($call),
            'is_qualified' => $duration >= self::QUALIFIED_SECONDS,
        ]);

        $this->audit->log('analytics.call.logged', context: ['source' => $call->source, 'score' => $call->score], resourceType: 'call', resourceId: (string) $call->id);

        return $call->refresh();
    }

    /**
     * Lead-quality score 0-100 from call duration (a 10-minute call maxes out),
     * with a conversion bonus.
     */
    public function score(Call $call): int
    {
        if ($call->status !== 'completed') {
            return 0;
        }

        $base = min(80, (int) floor($call->duration_seconds / 7.5)); // 600s -> 80
        $bonus = $call->converted ? 20 : 0;

        return min(100, $base + $bonus);
    }

    public function markConverted(Call $call): Call
    {
        $call->update(['converted' => true, 'score' => min(100, $call->score + 20)]);

        // LEAD-009: a converted call IS a lead. Attach the existing contact by
        // phone when we have one; otherwise the call becomes a new phone-call
        // lead so it enters routing, scoring and attribution like every other
        // capture. Never invents an email.
        if ($call->contact_id === null) {
            $contact = Contact::where('phone', $call->from_number)->first()
                ?? Contact::create([
                    'first_name' => 'Caller',
                    'last_name' => $call->from_number,
                    'phone' => $call->from_number,
                    'lead_source' => $call->source !== null && $call->source !== '' ? $call->source : 'phone_call',
                    'lifecycle_stage' => 'lead',
                ]);

            $call->update(['contact_id' => $contact->id]);
        }

        return $call->refresh();
    }

    /**
     * @return array<string, int>
     */
    public function sourceBreakdown(): array
    {
        return Call::query()
            ->selectRaw("COALESCE(NULLIF(source, ''), 'unknown') AS channel, COUNT(*) AS total")
            ->groupBy('channel')
            ->pluck('total', 'channel')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
