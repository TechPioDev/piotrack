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
        // Title and body are escaped first: bodies carry text a website visitor
        // typed, so only the bold markup we add ourselves may render.
        $title = self::escape($channel->kind, $notification->title());
        $body = self::escape($channel->kind, $notification->body());
        $text = ($channel->kind === 'slack' ? '*'.$title.'*' : $title)."\n".$body;

        Http::timeout(5)->post($channel->url, ['text' => $text])->throw();
    }

    /**
     * Make text safe to post into a Slack or Teams `text` payload, so that
     * whatever it contains is shown rather than acted on.
     *
     * Slack reads `<...>` as control sequences: `<!channel>` and `<!here>`
     * page everyone in the channel, `<@U123>` pings a person, and
     * `<https://evil.test|click here>` shows a link under a false label. Its
     * documented escaping is exactly `&`, `<` and `>`.
     *
     * Teams renders a subset of markdown and HTML: `[label](url)` and
     * `<a href>` links, `<at>` mentions, emphasis and code spans. Those
     * characters become numeric character references, which Teams shows as
     * the plain character and which markdown never treats as syntax. `&` goes
     * too, so text that already contains a reference such as `&#91;` stays
     * literal instead of turning back into a bracket.
     */
    public static function escape(string $kind, string $text): string
    {
        if ($kind === 'slack') {
            return strtr($text, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
        }

        return strtr($text, [
            '&' => '&amp;', '<' => '&lt;', '>' => '&gt;',
            '[' => '&#91;', ']' => '&#93;',
            '*' => '&#42;', '_' => '&#95;', '~' => '&#126;', '`' => '&#96;', '\\' => '&#92;',
        ]);
    }

    /**
     * Which renderer a bare incoming-webhook URL posts into, for the places
     * that store only a URL. Slack's incoming webhooks all live on
     * hooks.slack.com; anything else gets the stricter Teams escaping, which
     * also neutralises Slack-style `<...>` sequences.
     */
    public static function kindForUrl(string $url): string
    {
        return mb_strtolower((string) parse_url($url, PHP_URL_HOST)) === 'hooks.slack.com' ? 'slack' : 'teams';
    }
}
