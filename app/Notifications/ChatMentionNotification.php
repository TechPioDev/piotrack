<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Someone named you in an internal note on a website-chat conversation (§30).
 */
class ChatMentionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $author,
        private readonly int $conversationId,
        private readonly string $note,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(sprintf('%s mentioned you in a chat conversation', $this->author))
            ->line(sprintf('%s left you a note on a website chat:', $this->author))
            ->line('"'.Str::limit($this->note, 300).'"')
            ->action('Open the conversation', url('/chat/conversations/'.$this->conversationId));
    }
}
