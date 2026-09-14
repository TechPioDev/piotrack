<?php

declare(strict_types=1);

/**
 * UI-P3: every domain dashboard tile carries context, and that context is
 * honest. Deltas compare only events on the timestamp that records the event
 * (sent_at, published_at, an ad metric's date), windows are bounded exactly,
 * ratios and stocks are never delta'd, and nothing leaks across tenants.
 */

use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\OutboundMessage;
use App\Services\Analytics\PeriodComparison;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Context Org');
    subscribeOrganization($this->org, 'enterprise');
});

function contextProps($test, string $route): array
{
    return $test->actingAs($test->owner)->get(route($route))->assertOk()->viewData('page')['props'];
}

it('bounds the current and previous 30-day windows exactly', function () {
    app(CurrentOrganization::class)->set($this->org);
    // Day 0 and day 29 are the current window; days 30 and 59 the previous one.
    foreach (['now' => 0, 'edge' => 29, 'prev' => 30, 'prev-edge' => 59, 'outside' => 60] as $name => $daysAgo) {
        Contact::create(['first_name' => $name, 'email' => "{$name}@window.test"])
            ->forceFill(['created_at' => now()->subDays($daysAgo)->startOfDay()->addHour()])->save();
    }

    expect((new PeriodComparison)->count(Contact::query(), 'created_at'))
        ->toBe(['value' => 2, 'previous' => 2, 'delta_pct' => 0.0]);
    app(CurrentOrganization::class)->forget();
});

it('counts messages by when they were sent, not when they were queued', function () {
    app(CurrentOrganization::class)->set($this->org);
    $contact = Contact::create(['first_name' => 'Reader', 'email' => 'reader@ctx.test']);
    $message = fn (array $attributes) => OutboundMessage::create(array_merge([
        'contact_id' => $contact->id, 'channel' => 'email', 'address' => 'reader@ctx.test', 'token' => 'tok'.uniqid(),
    ], $attributes));

    // Queued long ago but sent this week: counts now. Never sent: never counts.
    $message(['status' => 'sent', 'sent_at' => now()->subDays(2), 'opened_at' => now()->subDay()])
        ->forceFill(['created_at' => now()->subDays(80)])->save();
    $message(['status' => 'sent', 'sent_at' => now()->subDays(40)]);
    $message(['status' => 'queued', 'sent_at' => null]);
    app(CurrentOrganization::class)->forget();

    $flows = contextProps($this, 'marketing.dashboard')['flows'];

    expect($flows['messages_sent'])->toBe(['value' => 1, 'previous' => 1, 'delta_pct' => 0.0])
        ->and($flows['messages_opened'])->toBe(1)
        ->and($flows['new_contacts']['value'])->toBe(1);
});

it('compares ad volumes window against window and leaves ratios undelta\'d', function () {
    app(CurrentOrganization::class)->set($this->org);
    $campaign = AdCampaign::create(['platform' => 'google_search', 'name' => 'Ctx', 'status' => 'active']);
    $metric = fn (int $daysAgo, int $spend, int $clicks) => AdMetric::create([
        'ad_campaign_id' => $campaign->id, 'date' => now()->subDays($daysAgo)->toDateString(),
        'impressions' => $clicks * 10, 'clicks' => $clicks, 'spend' => $spend, 'conversions' => 1, 'revenue' => $spend * 2,
    ]);
    $metric(3, 30000, 30);   // current window
    $metric(45, 10000, 10);  // previous window
    $metric(75, 99999, 99);  // outside both
    app(CurrentOrganization::class)->forget();

    $props = contextProps($this, 'ads.dashboard');

    expect($props['flows']['spend'])->toBe(['value' => 30000, 'previous' => 10000, 'delta_pct' => 200.0])
        ->and($props['flows']['clicks'])->toBe(['value' => 30, 'previous' => 10, 'delta_pct' => 200.0])
        ->and(array_keys($props['flows']))->toBe(['spend', 'impressions', 'clicks', 'conversions', 'revenue'])
        ->and($props['kpi']['spend'])->toBe(30000); // the KPI row is the same window the deltas describe
});

it('counts content by publication, so drafts never inflate publishing', function () {
    app(CurrentOrganization::class)->set($this->org);
    $piece = fn (string $slug, string $status, $publishedAt) => ContentPiece::create([
        'title' => $slug, 'slug' => $slug, 'content_type' => 'article', 'status' => $status, 'published_at' => $publishedAt,
    ]);
    $piece('fresh', 'published', now()->subDays(5));
    $piece('older', 'published', now()->subDays(35));
    $piece('draft', 'draft', null);
    app(CurrentOrganization::class)->forget();

    $props = contextProps($this, 'content.dashboard');

    expect($props['flows']['pieces_published'])->toBe(['value' => 1, 'previous' => 1, 'delta_pct' => 0.0])
        ->and($props['stats']['published'])->toBe(2)
        ->and($props['stats']['pieces'])->toBe(3);
});

it('shows no audit score when there are no audits, rather than a zero', function () {
    $stats = contextProps($this, 'seo.dashboard')['stats'];

    expect($stats['latest_score'])->toBeNull()
        ->and($stats['audits'])->toBe(0);
});

it('keeps every dashboard flow inside the tenant', function () {
    [$other] = makeOrganization('Other Org');
    app(CurrentOrganization::class)->set($other);
    Contact::create(['first_name' => 'Elsewhere', 'email' => 'elsewhere@ctx.test']);
    ContentPiece::create(['title' => 'Theirs', 'slug' => 'theirs', 'content_type' => 'article', 'status' => 'published', 'published_at' => now()]);
    app(CurrentOrganization::class)->forget();

    expect(contextProps($this, 'marketing.dashboard')['flows']['new_contacts']['value'])->toBe(0)
        ->and(contextProps($this, 'content.dashboard')['flows']['pieces_published']['value'])->toBe(0)
        ->and(contextProps($this, 'sales.dashboard')['flows']['meetings_booked']['value'])->toBe(0)
        ->and(contextProps($this, 'ai.dashboard')['flows']['requests']['value'])->toBe(0);
});
