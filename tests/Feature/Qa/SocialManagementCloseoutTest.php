<?php

declare(strict_types=1);

/**
 * Social Media Management close-out (Phase 46 — SOC-009/020/021/022/023/024).
 *
 * Template graphics generated from real brand assets, the engagement inbox
 * (logged comments/DMs + chat waiting + unanswered reviews) with response
 * metrics, and brand monitoring/listening through the provider seam with
 * transparent keyword sentiment.
 */

use App\Models\BrandProfile;
use App\Models\ChatConversation;
use App\Models\ChatWidget;
use App\Models\ListeningTerm;
use App\Models\Review;
use App\Models\SocialInteraction;
use App\Models\SocialPost;
use App\Services\Content\SocialEngagementService;
use App\Services\Content\SocialGraphicService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('SocMgmt Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('generates the branded social graphic from the post and real brand assets', function () {
    BrandProfile::first()?->update([
        'legal_name' => 'Acme Managed IT',
        'palette' => ['primary' => '#123456', 'accent' => '#ff8800'],
    ]) ?? BrandProfile::create(['legal_name' => 'Acme Managed IT', 'palette' => ['primary' => '#123456', 'accent' => '#ff8800']]);

    $post = SocialPost::create(['channel' => 'linkedin', 'type' => 'update', 'body' => 'Ransomware recovery in under four hours — here is how our image-based backups make that possible.', 'status' => 'draft']);

    $svg = app(SocialGraphicService::class)->svg($post);
    expect($svg)->toContain('#123456')          // brand primary
        ->and($svg)->toContain('#ff8800')       // brand accent
        ->and($svg)->toContain('Acme Managed IT')
        ->and($svg)->toContain('Ransomware recovery');

    // The download endpoint serves it as an SVG attachment.
    $response = $this->actingAs($this->owner)->get(route('content.social.graphic', $post));
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('image/svg+xml');
});

it('runs the engagement inbox: logging, triage, response metrics, and the real adjacent queues', function () {
    // Adjacent queues the platform actually holds.
    $widget = ChatWidget::create(['name' => 'W', 'is_active' => true]);
    ChatConversation::create(['chat_widget_id' => $widget->id, 'status' => 'waiting']);
    Review::create(['source' => 'google', 'author_name' => 'R', 'rating' => 2, 'sentiment' => 'negative', 'body' => 'Slow response.']);

    // SOC-021: log a comment — sentiment is heuristic and transparent.
    $this->actingAs($this->owner)->post(route('content.social.interactions.store'), [
        'network' => 'linkedin', 'kind' => 'comment', 'author' => 'ops-director',
        'body' => 'Terrible experience with our current provider — how do you handle outage response?',
    ])->assertRedirect();

    $interaction = SocialInteraction::firstOrFail();
    expect($interaction->status)->toBe('open')
        ->and($interaction->sentiment)->toBe('negative');

    $inbox = app(SocialEngagementService::class)->inbox();
    expect($inbox['interactions'])->toHaveCount(1)
        ->and($inbox['chat_waiting'])->toBe(1)
        ->and($inbox['reviews_unresponded'])->toBe(1);

    // Triage: replied stamps the response time and clears the queue.
    $this->actingAs($this->owner)->patch(route('content.social.interactions.status', $interaction), ['status' => 'replied'])->assertRedirect();
    $inbox = app(SocialEngagementService::class)->inbox();
    expect($inbox['interactions'])->toHaveCount(0)
        ->and($inbox['metrics']['replied'])->toBe(1)
        ->and($inbox['metrics']['avg_response_hours'])->not->toBeNull();
});

it('monitors the brand and listening terms through the seam with per-network volume and sentiment', function () {
    BrandProfile::first()?->update(['legal_name' => 'Acme Managed IT'])
        ?? BrandProfile::create(['legal_name' => 'Acme Managed IT']);

    $this->actingAs($this->owner)->post(route('content.social.terms.store'), ['term' => 'co-managed IT'])->assertRedirect();
    $this->actingAs($this->owner)->post(route('content.social.terms.store'), ['term' => 'co-managed IT'])->assertRedirect(); // idempotent
    expect(ListeningTerm::count())->toBe(1);

    $monitor = app(SocialEngagementService::class)->monitor();

    expect($monitor['provider'])->toBe('fixture')
        ->and($monitor['terms'])->toContain('Acme Managed IT')   // the brand is always tracked
        ->and($monitor['terms'])->toContain('co-managed IT')
        ->and($monitor['mentions'])->not->toBeEmpty()
        ->and($monitor['by_network'])->not->toBeEmpty()
        ->and($monitor['negative'] + $monitor['positive'])->toBeGreaterThan(0);

    // Deterministic: the same term always yields the same mentions.
    expect(app(SocialEngagementService::class)->monitor()['mentions'])->toBe($monitor['mentions']);

    // The social page carries the whole layer and labels the fixture driver.
    $props = $this->actingAs($this->owner)->get(route('content.social.index'))->assertOk()->viewData('page')['props'];
    expect($props['monitoring']['provider'])->toBe('fixture')
        ->and($props['engagement'])->toHaveKey('metrics');
});

it('keeps the sentiment heuristic transparent and the tenant fence intact', function () {
    $service = app(SocialEngagementService::class);
    expect($service->sentiment('This provider is the worst — constant outage after outage'))->toBe('negative')
        ->and($service->sentiment('Love the fast onboarding, would recommend'))->toBe('positive')
        ->and($service->sentiment('We migrated our tenant last week'))->toBe('neutral');

    // Another tenant's interaction is unreachable for triage.
    $mine = SocialInteraction::create(['network' => 'x', 'kind' => 'dm', 'body' => 'ours']);
    [$otherOrg, $otherOwner] = makeOrganization('Other Soc Org');
    subscribeOrganization($otherOrg, 'enterprise');
    $this->actingAs($otherOwner)->patch(route('content.social.interactions.status', $mine), ['status' => 'dismissed'])->assertNotFound();
    expect($mine->refresh()->status)->toBe('open');
});
