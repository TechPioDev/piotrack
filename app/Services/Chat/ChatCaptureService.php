<?php

namespace App\Services\Chat;

use App\Models\BookingPage;
use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatWidget;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\ChatTicketOpenedNotification;
use App\Services\Delivery\TicketService;
use App\Services\Integrations\WebhookDispatcher;
use App\Services\Sales\AlertService;
use App\Services\Sales\IntentService;
use App\Services\Sales\LeadScoringService;
use App\Support\NotificationDispatcher;
use Illuminate\Support\Str;

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
        private readonly TicketService $tickets,
        private readonly WebhookDispatcher $webhooks,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * The support ticket for an existing customer's chat: what they told us,
     * then the whole conversation, so whoever picks it up needs nothing else.
     */
    private function openTicket(ChatConversation $conversation, ?string $topic): Ticket
    {
        $answers = $conversation->answers ?? [];
        $messages = $conversation->messages()
            ->whereIn('role', ['visitor', 'bot', 'agent', 'system'])
            ->orderBy('id')
            ->limit(200)
            ->get(['role', 'body', 'meta']);

        $text = fn (string $key): string => trim((string) ($answers[$key] ?? ''));
        $name = trim($text('first_name').' '.$text('last_name'));

        $details = array_filter([
            'Name' => $name,
            'Email' => $text('email'),
            'Phone' => $text('phone'),
            'Company' => $text('company_name'),
            'Request' => (string) $topic,
            'Details' => $text('support_issue'),
            'Page' => trim((string) ($conversation->attribution['page'] ?? '')),
        ], fn (string $value) => $value !== '');

        $lines = [];
        foreach ($details as $label => $value) {
            $lines[] = "{$label}: {$value}";
        }
        $transcript = $messages->map(fn ($m) => match ($m->role) {
            'visitor' => 'Visitor',
            'agent' => 'Agent',
            default => 'Bot',
        }.': '.$m->body)->implode("\n");

        // Who asked, so the desk can answer them: a website visitor has no
        // account, only the address they gave. An existing client is usually
        // already in the CRM, and the ticket points at their record - but a
        // stranger claiming to be a client is not turned into a contact.
        $email = strtolower($text('email'));
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
        $contact = $email !== null ? Contact::query()->whereRaw('LOWER(email) = ?', [$email])->first() : null;

        return $this->tickets->open([
            'subject' => Str::limit(sprintf('Website chat: %s from %s', $topic ?? 'support request', $name !== '' ? $name : ($text('email') ?: 'a website visitor')), 180),
            'body' => mb_substr(implode("\n", $lines)."\n\nChat transcript\n".$transcript, 0, 60000),
            'priority' => ($answers['_priority'] ?? null) === 'high' ? 'high' : 'normal',
            'category' => 'website_chat',
            'requester_email' => $email,
            'requester_name' => $name !== '' ? Str::limit($name, 160, '') : null,
            'contact_id' => $contact?->id,
            'chat_conversation_id' => $conversation->id,
        ]);
    }

    /**
     * What the visitor asked for: the last answer they picked on the way to
     * the ticket ("Billing", "Technical support"), whatever the flow calls that
     * field. A button the business wrote, never text the visitor typed.
     */
    private function topicOf(ChatConversation $conversation): ?string
    {
        $picked = $conversation->messages()
            ->where('role', 'visitor')
            ->orderBy('id')
            ->limit(200)
            ->get(['body', 'meta'])
            ->filter(fn ($m) => isset($m->meta['option']))
            ->last()?->body;

        return $picked !== null && trim((string) $picked) !== '' ? (string) $picked : null;
    }

    /**
     * Make sure somebody knows a client is waiting on a ticket.
     *
     * Whoever was already talking to them keeps it; failing that, whoever this
     * chat sends its conversations to. The owners and the workspace's Teams or
     * Slack are told either way - once, not again for an owner who was just
     * assigned it - and the client gets a receipt with their ticket number.
     */
    private function followUpTicket(ChatWidget $widget, ChatConversation $conversation, Ticket $ticket, ?string $topic): void
    {
        $organization = $widget->organization()->first();
        if ($organization === null) {
            return;
        }

        $assignee = null;
        foreach ([$conversation->assignee_id, $widget->routing['assignee_id'] ?? null] as $candidate) {
            // Someone who has since left the workspace cannot take it.
            $assignee = $candidate ? $organization->members()->wherePivot('status', 'active')->find((int) $candidate) : null;
            if ($assignee instanceof User) {
                break;
            }
        }

        if ($assignee instanceof User) {
            $this->tickets->assign($ticket, $assignee);
        }

        $this->tickets->acknowledge($ticket);

        $this->notifications->toOrganizationOwners(
            $organization,
            new ChatTicketOpenedNotification($ticket->id, $topic ?? 'Support request', $ticket->priority, $assignee?->name),
            exceptUserId: $assignee?->id,
        );
    }

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
     * What the visitor told us, without the engine's own bookkeeping keys.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function visibleAnswers(array $answers): array
    {
        return array_filter(
            $answers,
            fn (string $key): bool => ! str_starts_with($key, '_'),
            ARRAY_FILTER_USE_KEY,
        );
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
        // They become a ticket on the support desk instead, so the request is
        // worked like any other support request rather than waiting in chat.
        if ($outcome === 'support') {
            $topic = $this->topicOf($conversation);
            $ticket = $this->openTicket($conversation, $topic);
            $conversation->forceFill([
                'contact_id' => $conversation->contact_id ?? $ticket->contact_id,
                'status' => 'closed',
                'answers' => [...($conversation->answers ?? []), '_ticket' => $ticket->id],
                'tags' => array_values(array_filter((array) (($conversation->answers ?? [])['_tags'] ?? []))) ?: null,
            ])->save();
            ChatEvent::create([
                'chat_widget_id' => $widget->id,
                'chat_conversation_id' => $conversation->id,
                'type' => 'complete',
                'meta' => ['outcome' => 'support', 'ticket_id' => $ticket->id],
            ]);

            $this->followUpTicket($widget, $conversation, $ticket, $topic);

            return [];
        }

        $answers = $conversation->answers ?? [];
        $tags = array_values(array_filter((array) ($answers['_tags'] ?? [])));
        $email = strtolower(trim((string) ($answers['email'] ?? '')));

        // Without an email we have no identity to capture — record completion only.
        if ($email === '') {
            $conversation->forceFill(['status' => 'closed', 'tags' => $tags ?: null])->save();
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
            'tags' => $tags ?: null,
        ])->save();

        // A chat lead reaches the outside world the way a form lead does: the
        // same event, so a subscriber wired to Zapier, n8n or a PSA gets both.
        $this->webhooks->dispatch('lead.captured', [
            'contact_id' => $contact->id,
            'name' => $contact->fullName(),
            'email' => $contact->email,
            'form' => $widget->name,
            'source' => 'website_chat',
            'lead_id' => $leadId,
            'lead_score' => $contact->lead_score,
            'tags' => $tags,
            'answers' => $this->visibleAnswers($answers),
            'lifecycle_stage' => $contact->lifecycle_stage,
        ]);

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
        // Outcome 'booked' means a slot was already taken IN the chat: the
        // meeting event was emitted at booking time, and handing the visitor a
        // "choose a time" link after they chose one would only confuse them.
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
