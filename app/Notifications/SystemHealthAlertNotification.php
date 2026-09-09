<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * OBS-004: the platform-admin alert for critical health failures — and the
 * matching all-clear. Sent only on STATE TRANSITIONS (HealthAlerter dedupes),
 * never on a timer.
 */
class SystemHealthAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $failing  empty = recovery notice
     */
    public function __construct(public array $failing) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->failing === []) {
            return (new MailMessage)
                ->subject(__('Piotrack health recovered'))
                ->line(__('All health checks are passing again.'));
        }

        return (new MailMessage)
            ->error()
            ->subject(__('Piotrack health alert: :checks failing', ['checks' => implode(', ', $this->failing)]))
            ->line(__('The following critical checks are failing: :checks.', ['checks' => implode(', ', $this->failing)]))
            ->line(__('See /health for live status. You will get one all-clear when checks recover.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->failing === [] ? 'health_recovered' : 'health_alert',
            'failing' => $this->failing,
        ];
    }
}
