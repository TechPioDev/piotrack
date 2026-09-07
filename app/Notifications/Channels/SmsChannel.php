<?php

namespace App\Notifications\Channels;

use App\Messaging\MessagingProviderManager;
use App\Messaging\SmsMessage;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Support\Str;
use Throwable;

/**
 * NOTIF-003: platform notifications over SMS through the same provider seam
 * every SMS rides (fixture in dev/tests, Twilio behind credentials). Opt-in
 * per category; a provider failure never breaks the notification flow — the
 * in-app and email copies have already landed.
 */
class SmsChannel
{
    public function __construct(private MessagingProviderManager $providers) {}

    public function send(object $notifiable, PlatformNotification $notification): void
    {
        if (! $notifiable instanceof User || $notifiable->phone === null || $notifiable->phone === '') {
            return;
        }

        try {
            $this->providers->sms()->send(new SmsMessage(
                toPhone: $notifiable->phone,
                body: Str::limit($notification->title().' — '.$notification->body(), 320, '…'),
            ));
        } catch (Throwable) {
            // The in-app + email copies already delivered; SMS is best-effort.
        }
    }
}
