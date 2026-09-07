<?php

declare(strict_types=1);

/**
 * Facebook / Meta Advertising close-out (Phase 38 — META-002/006/008/009/
 * 010/011).
 *
 * Content amplification and video-ad drafts, proof/testimonial campaigns
 * from real reviews (evidence floor enforced), custom-audience attachment
 * with multi-platform coverage, and the lead ads CSV import. Live Meta
 * Marketing API delivery/audience push/form sync stay behind ADR-0006 and
 * are asserted nowhere.
 */

use App\Models\AdCampaign;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\RetargetingAudience;
use App\Models\Review;
use App\Services\Advertising\MetaAdsService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('META Org');
    subscribeOrganization($this->org, 'professional');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('amplifies content and drafts video ads with creative from the piece', function () {
    $guide = ContentPiece::create(['title' => 'Ransomware readiness for small firms', 'slug' => 'ransomware', 'content_type' => 'guide', 'status' => 'published', 'excerpt' => 'The five controls that stop 90% of attacks.', 'url' => 'https://example.com/ransomware']);

    $this->actingAs($this->owner)
        ->post(route('ads.meta.promote-content'), ['content_piece_id' => $guide->id])
        ->assertRedirect();

    $campaign = AdCampaign::where('platform', 'meta')->firstOrFail();
    expect($campaign->status)->toBe('draft')
        ->and($campaign->type)->toBe('amplification')
        ->and($campaign->name)->toStartWith('Amplify:');

    $ad = $campaign->groups()->firstOrFail()->ads()->firstOrFail();
    expect(mb_strlen((string) $ad->headline))->toBeLessThanOrEqual(40)
        ->and($ad->body)->toBe('The five controls that stop 90% of attacks.')
        ->and($ad->destination_url)->toBe('https://example.com/ransomware');

    // Idempotent per piece.
    $this->actingAs($this->owner)->post(route('ads.meta.promote-content'), ['content_piece_id' => $guide->id])->assertRedirect();
    expect(AdCampaign::count())->toBe(1);

    // META-009: video pieces draft as video ads and say where the media lives.
    $webinar = ContentPiece::create(['title' => 'Webinar: co-managed IT in 30 minutes', 'slug' => 'webinar-comanaged', 'content_type' => 'webinar', 'status' => 'published']);
    $this->actingAs($this->owner)->post(route('ads.meta.promote-content'), ['content_piece_id' => $webinar->id])->assertRedirect();

    $video = AdCampaign::where('targeting->content_piece_id', $webinar->id)->firstOrFail();
    $videoAd = $video->groups()->firstOrFail()->ads()->firstOrFail();
    expect($video->type)->toBe('video_ad')
        ->and($video->name)->toStartWith('Video ad:')
        ->and($videoAd->body)->toContain('Attach the video in Ads Manager');
});

it('builds the proof campaign from real reviews and case studies, refusing without evidence', function () {
    // Evidence floor: nothing on file, nothing drafted.
    $this->actingAs($this->owner)->from(route('ads.campaigns.index'))
        ->post(route('ads.meta.proof'))
        ->assertRedirect()->assertSessionHasErrors('proof');
    expect(AdCampaign::count())->toBe(0);

    Review::create(['source' => 'google', 'author_name' => 'Dana R.', 'rating' => 5, 'sentiment' => 'positive', 'body' => 'Response times went from hours to minutes.']);
    Review::create(['source' => 'google', 'author_name' => 'Meh', 'rating' => 2, 'sentiment' => 'negative', 'body' => 'Not for us.']);
    ContentPiece::create(['title' => 'How a 40-seat firm cut downtime 90%', 'slug' => 'cs-downtime', 'content_type' => 'case_study', 'status' => 'published', 'excerpt' => 'From weekly outages to none in one quarter.', 'url' => 'https://example.com/cs']);

    $this->actingAs($this->owner)->post(route('ads.meta.proof'))->assertRedirect();

    $campaign = AdCampaign::where('type', 'proof')->firstOrFail();
    $ads = $campaign->groups()->firstOrFail()->ads()->get();

    expect($campaign->objective)->toBe('conversions')
        ->and($ads)->toHaveCount(2) // the 2-star review never becomes an ad
        ->and($ads->firstWhere('name', 'Review — Dana R.')->headline)->toBe('5/5 — Dana R.')
        ->and($ads->firstWhere('name', 'Review — Dana R.')->body)->toBe('"Response times went from hours to minutes."')
        ->and($ads->first(fn ($a) => str_starts_with((string) $a->name, 'Case study'))->destination_url)->toBe('https://example.com/cs');

    // Idempotent: a second click reuses the draft.
    $this->actingAs($this->owner)->post(route('ads.meta.proof'))->assertRedirect();
    expect(AdCampaign::where('type', 'proof')->count())->toBe(1);
});

it('attaches one audience across Meta and LinkedIn campaigns — multi-platform retargeting', function () {
    $audience = RetargetingAudience::create(['name' => 'Warm visitors', 'source' => 'rules', 'member_count' => 30]);
    $meta = AdCampaign::create(['platform' => 'meta', 'name' => 'FB retarget', 'objective' => 'leads', 'daily_budget' => 1500]);
    $linkedin = AdCampaign::create(['platform' => 'linkedin', 'name' => 'LI retarget', 'objective' => 'leads', 'daily_budget' => 1500]);
    $google = AdCampaign::create(['platform' => 'google_search', 'name' => 'Search', 'objective' => 'leads', 'daily_budget' => 1500]);

    $this->actingAs($this->owner)->post(route('ads.campaigns.audience', $meta), ['audience_id' => $audience->id])->assertRedirect();
    $this->actingAs($this->owner)->post(route('ads.campaigns.audience', $linkedin), ['audience_id' => $audience->id])->assertRedirect();

    expect($meta->refresh()->targeting['audience_id'])->toBe($audience->id)
        ->and($linkedin->refresh()->targeting['audience_id'])->toBe($audience->id);

    // Search campaigns still refuse the attach; Customer Match runs off the
    // same audience export CSV instead.
    $this->actingAs($this->owner)->from(route('ads.campaigns.show', $google))
        ->post(route('ads.campaigns.audience', $google), ['audience_id' => $audience->id])
        ->assertRedirect()->assertSessionHasErrors('audience_id');
});

it('imports Meta lead ads CSV exports with full-name splitting and set-once sources', function () {
    Contact::create(['first_name' => 'Existing', 'email' => 'seen@x.com', 'lead_source' => 'webinar', 'email_opt_in' => true]);

    $csv = implode("\n", [
        'email,full_name,phone_number,campaign_name',
        'lena@newco.example,Lena Marsh,+1-555-0100,MSP proof Q3',
        'seen@x.com,Seen Before,+1-555-0111,MSP proof Q3',
        ',No Email,,MSP proof Q3',
    ]);
    $path = storage_path('framework/meta-leads-'.uniqid().'.csv');
    file_put_contents($path, $csv);

    $counts = app(MetaAdsService::class)->importLeads($path);
    expect($counts)->toBe(['created' => 1, 'updated' => 1, 'skipped' => 1]);

    $lena = Contact::firstWhere('email', 'lena@newco.example');
    expect($lena->first_name)->toBe('Lena')
        ->and($lena->last_name)->toBe('Marsh')
        ->and($lena->phone)->toBe('+1-555-0100')
        ->and($lena->lead_source)->toBe('facebook')
        ->and($lena->campaign)->toBe('MSP proof Q3');

    // First-touch source survives; the blank phone was filled.
    $seen = Contact::firstWhere('email', 'seen@x.com');
    expect($seen->lead_source)->toBe('webinar')
        ->and($seen->phone)->toBe('+1-555-0111');

    @unlink($path);
});
