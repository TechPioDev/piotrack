<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Notifications\ChatReplyNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Team replies a visitor never saw, sent to them by email once they have left
 * the chat. The widget polls while it is open, so a visitor it heard from in
 * the last minute is still there and sees replies in the chat itself; and it
 * reports how far through the conversation it got, so only replies that never
 * reached the screen are sent - each one once.
 */
class ChatReplyMailer
{
    /** How recently the widget must have been in touch for the visitor to count as present. */
    public const PRESENT_FOR_SECONDS = 60;

    /** @return int how many replies were emailed */
    public function sendPending(ChatConversation $conversation): int
    {
        $widget = $conversation->widget;
        if ($widget === null || $conversation->is_preview || ! ($widget->settings['email_replies'] ?? true)) {
            return 0;
        }

        $email = trim((string) ($conversation->answers['email'] ?? $conversation->contact->email ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 0;
        }

        $seen = $conversation->visitor_seen_at;
        if ($seen !== null && $seen->gt(now()->subSeconds(self::PRESENT_FOR_SECONDS))) {
            return 0;
        }

        $pending = $conversation->messages()
            ->with('author:id,name,email')
            ->where('role', 'agent')
            ->where('id', '>', (int) $conversation->visitor_seen_message_id)
            ->orderBy('id')
            ->get()
            ->reject(fn (ChatMessage $m) => isset($m->meta['emailed_at']))
            ->values();

        if ($pending->isEmpty()) {
            return 0;
        }

        $author = $pending->last()->author;
        $page = (string) ($conversation->attribution['page'] ?? '');

        Notification::route('mail', $email)->notify(new ChatReplyNotification(
            company: (string) ($widget->theme['company'] ?? $widget->name),
            agent: $author !== null ? (string) $author->name : 'Our team',
            replies: $pending->map(fn (ChatMessage $m) => (string) $m->body)->all(),
            visitorName: trim((string) ($conversation->answers['first_name'] ?? '')),
            replyTo: $author?->email,
            page: preg_match('#^https?://#i', $page) === 1 ? $page : null,
        ));

        foreach ($pending as $message) {
            $message->forceFill(['meta' => [...($message->meta ?? []), 'emailed_at' => now()->toIso8601String()]])->save();
        }

        return $pending->count();
    }
}
