<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * A website visitor asked to speak to someone and nobody was available (§32).
 *
 * Worth interrupting a team for: the visitor has been told they will hear back,
 * so this is a promise that now needs keeping - and a promise kept an hour
 * later is usually a promise broken. It used to be email and an in-app bell
 * only, which is no use to a team living in Teams or Slack, or to an engineer
 * out on site. As a platform notification it now reaches the workspace's own
 * channels as well, and the phone of anyone who opted into operations texts.
 */
class ChatVisitorWaitingNotification extends PlatformNotification
{
    public function __construct(
        private readonly int $conversationId,
        private readonly string $reason,
    ) {}

    public function category(): string
    {
        return 'operations';
    }

    public function title(): string
    {
        return 'A website visitor is waiting for a reply';
    }

    public function body(): string
    {
        return $this->reason;
    }

    public function url(): ?string
    {
        return url('/chat/conversations/'.$this->conversationId);
    }

    /**
     * One page per conversation per day: a visitor who asks twice in an
     * afternoon is the same promise, not two.
     */
    public function dedupeKey(): ?string
    {
        return 'chat.visitor_waiting:'.$this->conversationId;
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
