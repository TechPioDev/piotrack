<?php

namespace App\Services\Sales;

use App\Models\Contact;
use App\Models\IntentSignal;
use App\Models\Visitor;
use Illuminate\Support\Carbon;

/**
 * Visitor Intelligence ingest (VINT). Rolls tracker events into the Visitor
 * row (sessions on a 30-minute window, first-touch attribution kept forever),
 * scores paths for intent, and resolves identity when the visitor tells us who
 * they are. Anonymous behavior accrues on the visitor row only; the moment a
 * contact is linked, the same behavior also feeds the existing intent-signal
 * engine so scoring, alerts and next actions light up.
 *
 * Runs inside tenant context (the public controller sets it from the tracking
 * key, the established public-endpoint pattern).
 */
class VisitorTracker
{
    /** A gap longer than this starts a new session (INTENT-003). */
    public const SESSION_MINUTES = 30;

    /** Path fragments -> intent signal type and weight (INTENT-004/005/006/009). */
    public const PATH_SIGNALS = [
        ['fragments' => ['pricing', 'contact', 'book', 'quote', 'consultation'], 'type' => 'high_intent_page', 'weight' => 8],
        ['fragments' => ['service', 'cybersecurity', 'managed-it', 'msp', 'microsoft-365', 'cmmc'], 'type' => 'service_interest', 'weight' => 3],
        ['fragments' => ['blog', 'guide', 'resource', 'case-stud', 'ebook', 'webinar'], 'type' => 'content_view', 'weight' => 2],
    ];

    /** Content pageviews by a known contact before the engagement alert fires (ALERT-007). */
    public const CONTENT_ALERT_THRESHOLD = 3;

    public function __construct(
        private IntentService $intent,
        private AlertService $alerts,
    ) {}

    /**
     * @param  array{vid: string, type: string, path?: ?string, title?: ?string, referrer?: ?string, email?: ?string, utm_source?: ?string, utm_medium?: ?string, utm_campaign?: ?string}  $event
     */
    public function ingest(array $event): Visitor
    {
        $now = Carbon::now();

        $visitor = Visitor::firstOrCreate(
            ['visitor_key' => $event['vid']],
            ['first_seen_at' => $now, 'last_seen_at' => null, 'visits' => 0],
        );

        // A fresh session after 30 quiet minutes; repeat visits are the signal.
        $newSession = $visitor->last_seen_at === null
            || $visitor->last_seen_at->lt($now->copy()->subMinutes(self::SESSION_MINUTES));

        $updates = [
            'last_seen_at' => $now,
            'visits' => $visitor->visits + ($newSession ? 1 : 0),
        ];

        // First-touch attribution is set once and never overwritten.
        foreach (['referrer', 'utm_source', 'utm_medium', 'utm_campaign'] as $field) {
            if ($visitor->{$field} === null && ! empty($event[$field])) {
                $updates[$field] = mb_substr((string) $event[$field], 0, 120);
            }
        }

        if ($event['type'] === 'pageview') {
            $updates['page_views'] = $visitor->page_views + 1;
            if (! empty($event['path'])) {
                $updates['last_path'] = mb_substr((string) $event['path'], 0, 300);
            }
        }

        if ($event['type'] === 'identify' && ! empty($event['email'])) {
            $email = mb_strtolower(trim((string) $event['email']));
            $updates['email'] = $email;
            if ($visitor->contact_id === null) {
                $contact = Contact::where('email', $email)->first();
                if ($contact !== null) {
                    $updates['contact_id'] = $contact->id;
                    $this->intent->record($contact, 'identified_on_site', 5, $visitor->last_path);
                }
            }
        }

        $visitor->update($updates);
        $visitor->refresh();

        // ALERT-006: a known contact coming back for another session is the
        // classic "call them now" signal. Deduped per contact while unread.
        if ($newSession && $visitor->visits > 1 && $visitor->contact !== null) {
            $this->alerts->fire('repeat_visit', $visitor->contact);
        }

        // INTENT-011: a known contact arriving through a paid first touch is
        // ad engagement — once per session, not per pageview.
        if ($newSession && $visitor->contact !== null && $visitor->channel() === 'paid') {
            $this->intent->record($visitor->contact, 'ad_engagement', 8, $visitor->last_path);
        }

        $visitor->events()->create([
            'type' => $event['type'],
            'path' => isset($event['path']) ? mb_substr((string) $event['path'], 0, 300) : null,
            'title' => isset($event['title']) ? mb_substr((string) $event['title'], 0, 200) : null,
        ]);

        if ($event['type'] === 'pageview') {
            $this->scorePath($visitor, (string) ($event['path'] ?? ''));
        }

        return $visitor;
    }

    /** Link a visitor to the contact a form submission just created or found. */
    public function linkContact(string $visitorKey, Contact $contact): void
    {
        $visitor = Visitor::where('visitor_key', $visitorKey)->first();

        if ($visitor === null || $visitor->contact_id !== null) {
            return;
        }

        $visitor->update(['contact_id' => $contact->id, 'email' => $contact->email]);
        $this->intent->record($contact, 'identified_on_site', 5, $visitor->last_path);
    }

    /**
     * Event-fired sales alerts from path signals (ALERT-007/008): a known
     * contact on a bottom-funnel page alerts at once; sustained content
     * reading alerts at the third content view.
     */
    private function alertOnSignal(Contact $contact, string $signalType): void
    {
        if ($signalType === 'high_intent_page') {
            $this->alerts->fire('bottom_funnel', $contact);

            return;
        }

        if ($signalType === 'content_view') {
            $reads = IntentSignal::where('contact_id', $contact->id)->where('type', 'content_view')->count();
            if ($reads >= self::CONTENT_ALERT_THRESHOLD) {
                $this->alerts->fire('content_engagement', $contact);
            }
        }
    }

    private function scorePath(Visitor $visitor, string $path): void
    {
        $path = mb_strtolower($path);

        foreach (self::PATH_SIGNALS as $rule) {
            foreach ($rule['fragments'] as $fragment) {
                if (str_contains($path, $fragment)) {
                    // Heat accrues on the visitor whether or not we know them…
                    $visitor->increment('intent_score', $rule['weight']);

                    // …and feeds the intent engine once we do (INTENT-007/014/016).
                    if ($visitor->contact_id !== null && $visitor->contact !== null) {
                        $this->intent->record($visitor->contact, $rule['type'], $rule['weight'], $path);
                        $this->alertOnSignal($visitor->contact, $rule['type']);
                    }

                    return; // one signal per pageview, strongest rule first
                }
            }
        }
    }
}
