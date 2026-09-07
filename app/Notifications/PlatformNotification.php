<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for platform notifications (NOTIF-001/002). Resolves delivery channels
 * from the recipient's per-category preferences: in-app is always on, email is
 * opt-out, and the `security` category ignores opt-out entirely. Email is
 * queued (JOBS).
 */
abstract class PlatformNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** billing | members | operations | security */
    abstract public function category(): string;

    abstract public function title(): string;

    abstract public function body(): string;

    public function url(): ?string
    {
        return null;
    }

    /**
     * Stable identity for repeat-prone alerts (ALRT): the sweep skips sending
     * when a notification with the same key was already created today.
     */
    public function dedupeKey(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $emailOn = $this->category() === 'security'
            || (! $notifiable instanceof User)
            || $notifiable->wantsChannel($this->category(), 'email');

        if ($emailOn) {
            $channels[] = 'mail';
        }

        // NOTIF-003: SMS is opt-in per category and needs a phone on file.
        if ($notifiable instanceof User && $notifiable->smsOptedIn($this->category())) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category(),
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'key' => $this->dedupeKey(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->line($this->body());

        if ($this->url() !== null) {
            $mail->action('View', url($this->url()));
        }

        return $mail;
    }
}
