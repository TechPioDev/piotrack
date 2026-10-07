<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * A client asked the website chat for help and it became a support ticket.
 *
 * The ticket used to land on the desk with no requester and no assignee, which
 * notified nobody at all - so the one person in the conversation who was
 * actually in trouble waited longest. This tells the workspace owners and the
 * workspace's own channels (Teams, Slack, webhook), the same way a visitor left
 * waiting in the chat does.
 *
 * It names the ticket and the request the visitor picked, never what they
 * typed: this text is posted straight into Slack and Teams, where a "name"
 * such as <!channel> would page the whole company.
 */
class ChatTicketOpenedNotification extends PlatformNotification
{
    public function __construct(
        private readonly int $ticketId,
        private readonly string $topic,
        private readonly string $priority,
        private readonly ?string $assignee,
        /** The client came back and their new message joined a ticket already open. */
        private readonly bool $addedTo = false,
    ) {}

    public function category(): string
    {
        return 'operations';
    }

    public function title(): string
    {
        if ($this->addedTo) {
            return 'A client has added to a support request that nobody has picked up';
        }

        return in_array($this->priority, ['high', 'urgent'], true)
            ? 'Urgent support request from the website chat'
            : 'New support request from the website chat';
    }

    public function body(): string
    {
        return sprintf(
            'Ticket #%d: %s - %s.',
            $this->ticketId,
            $this->topic,
            $this->assignee !== null ? "assigned to {$this->assignee}" : 'nobody is assigned yet',
        );
    }

    public function url(): ?string
    {
        return url('/support#ticket-'.$this->ticketId);
    }

    /** One alert per ticket, however many times the sweep looks at it. */
    public function dedupeKey(): ?string
    {
        return ($this->addedTo ? 'chat.ticket_added_to:' : 'chat.ticket_opened:').$this->ticketId;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title().' (#'.$this->ticketId.')')
            ->line($this->addedTo
                ? 'A client came back to the website chat about a request that is still open, and their new message has been added to it.'
                : 'A client asked for help on the website chat, and it is now a support ticket.')
            ->line($this->body())
            ->line($this->assignee !== null
                ? "It is assigned to {$this->assignee}, who was already in the conversation or handles this chat."
                : 'Nobody is assigned yet. They were told the team will follow up by email.')
            ->action('Open the ticket', (string) $this->url());
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            ...parent::toArray($notifiable),
            'type' => 'chat.ticket_opened',
            'ticket_id' => $this->ticketId,
        ];
    }
}
