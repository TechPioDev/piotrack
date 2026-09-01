<?php

namespace App\Services\Sales;

use App\Models\Contact;
use App\Models\IntentSignal;

/**
 * Buyer-intent intelligence (INTENT). Records first-party intent signals and
 * derives an intent score, a high-intent flag, and a recommended next action
 * from recent activity. Third-party intent data + anonymous-visitor ID arrive
 * via INTG connectors (Planned).
 */
class IntentService
{
    /** Intent score at/above which a contact is "in market". */
    public const HIGH_INTENT = 20;

    public function record(Contact $contact, string $type, int $weight = 1, ?string $url = null): IntentSignal
    {
        return IntentSignal::create([
            'contact_id' => $contact->id,
            'type' => $type,
            'weight' => $weight,
            'url' => $url,
            'occurred_at' => now(),
        ]);
    }

    public function intentScore(Contact $contact, int $days = 30): int
    {
        return (int) IntentSignal::where('contact_id', $contact->id)
            ->where('occurred_at', '>=', now()->subDays($days))
            ->sum('weight');
    }

    public function isHighIntent(Contact $contact): bool
    {
        return $this->intentScore($contact) >= self::HIGH_INTENT;
    }

    /** A buying window needs sustained recent activity, not one hot page. */
    public const BUYING_WINDOW_DAYS = 14;

    public const BUYING_WINDOW_SIGNALS = 3;

    public const BUYING_WINDOW_SCORE = 20;

    /**
     * Buying-window detection (INTENT-013): three or more distinct signals
     * worth 20+ points inside a fortnight — the pattern of someone actively
     * evaluating, distinct from a single high-intent page hit.
     */
    public function inBuyingWindow(Contact $contact): bool
    {
        $recent = IntentSignal::where('contact_id', $contact->id)
            ->where('occurred_at', '>=', now()->subDays(self::BUYING_WINDOW_DAYS))->get();

        return $recent->count() >= self::BUYING_WINDOW_SIGNALS
            && (int) $recent->sum('weight') >= self::BUYING_WINDOW_SCORE;
    }

    public function nextAction(Contact $contact): string
    {
        $latest = IntentSignal::where('contact_id', $contact->id)->latest('occurred_at')->first();

        if ($latest === null) {
            return 'Nurture with content';
        }

        return match ($latest->type) {
            'pricing_view', 'demo_request' => 'Reach out today — high buying intent',
            'service_view' => 'Send a relevant case study',
            'download' => 'Follow up on the downloaded asset',
            default => $this->isHighIntent($contact) ? 'Prioritize outreach' : 'Continue nurturing',
        };
    }
}
