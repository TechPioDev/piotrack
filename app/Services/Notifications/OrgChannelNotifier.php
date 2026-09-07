<?php

namespace App\Services\Notifications;

use App\Models\NotificationChannel;
use App\Models\Organization;
use App\Notifications\PlatformNotification;
use App\Support\AuditLogger;
use App\Support\UrlGuard;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * NOTIF-004/005: fan one organization-level notification out to the org's
 * outbound channels — Slack/Teams incoming webhooks (their `{text}` payload)
 * and generic webhooks (JSON event, HMAC-SHA256 signed when the channel has
 * a secret). Fired ONCE per event, never per recipient; URLs pass the SSRF
 * guard; a channel failure is audited and never breaks the business flow.
 */
class OrgChannelNotifier
{
    public function __construct(
        private UrlGuard $urls,
        private AuditLogger $audit,
    ) {}

    public function send(Organization $organization, PlatformNotification $notification): int
    {
        $channels = NotificationChannel::withoutGlobalScope('tenant')
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get();

        $sent = 0;
        foreach ($channels as $channel) {
            try {
                if (! $this->urls->isFetchable($channel->url)) {
                    continue;
                }

                $this->post($channel, $notification);
                $sent++;
            } catch (Throwable $e) {
                $this->audit->log('notifications.channel.failed', context: ['kind' => $channel->kind, 'error' => $e->getMessage()], resourceType: 'notification_channel', resourceId: (string) $channel->id, organizationId: $organization->id);
            }
        }

        return $sent;
    }

    private function post(NotificationChannel $channel, PlatformNotification $notification): void
    {
        if ($channel->kind === 'webhook') {
            $payload = [
                'event' => 'notification',
                'category' => $notification->category(),
                'title' => $notification->title(),
                'body' => $notification->body(),
                'url' => $notification->url(),
                'sent_at' => now()->toIso8601String(),
            ];

            $request = Http::timeout(5);
            if ($channel->secret !== null && $channel->secret !== '') {
                $request = $request->withHeaders([
                    'X-Piotrack-Signature' => hash_hmac('sha256', (string) json_encode($payload), $channel->secret),
                ]);
            }

            $request->post($channel->url, $payload)->throw();

            return;
        }

        // Slack and Teams incoming webhooks both accept a plain `text` payload.
        $text = ($channel->kind === 'slack' ? '*'.$notification->title().'*' : $notification->title())
            ."\n".$notification->body();

        Http::timeout(5)->post($channel->url, ['text' => $text])->throw();
    }
}
