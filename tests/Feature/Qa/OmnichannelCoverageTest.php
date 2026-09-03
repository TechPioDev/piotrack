<?php

declare(strict_types=1);

/**
 * Omnichannel Marketing close-out (Phase 24 — OMNI-002/004/005/006/007/008/011/015).
 *
 * The rollup now surfaces every register channel as a distinct row computed from
 * the tenant's OWN records: per-network social activity, per-platform ad
 * campaigns and clicks, the GBP surface Local SEO coordinates, published video
 * formats, and the earned-media pipeline as PR. A channel with no data is
 * inactive with a zero metric — never faked — and live network-side insights
 * stay connector-gated rather than invented.
 */

use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\AuthorityAsset;
use App\Models\Citation;
use App\Models\ContentPiece;
use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use App\Models\SeoLocation;
use App\Models\SocialPost;
use App\Services\Analytics\OmnichannelService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Omni Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('covers every register channel, all honestly inactive on an empty org', function () {
    $channels = collect(app(OmnichannelService::class)->channels());

    expect($channels->pluck('channel')->all())->toBe([
        'seo', 'google_maps', 'google_ads', 'microsoft_ads', 'linkedin', 'facebook', 'x', 'youtube',
        'email', 'sms', 'video', 'content', 'retargeting', 'ai_search', 'pr',
    ]);

    foreach ($channels as $channel) {
        expect($channel['active'])->toBeFalse()
            ->and($channel['value'])->toBe(0)
            ->and($channel['detail'])->toBeNull()
            ->and($channel['href'])->toStartWith('/');
    }
});

it('measures each social network by its own published posts, tenant-scoped', function () {
    SocialPost::create(['channel' => 'linkedin', 'body' => 'A', 'status' => 'published', 'published_at' => now()]);
    SocialPost::create(['channel' => 'linkedin', 'body' => 'B', 'status' => 'published', 'published_at' => now()]);
    SocialPost::create(['channel' => 'x', 'body' => 'C', 'status' => 'published', 'published_at' => now()]);
    SocialPost::create(['channel' => 'facebook', 'body' => 'D', 'status' => 'draft']); // drafts never count
    app(CurrentOrganization::class)->forget();

    // Another tenant's activity must not leak into this rollup.
    [$rival] = makeOrganization('Rival Omni Org');
    app(CurrentOrganization::class)->set($rival);
    SocialPost::create(['channel' => 'youtube', 'body' => 'E', 'status' => 'published', 'published_at' => now()]);
    app(CurrentOrganization::class)->forget();

    app(CurrentOrganization::class)->set($this->org);
    $channels = collect(app(OmnichannelService::class)->channels())->keyBy('channel');

    expect($channels['linkedin']['active'])->toBeTrue()->and($channels['linkedin']['value'])->toBe(2)
        ->and($channels['x']['active'])->toBeTrue()->and($channels['x']['value'])->toBe(1)
        ->and($channels['facebook']['active'])->toBeFalse()->and($channels['facebook']['value'])->toBe(0)
        ->and($channels['youtube']['active'])->toBeFalse()->and($channels['youtube']['value'])->toBe(0);
});

it('splits paid channels by ad platform with clicks from the recorded metrics', function () {
    $google = AdCampaign::create(['platform' => 'google_search', 'name' => 'Search Q3', 'status' => 'active']);
    $bing = AdCampaign::create(['platform' => 'microsoft', 'name' => 'Bing Q3', 'status' => 'active']);
    AdMetric::create(['ad_campaign_id' => $google->id, 'date' => now()->toDateString(), 'impressions' => 1000, 'clicks' => 120, 'spend' => 20000, 'conversions' => 6, 'revenue' => 0]);
    AdMetric::create(['ad_campaign_id' => $bing->id, 'date' => now()->toDateString(), 'impressions' => 400, 'clicks' => 35, 'spend' => 8000, 'conversions' => 2, 'revenue' => 0]);

    $channels = collect(app(OmnichannelService::class)->channels())->keyBy('channel');

    expect($channels['google_ads']['value'])->toBe(120)
        ->and($channels['google_ads']['detail'])->toBe('1 campaigns')
        ->and($channels['microsoft_ads']['active'])->toBeTrue()
        ->and($channels['microsoft_ads']['value'])->toBe(35);

    // A LinkedIn ad campaign activates the LinkedIn channel even with no posts,
    // and its clicks ride the detail line rather than posing as posts.
    $li = AdCampaign::create(['platform' => 'linkedin', 'name' => 'ABM push', 'status' => 'active']);
    AdMetric::create(['ad_campaign_id' => $li->id, 'date' => now()->toDateString(), 'impressions' => 300, 'clicks' => 12, 'spend' => 9000, 'conversions' => 1, 'revenue' => 0]);

    $linkedin = collect(app(OmnichannelService::class)->channels())->firstWhere('channel', 'linkedin');
    expect($linkedin['active'])->toBeTrue()
        ->and($linkedin['value'])->toBe(0)
        ->and($linkedin['detail'])->toBe('12 ad clicks');
});

it('computes Maps, PR and Video from the GBP surface, the earned-media pipeline and video formats', function () {
    $philly = SeoLocation::create(['name' => 'Philly HQ', 'city' => 'Philadelphia', 'is_active' => true, 'gbp_place_id' => 'ChIJtest123']);
    SeoLocation::create(['name' => 'Satellite', 'city' => 'Camden', 'is_active' => true]); // no GBP link yet
    Citation::create(['seo_location_id' => $philly->id, 'source' => 'yelp', 'status' => 'consistent']);
    Citation::create(['seo_location_id' => $philly->id, 'source' => 'yellowpages', 'status' => 'mismatch']);

    $campaign = OutreachCampaign::create(['name' => 'Earned media Q3', 'goal' => 'authority', 'status' => 'active']);
    OutreachProspect::create(['outreach_campaign_id' => $campaign->id, 'name' => 'MSP Insider', 'domain' => 'mspinsider.example', 'status' => 'won', 'placement_url' => 'https://mspinsider.example/feature']);
    OutreachProspect::create(['outreach_campaign_id' => $campaign->id, 'name' => 'TechDaily', 'domain' => 'techdaily.example', 'status' => 'pitched']); // pitched is not earned
    AuthorityAsset::create(['type' => 'article', 'name' => 'Guest feature', 'issuer' => 'mspinsider.example', 'url' => 'https://mspinsider.example/feature']);

    ContentPiece::create(['title' => 'Ransomware webinar', 'slug' => 'ransomware-webinar', 'content_type' => 'webinar', 'status' => 'published', 'body' => 'x']);
    ContentPiece::create(['title' => 'Explainer', 'slug' => 'explainer-video', 'content_type' => 'video', 'status' => 'draft', 'body' => 'x']); // drafts never count

    $channels = collect(app(OmnichannelService::class)->channels())->keyBy('channel');

    expect($channels['google_maps']['active'])->toBeTrue()
        ->and($channels['google_maps']['value'])->toBe(1) // only the GBP-linked location
        ->and($channels['google_maps']['detail'])->toBe('1 consistent citations')
        ->and($channels['pr']['value'])->toBe(1) // won placement only, never pitches
        ->and($channels['pr']['detail'])->toBe('1 authority assets')
        ->and($channels['video']['active'])->toBeTrue()
        ->and($channels['video']['value'])->toBe(1);
});

it('renders the full rollup on the omnichannel page with module links', function () {
    SocialPost::create(['channel' => 'x', 'body' => 'Hi', 'status' => 'published', 'published_at' => now()]);
    app(CurrentOrganization::class)->forget();

    $response = $this->actingAs($this->owner)->get(route('analytics.omnichannel.index'))->assertOk();

    $channels = collect($response->viewData('page')['props']['channels']);
    expect($channels)->toHaveCount(15)
        ->and($channels->firstWhere('channel', 'x')['active'])->toBeTrue()
        ->and($channels->firstWhere('channel', 'x')['href'])->toBe('/content/social')
        ->and($channels->firstWhere('channel', 'google_maps')['href'])->toBe('/seo/local')
        ->and($channels->firstWhere('channel', 'pr')['href'])->toBe('/content/outreach');

    app(CurrentOrganization::class)->set($this->org);
});
