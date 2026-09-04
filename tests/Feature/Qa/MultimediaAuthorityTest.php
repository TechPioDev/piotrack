<?php

declare(strict_types=1);

/**
 * Podcast / Multimedia Authority close-out (Phase 28 — POD-001/004/009).
 *
 * Podcast appearances ride the earned-media pipeline as a typed asset; webinar
 * promotion and social clips schedule through the tested social pipeline,
 * linked to their source piece. YouTube video upload stays API-gated.
 */

use App\Models\AuthorityAsset;
use App\Models\ContentPiece;
use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use App\Models\SocialPost;
use App\Services\Content\MultimediaPromotion;
use App\Services\Content\OutreachService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Multimedia Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('records a won podcast pitch as a typed appearance asset', function () {
    $campaign = OutreachCampaign::create(['name' => 'Podcast circuit Q4', 'type' => 'podcast_booking', 'goal' => 'authority', 'status' => 'active']);
    $show = OutreachProspect::create(['outreach_campaign_id' => $campaign->id, 'name' => 'MSP Voices', 'domain' => 'mspvoices.example', 'status' => 'pitched']);

    app(OutreachService::class)->markPlacement($show, 'https://mspvoices.example/ep/42', null, null, null, 'podcast_appearance');

    $asset = AuthorityAsset::where('url', 'https://mspvoices.example/ep/42')->firstOrFail();
    expect($asset->type)->toBe('podcast_appearance')
        ->and($asset->issuer)->toBe('mspvoices.example')
        ->and($show->refresh()->hasPlacement())->toBeTrue();

    // The campaign type is accepted end-to-end at the endpoint too.
    app(CurrentOrganization::class)->forget();
    $this->actingAs($this->owner)->post(route('content.outreach.store'), [
        'name' => 'Podcast circuit 2', 'type' => 'podcast_booking',
    ])->assertRedirect()->assertSessionHas('status');
    app(CurrentOrganization::class)->set($this->org);
});

it('schedules one announcement per network for a webinar, linked to the piece', function () {
    $webinar = ContentPiece::create(['title' => 'Ransomware readiness webinar', 'slug' => 'rr-webinar', 'content_type' => 'webinar', 'status' => 'published', 'url' => 'https://example.test/register', 'excerpt' => 'What auditors actually check.']);

    $posts = app(MultimediaPromotion::class)->promote($webinar);

    expect($posts)->toHaveCount(4)
        ->and(collect($posts)->pluck('channel')->sort()->values()->all())->toBe(['facebook', 'linkedin', 'x', 'youtube']);

    foreach ($posts as $post) {
        expect($post->content_piece_id)->toBe($webinar->id)
            ->and($post->status)->toBe('scheduled')
            ->and($post->scheduled_at)->not->toBeNull()
            ->and($post->body)->toContain('Ransomware readiness webinar')
            ->and($post->body)->toContain('https://example.test/register')
            ->and($post->body)->toContain('Save your seat');
    }

    // Staggered, not simultaneous.
    $times = collect($posts)->pluck('scheduled_at')->map(fn ($t) => $t->timestamp)->unique();
    expect($times)->toHaveCount(4);
});

it('staggers typed clip slots across networks and refuses non-multimedia pieces', function () {
    $podcast = ContentPiece::create(['title' => 'Managed IT podcast ep. 7', 'slug' => 'pod-7', 'content_type' => 'podcast', 'status' => 'published']);

    $clips = app(MultimediaPromotion::class)->clips($podcast, 5);
    expect($clips)->toHaveCount(5);

    foreach ($clips as $i => $clip) {
        expect($clip->type)->toBe('clip')
            ->and($clip->content_piece_id)->toBe($podcast->id)
            ->and($clip->media_url)->toBeNull() // the tenant attaches the cut
            ->and($clip->scheduled_at->isSameDay(now()->addDays($i + 1)))->toBeTrue();
    }

    // Rotation covers multiple networks.
    expect(collect($clips)->pluck('channel')->unique()->count())->toBeGreaterThan(2);

    // An article has no clips to cut.
    $article = ContentPiece::create(['title' => 'Plain article', 'slug' => 'plain-a', 'content_type' => 'article', 'status' => 'published']);
    expect(fn () => app(MultimediaPromotion::class)->clips($article))->toThrow(RuntimeException::class);
});

it('wires promote and clips endpoints, refusing them for non-multimedia pieces', function () {
    $video = ContentPiece::create(['title' => 'Client onboarding video', 'slug' => 'onb-video', 'content_type' => 'video', 'status' => 'published']);
    $article = ContentPiece::create(['title' => 'Article', 'slug' => 'art-x', 'content_type' => 'article', 'status' => 'published']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->post(route('content.pieces.promote', $video->id))
        ->assertRedirect()->assertSessionHas('status');
    $this->actingAs($this->owner)->post(route('content.pieces.clips', $video->id), ['count' => 2])
        ->assertRedirect()->assertSessionHas('status');
    $this->actingAs($this->owner)->post(route('content.pieces.promote', $article->id))
        ->assertSessionHasErrors('piece');

    app(CurrentOrganization::class)->set($this->org);
    expect(SocialPost::where('content_piece_id', $video->id)->count())->toBe(6) // 4 promos + 2 clips
        ->and(SocialPost::where('content_piece_id', $article->id)->count())->toBe(0);
});
