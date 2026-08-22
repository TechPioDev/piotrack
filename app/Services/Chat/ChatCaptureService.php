<?php

namespace App\Services\Chat;

use App\Models\BookingPage;
use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatWidget;
use App\Models\Contact;
use App\Models\Lead;
use App\Services\Sales\AlertService;
use App\Services\Sales\IntentService;
use App\Services\Sales\LeadScoringService;

/**
 * Runs when a conversation reaches an end node: turns the collected answers into
 * CRM records with duplicate detection, applies scoring + alerts, assigns an
 * owner, and preserves attribution. Assumes the caller has established tenant
 * context from the widget's organization (public controller does).
 */
class ChatCaptureService
{
    public function __construct(
        private readonly LeadScoringService $scoring,
        private readonly AlertService $alerts,
        private readonly IntentService $intent,
    ) {}

    /**
     * What is already known about a returning visitor (§17).
     *
     * Matched on the anonymous visitor id the widget stores locally, then read
     * off the contact it produced last time. Nothing is invented: only fields the
     * visitor themselves gave us before are returned.
     *
     * @return array<string, string>
     */
    public function knownAnswersFor(?string $visitorId): array
    {
        if ($visitorId === null || $visitorId === '' || $visitorId === 'preview') {
            return [];
        }

        $previous = ChatConversation::query()
            ->where('visitor_id', $visitorId)
            ->whereNotNull('contact_id')
            ->latest('id')
            ->with('contact')
            ->first();

        $contact = $previous?->contact;
        if ($contact === null) {
            return [];
        }

        return array_filter([
            'first_name' => (string) $contact->first_name,
            'last_name' => (string) $contact->last_name,
            'email' => (string) $contact->email,
            'phone' => (string) $contact->phone,
            'company_name' => (string) ($contact->company()->value('name') ?? ''),
        ], fn (string $v) => trim($v) !== '');
    }

    /**
     * @return array<string, mixed> extra payload for the widget (e.g. booking_url)
     */
    public function complete(ChatWidget $widget, ChatConversation $conversation, string $outcome): array
    {
        // Builder previews run through this same engine so a tenant tests the real
        // conversation — but they must never reach the CRM, alerts or analytics.
        if ($conversation->is_preview) {
            $conversation->forceFill(['status' => 'closed'])->save();

            return $outcome === 'meeting' ? ['booking_url' => null, 'preview_outcome' => 'meeting'] : ['preview_outcome' => $outcome];
        }

        // Existing-customer/support outcomes never become sales leads (§13).
        if ($outcome === 'support') {
            $conversation->forceFill(['status' => 'closed'])->save();
            ChatEvent::create([
                'chat_widget_id' => $widget->id,
                'chat_conversation_id' => $conversation->id,
                'type' => 'complete',
                'meta' => ['outcome' => 'support'],
            ]);

            return [];
        }

        $answers = $conversation->answers ?? [];
        $email = strtolower(trim((string) ($answers['email'] ?? '')));

        // Without an email we have no identity to capture — record completion only.
        if ($email === '') {
            $conversation->forceFill(['status' => 'closed'])->save();
            ChatEvent::create([
                'chat_widget_id' => $widget->id,
                'chat_conversation_id' => $conversation->id,
                'type' => 'complete',
                'meta' => ['outcome' => $outcome, 'anonymous' => true],
            ]);

            return [];
        }

        // ---- Contact: dedupe by email within the tenant (silent-dedupe pattern) ----
        $contact = Contact::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($contact === null) {
            $contact = Contact::create([
                'first_name' => (string) ($answers['first_name'] ?? 'Website'),
                // Leave the surname empty rather than inventing one: a flow that
                // never asks would otherwise produce "Michael Visitor" and that
                // would end up in an email addressed to the person.
                'last_name' => $answers['last_name'] ?? null,
                'email' => $email,
                'phone' => $answers['phone'] ?? null,
                'lead_source' => 'website_chat',
                'campaign' => $conversation->attribution['utm_campaign'] ?? null,
                'lifecycle_stage' => 'lead',
            ]);
        }

        // ---- Lead row so it appears in CRM > Leads with chat provenance ----
        $leadId = $conversation->lead_id;
        $ownerId = $conversation->assignee_id;
        if ($leadId === null) {
            $lead = Lead::create([
                'first_name' => (string) ($answers['first_name'] ?? 'Website'),
                'last_name' => $answers['last_name'] ?? null,
                'email' => $email,
                'phone' => $answers['phone'] ?? null,
                'company_name' => $answers['company_name'] ?? null,
                'source' => 'website_chat',
                'campaign' => $conversation->attribution['utm_campaign'] ?? null,
                'status' => 'new',
                'owner_id' => $this->assignOwner($widget),
                'lead_score' => $conversation->lead_score,
            ]);
            $leadId = $lead->id;
            $ownerId = $lead->owner_id;
        }

        // ---- Page context feeds intent (and through it, scoring + alerts) ----
        $page = $conversation->attribution['page'] ?? null;
        if ($page) {
            $this->intent->record($contact, 'page_visit', 1, (string) $page);
        }

        // ---- Score: rules engine first, then keep the higher of rule/chat score ----
        $contact = $this->scoring->apply($contact);
        if ($conversation->lead_score > $contact->lead_score) {
            $contact->forceFill(['lead_score' => $conversation->lead_score])->save();
        }

        // ---- Alerts: rule evaluation plus a direct fire for hot/priority chats ----
        $this->alerts->evaluate($contact);
        $isHot = $this->scoring->temperature($contact->lead_score) === 'hot'
            || ($answers['_priority'] ?? null) === 'high';
        if ($isHot) {
            $this->alerts->fire(
                'meeting_request',
                $contact,
                sprintf('Hot website-chat lead: %s (%s) — score %d.', $contact->fullName(), $email, $contact->lead_score),
            );
        }

        $conversation->forceFill([
            'contact_id' => $contact->id,
            'lead_id' => $leadId,
            'assignee_id' => $ownerId,
            'status' => $isHot ? 'qualified' : 'converted',
        ])->save();

        ChatEvent::create([
            'chat_widget_id' => $widget->id,
            'chat_conversation_id' => $conversation->id,
            'type' => 'lead',
            'meta' => ['score' => $contact->lead_score],
        ]);
        if ($isHot) {
            ChatEvent::create([
                'chat_widget_id' => $widget->id,
                'chat_conversation_id' => $conversation->id,
                'type' => 'qualified',
            ]);
        }

        // ---- Meeting offer: hand the widget the tenant's public booking link ----
        $extra = [];
        if ($outcome === 'meeting') {
            $bookingPage = BookingPage::query()->where('is_active', true)->first();
            if ($bookingPage) {
                $extra['booking_url'] = url('/b/'.$bookingPage->slug);
                ChatEvent::create([
                    'chat_widget_id' => $widget->id,
                    'chat_conversation_id' => $conversation->id,
                    'type' => 'meeting',
                ]);
            }
        }

        ChatEvent::create([
            'chat_widget_id' => $widget->id,
            'chat_conversation_id' => $conversation->id,
            'type' => 'complete',
            'meta' => ['outcome' => $outcome],
        ]);

        return $extra;
    }

    /**
     * Round-robin owner assignment: the active member with the fewest open
     * chat conversations (mirrors BookingService::assignOwner). Routing rules
     * (service/geo/size) arrive with the routing phase.
     */
    private function assignOwner(ChatWidget $widget): ?int
    {
        $organization = $widget->organization()->first();
        if ($organization === null) {
            return null;
        }

        $fixed = $widget->routing['assignee_id'] ?? null;
        if ($fixed) {
            return (int) $fixed;
        }

        $members = $organization->members()->wherePivot('status', 'active')->pluck('users.id')->all();
        if ($members === []) {
            return null;
        }

        $load = ChatConversation::query()
            ->whereIn('assignee_id', $members)
            ->whereNotIn('status', ['closed', 'spam'])
            ->selectRaw('assignee_id, COUNT(*) AS total')
            ->groupBy('assignee_id')
            ->pluck('total', 'assignee_id')
            ->all();

        $counts = [];
        foreach ($members as $id) {
            $counts[$id] = (int) ($load[$id] ?? 0);
        }
        asort($counts);

        return (int) array_key_first($counts);
    }
}
