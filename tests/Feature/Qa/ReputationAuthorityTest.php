<?php

declare(strict_types=1);

/**
 * Reputation & Authority close-out (Phase 17 — REP-005/006/007/012..017/019).
 *
 * Earned placements become typed authority assets automatically, testimonials
 * carry their video, directory profiles get a deterministic optimization
 * checklist, and the proof-first landing page is assembled from real records
 * only — and refuses to exist without them.
 */

use App\Authorization\Role;
use App\Models\AuthorityAsset;
use App\Models\ContentPiece;
use App\Models\LandingPage;
use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use App\Models\Review;
use App\Services\Content\OutreachService;
use App\Services\Content\ReputationService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Authority Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('records a won placement as a typed authority asset, idempotently', function () {
    $campaign = OutreachCampaign::create(['name' => 'Guest posts Q3', 'goal' => 'backlinks', 'status' => 'active']);
    $prospect = OutreachProspect::create([
        'outreach_campaign_id' => $campaign->id, 'name' => 'MSP Insider',
        'domain' => 'mspinsider.example', 'status' => 'pitched',
    ]);

    app(OutreachService::class)->markPlacement($prospect, 'https://mspinsider.example/guest/co-managed-it', 55, 'co-managed IT', 'dofollow', 'article');

    $asset = AuthorityAsset::where('url', 'https://mspinsider.example/guest/co-managed-it')->firstOrFail();
    expect($asset->type)->toBe('article')
        ->and($asset->issuer)->toBe('mspinsider.example')
        ->and($asset->details['domain_authority'])->toBe(55)
        ->and($prospect->fresh()->status)->toBe('won');

    // Marking again does not duplicate the asset.
    app(OutreachService::class)->markPlacement($prospect, 'https://mspinsider.example/guest/co-managed-it', 55, 'co-managed IT', 'dofollow', 'article');
    expect(AuthorityAsset::count())->toBe(1);

    // Other kinds map to their own types; an unknown kind falls back to backlink.
    $quote = OutreachProspect::create(['outreach_campaign_id' => $campaign->id, 'name' => 'TechCrunch', 'domain' => 'tc.example', 'status' => 'pitched']);
    app(OutreachService::class)->markPlacement($quote, 'https://tc.example/quote', null, null, null, 'expert_quote');
    expect(AuthorityAsset::where('url', 'https://tc.example/quote')->value('type'))->toBe('expert_quote');
});

it('records a video testimonial with the review', function () {
    $this->actingAs($this->owner)->post(route('content.reputation.reviews.store'), [
        'source' => 'manual', 'author_name' => 'Dana at Precision Mfg', 'rating' => 5,
        'body' => 'They passed our CMMC audit first try.',
        'video_url' => 'https://videos.example/testimonial-dana',
    ])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    expect(Review::firstOrFail()->video_url)->toBe('https://videos.example/testimonial-dana');
});

it('checks directory profiles deterministically and names each fix', function () {
    AuthorityAsset::create([
        'type' => 'directory_profile', 'name' => 'Clutch profile', 'issuer' => 'Clutch',
        'url' => 'https://clutch.co/profile/authority-org',
        'details' => ['description' => 'Managed IT for manufacturers.', 'services' => ['Managed IT', 'CMMC'], 'review_count' => 12],
    ]);
    AuthorityAsset::create(['type' => 'directory_profile', 'name' => 'UpCity profile', 'issuer' => 'UpCity']);

    $checklists = collect(app(ReputationService::class)->directoryChecklists())->keyBy('directory');

    expect($checklists['Clutch']['ok'])->toBeTrue();

    $upcity = collect($checklists['UpCity']['checks'])->keyBy('key');
    expect($checklists['UpCity']['ok'])->toBeFalse()
        ->and($upcity['url']['ok'])->toBeFalse()
        ->and($upcity['description']['detail'])->toContain('description')
        ->and($upcity['services']['ok'])->toBeFalse()
        ->and($upcity['reviews']['ok'])->toBeFalse();
});

it('drafts the proof page from real records and refuses with zero proof', function () {
    // With nothing on file: refusal as a validation error, no page created.
    $this->actingAs($this->owner)
        ->from(route('content.reputation.index'))
        ->post(route('content.reputation.proof-page'))
        ->assertRedirect(route('content.reputation.index'))
        ->assertSessionHasErrors('proof');

    app(CurrentOrganization::class)->set($this->org);
    expect(LandingPage::count())->toBe(0);

    // Real proof: reviews with text, a logo, a published case study.
    Review::create(['source' => 'google', 'author_name' => 'Sam', 'rating' => 5, 'sentiment' => 'positive', 'body' => 'Response times went from hours to minutes.']);
    Review::create(['source' => 'google', 'author_name' => 'Meh', 'rating' => 2, 'sentiment' => 'negative', 'body' => 'Not for us.']);
    AuthorityAsset::create(['type' => 'logo', 'name' => 'Precision Manufacturing']);
    ContentPiece::create(['title' => 'How Precision passed CMMC', 'slug' => 'precision-cmmc', 'content_type' => 'case_study', 'status' => 'published', 'excerpt' => 'From gap analysis to certification in 90 days.']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->post(route('content.reputation.proof-page'))->assertRedirect()->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($this->org);
    $page = LandingPage::firstOrFail();
    expect($page->status)->toBe('draft')
        ->and($page->body_html)->toContain('Response times went from hours to minutes.')
        ->and($page->body_html)->not->toContain('Not for us.') // 2-star review never becomes "proof"
        ->and($page->body_html)->toContain('Precision Manufacturing')
        ->and($page->body_html)->toContain('How Precision passed CMMC');

    // A second draft gets its own slug.
    $this->actingAs($this->owner)->post(route('content.reputation.proof-page'))->assertRedirect();
    app(CurrentOrganization::class)->set($this->org);
    expect(LandingPage::pluck('slug')->all())->toBe(['proof', 'proof-2']);
});

it('gates the proof endpoint and keeps assets tenant-scoped', function () {
    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)->post(route('content.reputation.proof-page'))->assertForbidden();

    AuthorityAsset::create(['type' => 'article', 'name' => 'Ours', 'url' => 'https://a.example/ours']);
    app(CurrentOrganization::class)->forget();

    [$otherOrg, $otherOwner] = makeOrganization('Other Org');
    subscribeOrganization($otherOrg, 'enterprise');
    app(CurrentOrganization::class)->set($otherOrg);
    expect(AuthorityAsset::count())->toBe(0);
});
