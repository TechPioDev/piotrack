<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email to someone who asked for help but is not a user of the workspace -
 * a client who opened a ticket from the website chat.
 *
 * Portal users hear about their tickets through the platform's own
 * notifications; a website visitor has no account to receive them, so the desk
 * told them nothing, while the chat had promised "our team will follow up by
 * email". Three moments are sent: the ticket was received, the team replied,
 * and it was resolved.
 *
 * The receipt deliberately repeats nothing the visitor typed. The address was
 * typed by whoever was in the chat, so echoing their words back would let
 * anyone use a tenant's chat to send their own text to a stranger's inbox.
 * Replies are written by the team, and replying to one reaches whoever wrote
 * it.
 */
class TicketRequesterNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'received'|'replied'|'resolved'  $event
     */
    public function __construct(
        private readonly string $event,
        private readonly int $ticketId,
        private readonly string $company,
        private readonly ?string $message = null,
        private readonly ?string $agent = null,
        private readonly ?string $replyTo = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reference = "#{$this->ticketId}";

        $mail = (new MailMessage)->greeting('Hello,');

        $mail = match ($this->event) {
            'received' => $mail
                ->subject("We have your request ({$reference}) - {$this->company}")
                ->line("Thanks for getting in touch with {$this->company} on our website chat. Your request is now support ticket {$reference}.")
                ->line('Someone from the team will reply to you at this email address.'),
            'replied' => $this->withReply($mail
                ->subject("Re: your request {$reference} - {$this->company}")
                ->line(sprintf('%s replied to your request %s:', $this->agent ?? "The {$this->company} team", $reference))),
            default => $mail
                ->subject("Your request {$reference} is resolved - {$this->company}")
                ->line("We have marked your request {$reference} as resolved."),
        };

        if ($this->replyTo !== null) {
            $mail->replyTo($this->replyTo, $this->agent ?? $this->company)
                ->line($this->event === 'resolved'
                    ? 'If anything is still not right, reply to this email and it reaches the team.'
                    : 'Reply to this email to carry on the conversation.');
        }

        return $mail->salutation($this->company);
    }

    private function withReply(MailMessage $mail): MailMessage
    {
        foreach (preg_split('/\R{2,}/', trim((string) $this->message)) ?: [] as $paragraph) {
            $mail->line($paragraph);
        }

        return $mail;
    }
}
