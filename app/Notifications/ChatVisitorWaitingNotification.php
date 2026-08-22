<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A website visitor asked to speak to someone and nobody was available (§32).
 *
 * Worth interrupting a team for: the visitor has been told they will hear back,
 * so this is a promise that now needs keeping.
 */
class ChatVisitorWaitingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $conversationId,
        private readonly string $reason,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A website visitor is waiting for a reply')
            ->line('Someone asked to speak to your team on the website chat, and nobody was available.')
            ->line($this->reason)
            ->action('Open the conversation', url('/chat/conversations/'.$this->conversationId));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'chat.visitor_waiting',
            'conversation_id' => $this->conversationId,
            'message' => 'A website visitor is waiting for a reply.',
            'url' => '/chat/conversations/'.$this->conversationId,
        ];
    }
}
