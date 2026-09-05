<?php

declare(strict_types=1);

/**
 * Analytics Dashboard + Privacy close-out (Phase 35 — ANLY-001..008/013/014,
 * PRIV-002/006).
 *
 * First-party web analytics (sessions/users/pageviews + classifier channel
 * split + organic conversions), the consent-gated pixel with its durable
 * preference record, and the shared-secret ESP bounce/complaint webhook.
 */

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\CookiePreference;
use App\Models\Deal;
use App\Models\MarketingList;
use App\Models\Pipeline;
use App\Models\SitePage;
use App\Models\Suppression;
use App\Models\Visitor;
use App\Services\Analytics\AnalyticsService;
use App\Services\Marketing\CampaignService;
use App\Services\Marketing\ListService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('AnlyPriv Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('measures sessions, users, pageviews and the channel split from first-party visitors', function () {
    $lead = Contact::create(['first_name' => 'Org', 'email' => 'org@x.com', 'lifecycle_stage' => 'customer']);
    Visitor::create(['visitor_key' => 'wa1', 'first_seen_at' => now(), 'visits' => 3, 'page_views' => 9, 'utm_source' => 'google', 'utm_medium' => 'organic', 'contact_id' => $lead->id]);
    Visitor::create(['visitor_key' => 'wa2', 'first_seen_at' => now(), 'visits' => 2, 'page_views' => 4, 'utm_source' => 'google', 'utm_medium' => 'cpc']);
    Visitor::create(['visitor_key' => 'wa3', 'first_seen_at' => now(), 'visits' => 1, 'page_views' => 1, 'utm_source' => 'linkedin', 'utm_medium' => 'social']);
    Visitor::create(['visitor_key' => 'wa4', 'first_seen_at' => now(), 'visits' => 1, 'page_views' => 2]); // no source: direct

    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $won = $pipeline->stages()->where('is_won', true)->firstOrFail();
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $won->id, 'name' => 'Organic win', 'value' => 180000, 'status' => 'won', 'contact_id' => $lead->id]);

    $web = app(AnalyticsService::class)->web();
    $channels = collect($web['channels'])->keyBy('channel');

    expect($web['sessions'])->toBe(7)
        ->and($web['users'])->toBe(4)
        ->and($web['pageviews'])->toBe(16)
        ->and($channels['organic']['visitors'])->toBe(1)
        ->and($channels['organic']['sessions'])->toBe(3)
        ->and($channels['organic']['leads'])->toBe(1)
        ->and($channels['paid']['sessions'])->toBe(2)
        ->and($channels['social']['visitors'])->toBe(1)
        ->and($channels['direct']['visitors'])->toBe(1)
        // ANLY-014: organic conversions join first-touch -> contact -> wins.
        ->and($web['organic']['customers'])->toBe(1)
        ->and($web['organic']['won_revenue'])->toBe(180000);

    // The dashboard carries it.
    app(CurrentOrganization::class)->forget();
    $props = $this->actingAs($this->owner)->get(route('analytics.dashboard'))->assertOk()->viewData('page')['props'];
    expect($props['metrics']['web']['sessions'])->toBe(7);
    app(CurrentOrganization::class)->set($this->org);
});

it('gates the pixel behind consent and records the durable preference', function () {
    $this->org->forceFill(['tracking_key' => 'tk_privtest0001'])->save();
    SitePage::create(['type' => 'landing', 'slug' => 'priv-page', 'title' => 'Privacy page', 'status' => SitePage::STATUS_PUBLISHED]);
    app(CurrentOrganization::class)->forget();

    $html = $this->get('/s/priv-page')->assertOk()->getContent();

    // The consent gate ships; a hard-coded pixel tag does not.
    expect($html)->toContain('pt_consent')
        ->and($html)->toContain('Essential only')
        ->and($html)->not->toContain('<script src="'.route('public.track.script', 'tk_privtest0001').'"');

    // The banner's beacon lands a durable preference row.
    $this->postJson(route('public.track.consent', 'tk_privtest0001'), ['analytics' => true])->assertOk();
    $this->postJson(route('public.track.consent', 'tk_privtest0001'), ['analytics' => 'not-bool'])->assertStatus(422);
    $this->postJson(route('public.track.consent', 'tk_unknownkey000'), ['analytics' => true])->assertNotFound();

    expect(CookiePreference::where('analytics', true)->count())->toBe(1);
});

it('suppresses bounced and complained addresses through the shared-secret ESP webhook', function () {
    config(['services.email_webhook.secret' => 'whsec_test_123']);

    $list = MarketingList::create(['name' => 'Bounce list', 'type' => 'static']);
    $contact = Contact::create(['first_name' => 'B', 'email' => 'bounce@x.com', 'email_opt_in' => true]);
    app(ListService::class)->addContact($list, $contact);
    $campaign = Campaign::create(['name' => 'C', 'channel' => 'email', 'subject' => 'S', 'body_html' => '<p>x</p>', 'marketing_list_id' => $list->id, 'status' => 'draft']);
    app(CampaignService::class)->send($campaign);
    app(CurrentOrganization::class)->forget();

    // Wrong or missing secret: refused.
    $this->postJson(route('public.email.webhook'), ['type' => 'bounce', 'email' => 'bounce@x.com'])->assertForbidden();
    $this->postJson(route('public.email.webhook'), ['type' => 'bounce', 'email' => 'bounce@x.com'], ['X-Webhook-Secret' => 'wrong'])->assertForbidden();

    // Correct secret: the messaging tenant gets the suppression + marked rows.
    $this->postJson(route('public.email.webhook'), ['type' => 'bounce', 'email' => 'bounce@x.com'], ['X-Webhook-Secret' => 'whsec_test_123'])
        ->assertOk()->assertJsonPath('tenants', 1);

    app(CurrentOrganization::class)->set($this->org);
    expect(Suppression::where('channel', 'email')->where('address', 'bounce@x.com')->where('reason', 'bounce')->exists())->toBeTrue()
        ->and(CampaignRecipient::where('address', 'bounce@x.com')->firstOrFail()->status)->toBe('bounced');

    // The dispatch pipeline honors it: a re-send reaches nobody.
    $second = Campaign::create(['name' => 'C2', 'channel' => 'email', 'subject' => 'S2', 'body_html' => '<p>x</p>', 'marketing_list_id' => $list->id, 'status' => 'draft']);
    app(CampaignService::class)->send($second);
    expect($second->refresh()->stat_sent)->toBe(0);

    // Unconfigured secret: everything refused, even the right guess.
    config(['services.email_webhook.secret' => null]);
    app(CurrentOrganization::class)->forget();
    $this->postJson(route('public.email.webhook'), ['type' => 'complaint', 'email' => 'other@x.com'], ['X-Webhook-Secret' => ''])->assertForbidden();
    app(CurrentOrganization::class)->set($this->org);
});
