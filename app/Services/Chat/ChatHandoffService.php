<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatWidget;
use App\Notifications\ChatVisitorWaitingNotification;
use App\Support\CurrentOrganization;
use App\Support\NotificationDispatcher;

/**
 * Handing a conversation from the bot to a human (§24).
 *
 * The decision has three inputs: the widget's chat mode, whether the tenant is
 * inside business hours, and whether any agent is actually online. If a human
 * cannot take it, the visitor is told what happens next instead of being left
 * waiting for a reply that will not come.
 */
class ChatHandoffService
{
    public function __construct(
        private readonly ChatPresenceService $presence,
        private readonly ChatBusinessHours $hours,
        private readonly NotificationDispatcher $notifications,
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    /** bot | bot_then_human | live — how this widget is meant to be staffed. */
    public function mode(ChatWidget $widget): string
    {
        $mode = (string) (($widget->settings ?? [])['mode'] ?? 'bot');

        return in_array($mode, ['bot', 'bot_then_human', 'live'], true) ? $mode : 'bot';
    }

    /** Whether a visitor should even be offered a human on this widget. */
    public function offersHumans(ChatWidget $widget): bool
    {
        return $this->mode($widget) !== 'bot';
    }

    public function canHandOff(ChatWidget $widget): bool
    {
        return $this->offersHumans($widget)
            && $this->hours->isOpen($widget)
            && $this->presence->anyoneAvailable();
    }

    /**
     * Try to put a human on the conversation.
     *
     * @return array{live: bool, message: string, agent: ?string}
     */
    public function request(ChatWidget $widget, ChatConversation $conversation): array
    {
        $conversation->handoff_requested_at = now();

        if (! $this->offersHumans($widget)) {
            $conversation->save();

            return [
                'live' => false,
                'agent' => null,
                'message' => 'Leave your details below and the team will follow up by email.',
            ];
        }

        if (! $this->hours->isOpen($widget)) {
            $conversation->status = 'waiting';
            $conversation->save();

            $this->notifyWaiting($conversation, 'They arrived outside your business hours.');

            return ['live' => false, 'agent' => null, 'message' => $this->hours->closedMessage($widget)];
        }

        $agent = $this->presence->pickAgent($conversation);
        if ($agent === null) {
            $conversation->status = 'waiting';
            $conversation->save();

            $this->notifyWaiting($conversation, 'Every agent was offline or busy at the time.');

            return [
                'live' => false,
                'agent' => null,
                'message' => 'Everyone is with another customer at the moment. Leave your details and we will reply within one business day.',
            ];
        }

        $conversation->assignee_id = $agent->id;
        $conversation->is_live = true;
        $conversation->status = 'assigned';
        $conversation->save();

        // The visitor sees who they are talking to; the transcript records the moment.
        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => 'system',
            'body' => sprintf('%s joined the conversation.', $agent->name),
            'meta' => ['event' => 'handoff', 'agent_id' => $agent->id],
        ]);

        return [
            'live' => true,
            'agent' => $agent->name,
            'message' => sprintf('%s is joining you now.', $agent->name),
        ];
    }

    /** Hand the conversation back to the bot / close the live session. */
    public function end(ChatConversation $conversation): void
    {
        $conversation->is_live = false;
        $conversation->save();
    }

    /** Tell the team a visitor asked for a person and did not get one (§32). */
    private function notifyWaiting(ChatConversation $conversation, string $reason): void
    {
        $organization = $this->currentOrganization->get();
        if ($organization === null || $conversation->is_preview) {
            return;
        }

        $this->notifications->toOrganizationOwners(
            $organization,
            new ChatVisitorWaitingNotification($conversation->id, $reason),
        );
    }
}
