<?php

namespace App\Services\Marketing;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\IntentSignal;
use App\Models\OutboundMessage;
use App\Models\Suppression;

/**
 * Records email engagement from OUR public tracking endpoints (EMAIL-016…020).
 * These run unauthenticated, so records are resolved by their unique token
 * across tenants; the token is the capability. Opens/clicks update the tracking
 * row + campaign stats; unsubscribe adds a suppression that the dispatch
 * pipeline then honors for all future sends.
 */
class EmailTrackingService
{
    public function open(string $token): void
    {
        $recipient = CampaignRecipient::withoutGlobalScope('tenant')->where('token', $token)->first();

        if ($recipient !== null) {
            if ($recipient->opened_at === null) {
                $recipient->update(['opened_at' => now()]);
                Campaign::withoutGlobalScope('tenant')->whereKey($recipient->campaign_id)->increment('stat_opened');
            }

            return;
        }

        $message = OutboundMessage::withoutGlobalScope('tenant')->where('token', $token)->first();

        if ($message !== null && $message->opened_at === null) {
            $message->update(['opened_at' => now()]);
        }
    }

    public function click(string $token): void
    {
        $recipient = CampaignRecipient::withoutGlobalScope('tenant')->where('token', $token)->first();

        if ($recipient !== null) {
            $updates = ['clicked_at' => now()];
            if ($recipient->opened_at === null) {
                $updates['opened_at'] = now();
                Campaign::withoutGlobalScope('tenant')->whereKey($recipient->campaign_id)->increment('stat_opened');
            }
            if ($recipient->clicked_at === null) {
                Campaign::withoutGlobalScope('tenant')->whereKey($recipient->campaign_id)->increment('stat_clicked');

                // INTENT-010: a first campaign click is buyer-intent, recorded
                // on the contact (§20 weights email_click at 10; every
                // recipient row references a contact). Public route, no tenant
                // context — the org id comes from the recipient.
                IntentSignal::create([
                    'organization_id' => $recipient->organization_id,
                    'contact_id' => $recipient->contact_id,
                    'type' => 'campaign_click',
                    'weight' => 10,
                    'occurred_at' => now(),
                ]);
            }
            $recipient->update($updates);

            return;
        }

        $message = OutboundMessage::withoutGlobalScope('tenant')->where('token', $token)->first();
        if ($message !== null) {
            $message->update(['clicked_at' => now(), 'opened_at' => $message->opened_at ?? now()]);
        }
    }

    /**
     * Unsubscribe/opt-out the recipient behind this token. Returns true if a
     * matching record was found.
     */
    public function unsubscribe(string $token): bool
    {
        $recipient = CampaignRecipient::withoutGlobalScope('tenant')->where('token', $token)->first();

        if ($recipient !== null) {
            $campaign = Campaign::withoutGlobalScope('tenant')->findOrFail($recipient->campaign_id);
            $channel = $campaign->channel;

            if ($recipient->unsubscribed_at === null) {
                $recipient->update(['unsubscribed_at' => now()]);
                Campaign::withoutGlobalScope('tenant')->whereKey($recipient->campaign_id)->increment('stat_unsubscribed');
            }

            $this->addSuppression((int) $recipient->organization_id, $channel, $recipient->address, (int) $recipient->contact_id);

            return true;
        }

        $message = OutboundMessage::withoutGlobalScope('tenant')->where('token', $token)->first();

        if ($message !== null) {
            $this->addSuppression((int) $message->organization_id, $message->channel, (string) $message->address, (int) $message->contact_id);

            return true;
        }

        return false;
    }

    private function addSuppression(int $organizationId, string $channel, string $address, ?int $contactId): void
    {
        Suppression::firstOrCreate(
            ['organization_id' => $organizationId, 'channel' => $channel, 'address' => $address],
            ['reason' => 'unsubscribe', 'contact_id' => $contactId],
        );
    }
}
