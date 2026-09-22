<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A website visitor left the chat before the team answered: the answer follows
 * them by email. Replying to the email reaches the person who wrote it, so the
 * conversation can carry on without the visitor coming back to the website.
 */
class ChatReplyNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $replies
     */
    public function __construct(
        private readonly string $company,
        private readonly string $agent,
        private readonly array $replies,
        private readonly ?string $visitorName = null,
        private readonly ?string $replyTo = null,
        private readonly ?string $page = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(sprintf('%s replied to your chat with %s', $this->agent, $this->company))
            ->greeting($this->visitorName !== null && $this->visitorName !== '' ? "Hi {$this->visitorName}," : 'Hello,')
            ->line(sprintf('You had left the chat on the %s website by the time %s replied, so here is the reply:', $this->company, $this->agent));

        foreach ($this->replies as $reply) {
            $mail->line('"'.$reply.'"');
        }

        if ($this->replyTo !== null) {
            $mail->replyTo($this->replyTo, $this->agent)
                ->line(sprintf('Reply to this email to carry on the conversation with %s.', $this->agent));
        }

        if ($this->page !== null) {
            $mail->action('Back to the website', $this->page);
        }

        return $mail;
    }
}
