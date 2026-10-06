<?php

declare(strict_types=1);

/**
 * NOTIF-004/005 hardening: what a website visitor typed must reach Slack and
 * Teams as text, never as markup. A chat visitor whose "first name" was
 * <!channel> used to page the whole Slack workspace through the hot-lead
 * alert, and <https://evil.test|click here> planted a link under a false
 * label. The generic webhook's JSON is data, so it keeps the raw values.
 */

use App\Models\Contact;
use App\Models\NotificationChannel;
use App\Notifications\SalesAlertNotification;
use App\Services\Notifications\OrgChannelNotifier;
use App\Services\Sales\AlertService;
use App\Support\CurrentOrganization;
use App\Support\NotificationDispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Escaping Org');
    app(CurrentOrganization::class)->set($this->org);

    Http::fake(['*' => Http::response('ok')]);
    NotificationChannel::create(['kind' => 'slack', 'url' => 'https://93.184.216.34/slack-hook']);
    NotificationChannel::create(['kind' => 'teams', 'url' => 'https://93.184.216.34/teams-hook']);
    NotificationChannel::create(['kind' => 'webhook', 'url' => 'https://93.184.216.34/events']);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function orgChannelTextPostedTo(string $url): string
{
    $posts = Http::recorded(fn ($request) => $request->url() === $url);
    expect($posts)->toHaveCount(1);

    return (string) $posts->first()[0]['text'];
}

it('escapes Slack control sequences and Teams markup in what the channels are sent', function () {
    $body = 'Hot website-chat lead: <!channel> <https://evil.test|x> [click here](https://evil.test) <at>Everyone</at> (a&b@client.test)';

    app(NotificationDispatcher::class)->toOrganizationOwners($this->org, new SalesAlertNotification($body));

    // Slack: our own bold title still renders; nothing of the visitor's does.
    $slack = orgChannelTextPostedTo('https://93.184.216.34/slack-hook');
    expect($slack)->toStartWith("*Sales alert*\n")
        ->toContain('&lt;!channel&gt;')
        ->toContain('&lt;https://evil.test|x&gt;')
        ->toContain('a&amp;b@client.test')
        ->not->toContain('<!channel>')
        ->not->toContain('<https://evil.test|x>');

    // Teams: no link syntax, no HTML, no <at> mention survives.
    $teams = orgChannelTextPostedTo('https://93.184.216.34/teams-hook');
    expect($teams)->toContain('&lt;!channel&gt;')
        ->toContain('&lt;https://evil.test|x&gt;')
        ->toContain('&#91;click here&#93;(https://evil.test)')
        ->toContain('&lt;at&gt;Everyone&lt;/at&gt;')
        ->not->toContain('<!channel>')
        ->not->toContain('<https://evil.test|x>')
        ->not->toContain('[click here]')
        ->not->toContain('<at>');

    // The signed webhook carries data, not rendered text: left exactly as it was.
    Http::assertSent(fn ($request) => $request->url() === 'https://93.184.216.34/events'
        && $request['body'] === $body
        && $request['title'] === 'Sales alert');
});

it('stops a visitor named <!channel> paging the workspace through a hot-lead alert', function () {
    Notification::fake();
    $this->org->update(['alert_channels' => ['webhook_url' => 'https://hooks.slack.com/services/T000/B000/XXXX']]);

    $contact = Contact::create(['first_name' => '<!channel>', 'last_name' => '<https://evil.test|Open invoice>', 'email' => 'visitor@client.test', 'lifecycle_stage' => 'lead']);
    expect(app(AlertService::class)->fire('meeting_request', $contact))->toBeTrue();

    // Both routes into Slack - the org's channel and the alerts page's own
    // incoming webhook - post the name as text.
    foreach (['https://93.184.216.34/slack-hook', 'https://hooks.slack.com/services/T000/B000/XXXX'] as $url) {
        expect(orgChannelTextPostedTo($url))
            ->toContain('&lt;!channel&gt; &lt;https://evil.test|Open invoice&gt; requested a meeting.')
            ->not->toContain('<!channel>')
            ->not->toContain('<https://evil.test');
    }

    expect(orgChannelTextPostedTo('https://93.184.216.34/teams-hook'))->not->toContain('<!channel>');
});

it('escapes an alerts-page webhook that is not Slack the stricter Teams way', function () {
    expect(OrgChannelNotifier::kindForUrl('https://hooks.slack.com/services/T/B/X'))->toBe('slack')
        ->and(OrgChannelNotifier::kindForUrl('https://HOOKS.SLACK.COM/services/T/B/X'))->toBe('slack')
        ->and(OrgChannelNotifier::kindForUrl('https://acme.webhook.office.com/webhookb2/abc'))->toBe('teams')
        ->and(OrgChannelNotifier::kindForUrl('https://hooks.slack.com.evil.test/x'))->toBe('teams');

    // A reference the visitor typed stays literal rather than decoding back
    // into a bracket; a backslash cannot re-open an escape.
    expect(OrgChannelNotifier::escape('teams', '&#91;x&#93;(https://evil.test) \\*_~`'))
        ->toBe('&amp;#91;x&amp;#93;(https://evil.test) &#92;&#42;&#95;&#126;&#96;')
        ->and(OrgChannelNotifier::escape('slack', '*bold* & <!here>'))->toBe('*bold* &amp; &lt;!here&gt;');
});
