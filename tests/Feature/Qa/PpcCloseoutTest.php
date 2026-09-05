<?php

declare(strict_types=1);

/**
 * Google Ads / PPC close-out (Phase 36 — PPC-001/002/010/013/014/016/017/018/020).
 *
 * Bulk-editor CSV export, the first-party account audit, rule-based + AI
 * bid guidance (advisory, data-floor guarded), AI copy drafts that always
 * land as drafts, ad extensions, and the call-tracking + landing-page
 * bridges. Live Ads API delivery stays behind the provider seam (ADR-0006)
 * and is asserted nowhere.
 */

use App\Ai\AiCompletion;
use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\AdExtension;
use App\Models\AdGroup;
use App\Models\AdKeyword;
use App\Models\AdMetric;
use App\Models\Call;
use App\Models\CallTrackingNumber;
use App\Models\LandingPage;
use App\Services\Advertising\BidAdvisor;
use App\Services\Ai\AiGateway;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PPC Org');
    subscribeOrganization($this->org, 'professional'); // includes `advertising`
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function ppcCampaign(array $overrides = []): AdCampaign
{
    return AdCampaign::create(array_merge([
        'platform' => 'google_search', 'name' => 'Dallas MSP Search', 'objective' => 'leads', 'daily_budget' => 5000,
    ], $overrides));
}

function ppcAiMock(string $reply, array &$captured): void
{
    $gateway = Mockery::mock(AiGateway::class);
    $gateway->shouldReceive('run')->andReturnUsing(function ($feature, $key, $vars) use (&$captured, $reply) {
        $captured[] = ['feature' => $feature, 'vars' => $vars];

        return new AiCompletion(text: $reply, promptTokens: 10, completionTokens: 10, model: 'fixture-1');
    });
    app()->instance(AiGateway::class, $gateway);
}

it('exports the full campaign structure as bulk-editor CSV for Google and Microsoft', function () {
    $campaign = ppcCampaign();
    $group = AdGroup::create(['ad_campaign_id' => $campaign->id, 'name' => 'Managed IT', 'bid_strategy' => 'manual_cpc', 'bid_amount' => 350]);
    AdKeyword::create(['ad_group_id' => $group->id, 'phrase' => 'managed it services dallas', 'match_type' => 'phrase', 'is_negative' => false]);
    AdKeyword::create(['ad_group_id' => $group->id, 'phrase' => 'free', 'match_type' => 'broad', 'is_negative' => true]);
    Ad::create(['ad_group_id' => $group->id, 'name' => 'RSA 1', 'headline' => 'Dallas Managed IT', 'body' => '24/7 support for growing teams.', 'destination_url' => 'https://example.com/managed-it', 'status' => 'active']);
    $campaign->extensions()->create(['kind' => 'sitelink', 'text' => 'Pricing', 'url' => 'https://example.com/pricing']);
    $campaign->extensions()->create(['kind' => 'call', 'text' => 'Call us', 'phone' => '+1-214-555-0100']);

    $csv = $this->actingAs($this->owner)
        ->get(route('ads.campaigns.export', $campaign).'?format=google')
        ->assertOk()
        ->assertDownload('google-dallas-msp-search-editor.csv')
        ->streamedContent();

    expect($csv)->toContain('Campaign Daily Budget')
        ->and($csv)->toContain('"Dallas MSP Search",,Campaign,50.00')
        ->and($csv)->toContain('"Managed IT","Ad Group",,3.50')
        ->and($csv)->toContain('"managed it services dallas",Phrase')
        ->and($csv)->toContain('"Negative Keyword"')
        ->and($csv)->toContain('free,Broad')
        ->and($csv)->toContain('"Dallas Managed IT","24/7 support for growing teams.",https://example.com/managed-it')
        ->and($csv)->toContain('"Sitelink Extension"')
        ->and($csv)->toContain('"Call Extension",,,,,"Call us",,,+1-214-555-0100');

    $this->actingAs($this->owner)
        ->get(route('ads.campaigns.export', $campaign).'?format=microsoft')
        ->assertOk()->assertDownload('microsoft-dallas-msp-search-editor.csv');

    $this->actingAs($this->owner)->from(route('ads.campaigns.show', $campaign))
        ->get(route('ads.campaigns.export', $campaign).'?format=yahoo')
        ->assertRedirect()->assertSessionHasErrors('format');
});

it('audits stored account structure and performance, citing the numbers', function () {
    // Broken: no ads, no keywords, no negatives, no extensions.
    $broken = ppcCampaign(['name' => 'Broken']);
    AdGroup::create(['ad_campaign_id' => $broken->id, 'name' => 'Empty group']);

    // Weak: complete structure but sub-1% CTR over enough impressions.
    $weak = ppcCampaign(['name' => 'Weak CTR']);
    $wg = AdGroup::create(['ad_campaign_id' => $weak->id, 'name' => 'WG']);
    AdKeyword::create(['ad_group_id' => $wg->id, 'phrase' => 'it support', 'match_type' => 'broad', 'is_negative' => false]);
    AdKeyword::create(['ad_group_id' => $wg->id, 'phrase' => 'jobs', 'match_type' => 'broad', 'is_negative' => true]);
    Ad::create(['ad_group_id' => $wg->id, 'name' => 'A', 'headline' => 'IT Support', 'destination_url' => 'https://example.com', 'status' => 'active']);
    $weak->extensions()->create(['kind' => 'callout', 'text' => 'Local team']);
    AdMetric::create(['ad_campaign_id' => $weak->id, 'date' => now()->subDays(3)->toDateString(), 'impressions' => 2000, 'clicks' => 8, 'spend' => 4000, 'conversions' => 1, 'revenue' => 0]);

    $audit = collect($this->actingAs($this->owner)->get(route('ads.campaigns.index'))
        ->assertOk()->viewData('page')['props']['audit']);

    $brokenFindings = $audit->where('campaign', 'Broken')->pluck('finding')->implode(' ');
    expect($brokenFindings)->toContain('has no ads')
        ->and($brokenFindings)->toContain('has no keywords');

    $weakFindings = $audit->where('campaign', 'Weak CTR')->pluck('finding')->implode(' ');
    expect($weakFindings)->toContain('CTR 0.4%')
        ->and($weakFindings)->toContain('2000 impressions')
        ->and($weakFindings)->not->toContain('has no ads');
});

it('recommends bids from recorded metrics with a hard data floor, and the AI advisory sees only real numbers', function () {
    $campaign = ppcCampaign(['daily_budget' => 1000]);
    AdGroup::create(['ad_campaign_id' => $campaign->id, 'name' => 'G', 'bid_strategy' => 'manual_cpc', 'bid_amount' => 250]);
    $advisor = app(BidAdvisor::class);

    // Below the floor: nothing recommended, and the gateway is never called.
    AdMetric::create(['ad_campaign_id' => $campaign->id, 'date' => now()->subDays(2)->toDateString(), 'impressions' => 300, 'clicks' => 5, 'spend' => 900, 'conversions' => 0, 'revenue' => 0]);
    expect($advisor->recommendations($campaign))->toBe(['sufficient' => false, 'items' => []])
        ->and($advisor->aiAdvice($campaign))->toBeNull();

    // Enough data: low CTR, spend without conversions, over-pacing all fire with numbers.
    AdMetric::create(['ad_campaign_id' => $campaign->id, 'date' => now()->subDays(1)->toDateString(), 'impressions' => 4700, 'clicks' => 25, 'spend' => 44100, 'conversions' => 0, 'revenue' => 0]);
    $result = $advisor->recommendations($campaign);
    $rules = collect($result['items'])->pluck('rule');
    $messages = collect($result['items'])->pluck('message')->implode(' ');

    expect($result['sufficient'])->toBeTrue()
        ->and($rules)->toContain('low_ctr')->toContain('spend_without_conversions')->toContain('over_pacing')
        ->and($messages)->toContain('0.6%')          // CTR cited
        ->and($messages)->toContain('450.00')        // spend cited
        ->and($messages)->toContain('10.00');        // budget cited

    // The AI advisory endpoint feeds the same first-party numbers to the gateway.
    $captured = [];
    ppcAiMock('Lower manual CPC on G.', $captured);
    $this->actingAs($this->owner)->post(route('ads.campaigns.bid-advice', $campaign))->assertRedirect();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['feature'])->toBe('ads.bidding')
        ->and($captured[0]['vars']['metrics'])->toContain('clicks 30')
        ->and($captured[0]['vars']['bids'])->toContain('G: manual_cpc @ 2.50');
});

it('drafts ad copy through the gateway as a draft ad with platform character limits enforced', function () {
    $campaign = ppcCampaign();
    $group = AdGroup::create(['ad_campaign_id' => $campaign->id, 'name' => 'Copy group']);
    AdKeyword::create(['ad_group_id' => $group->id, 'phrase' => 'msp dallas', 'match_type' => 'exact', 'is_negative' => false]);

    $captured = [];
    ppcAiMock(implode("\n", [
        'HEADLINE: Dallas MSP You Can Trust',
        'HEADLINE: This headline is far too long for a search ad and must be truncated',
        'DESCRIPTION: 24/7 helpdesk and flat-fee IT support for Dallas businesses.',
    ]), $captured);

    $this->actingAs($this->owner)->post(route('ads.groups.draft-copy', $group))->assertRedirect();

    expect($captured[0]['feature'])->toBe('ads.copy')
        ->and($captured[0]['vars']['keywords'])->toContain('msp dallas')
        ->and($captured[0]['vars']['objective'])->toBe('leads');

    $ad = Ad::where('ad_group_id', $group->id)->firstOrFail();
    expect($ad->status)->toBe('draft')
        ->and($ad->headline)->toBe('Dallas MSP You Can Trust')
        ->and(mb_strlen((string) $ad->headline))->toBeLessThanOrEqual(30)
        ->and($ad->body)->toBe('24/7 helpdesk and flat-fee IT support for Dallas businesses.');
});

it('attributes calls to the campaign through a linked tracking number', function () {
    $campaign = ppcCampaign();
    $number = CallTrackingNumber::create(['phone_number' => '+1-214-555-0177', 'label' => 'Search ads line', 'is_active' => true]);

    $this->actingAs($this->owner)
        ->post(route('ads.campaigns.tracking-number', $campaign), ['call_tracking_number_id' => $number->id])
        ->assertRedirect();

    $number->refresh();
    expect($number->ad_campaign_id)->toBe($campaign->id)
        ->and($number->source)->toBe('google_search')
        ->and($number->campaign)->toBe('Dallas MSP Search');

    Call::create(['call_tracking_number_id' => $number->id, 'from_number' => '+1-972-555-0001', 'to_number' => $number->phone_number, 'direction' => 'inbound', 'duration_seconds' => 240, 'status' => 'completed', 'is_qualified' => true, 'converted' => true, 'occurred_at' => now()]);
    Call::create(['call_tracking_number_id' => $number->id, 'from_number' => '+1-972-555-0002', 'to_number' => $number->phone_number, 'direction' => 'inbound', 'duration_seconds' => 20, 'status' => 'completed', 'is_qualified' => false, 'converted' => false, 'occurred_at' => now()]);

    $calls = $this->actingAs($this->owner)->get(route('ads.campaigns.show', $campaign))
        ->assertOk()->viewData('page')['props']['calls'];
    expect($calls['total'])->toBe(2)
        ->and($calls['qualified'])->toBe(1)
        ->and($calls['converted'])->toBe(1)
        ->and($calls['linked_numbers'][0]['phone_number'])->toBe('+1-214-555-0177');

    // Another tenant's number can never be linked (PPC-020 stays tenant-safe).
    [$otherOrg] = makeOrganization('Other PPC Org');
    app(CurrentOrganization::class)->set($otherOrg);
    $foreign = CallTrackingNumber::create(['phone_number' => '+1-469-555-0100', 'is_active' => true]);
    app(CurrentOrganization::class)->set($this->org);

    $this->actingAs($this->owner)->from(route('ads.campaigns.show', $campaign))
        ->post(route('ads.campaigns.tracking-number', $campaign), ['call_tracking_number_id' => $foreign->id])
        ->assertRedirect()->assertSessionHasErrors('call_tracking_number_id');
});

it('manages ad extensions and creates the draft landing page bridge', function () {
    $campaign = ppcCampaign();

    $this->actingAs($this->owner)
        ->post(route('ads.extensions.store', $campaign), ['kind' => 'sitelink', 'text' => 'Pricing', 'url' => 'https://example.com/pricing'])
        ->assertRedirect();
    // A sitelink without a URL is refused.
    $this->actingAs($this->owner)->from(route('ads.campaigns.show', $campaign))
        ->post(route('ads.extensions.store', $campaign), ['kind' => 'sitelink', 'text' => 'No URL'])
        ->assertRedirect()->assertSessionHasErrors('url');

    $extension = AdExtension::firstOrFail();
    expect($extension->kind)->toBe('sitelink')->and($extension->text)->toBe('Pricing');

    // PPC-018: one click creates a draft landing page keyed to the campaign.
    $this->actingAs($this->owner)->post(route('ads.campaigns.landing-page', $campaign))->assertRedirect();
    $page = LandingPage::firstOrFail();
    expect($page->name)->toBe('Dallas MSP Search landing page')
        ->and($page->status)->toBe('draft')
        ->and($page->slug)->toStartWith('dallas-msp-search-');

    // Cross-tenant: another org's user cannot delete this tenant's extension.
    [$otherOrg, $otherOwner] = makeOrganization('Other Ext Org');
    subscribeOrganization($otherOrg, 'professional');
    $this->actingAs($otherOwner)->delete(route('ads.extensions.destroy', $extension->id))->assertNotFound();

    app(CurrentOrganization::class)->set($this->org);
    $this->actingAs($this->owner)->delete(route('ads.extensions.destroy', $extension->id))->assertRedirect();
    expect(AdExtension::count())->toBe(0);
});
