<?php

namespace App\Services\Advertising;

use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\AdGroup;
use App\Models\ContentPiece;
use App\Models\RetargetingAudience;
use App\Models\Review;
use App\Support\AuditLogger;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Facebook / Meta Advertising workflows that need no Marketing API
 * (META-002/006/008/009/010/011): content amplification and video-ad drafts,
 * proof/testimonial campaigns from real reviews, custom-audience attachment,
 * and the lead ads CSV import (Ads Manager's own export). Live delivery,
 * audience push and lead-form sync stay behind ADR-0006.
 */
class MetaAdsService
{
    /** Meta feed-ad creative limits (enforced server-side). */
    public const HEADLINE_LIMIT = 40;

    public const PRIMARY_TEXT_LIMIT = 125;

    /** Content types that promote as video ads (META-009). */
    public const VIDEO_TYPES = ['video', 'webinar', 'podcast', 'interview'];

    /** Ads Manager lead export headers → contact fields. */
    private const LEAD_ALIASES = [
        'email' => ['email', 'email address'],
        'full_name' => ['full name', 'full_name'],
        'first_name' => ['first name', 'first_name'],
        'last_name' => ['last name', 'last_name'],
        'title' => ['job title', 'job_title'],
        'phone' => ['phone number', 'phone_number', 'phone'],
        'campaign' => ['campaign_name', 'campaign name', 'campaign'],
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * META-006/009: a content piece becomes a draft Meta campaign. Video-typed
     * pieces draft as video ads — the creative says the video file itself is
     * attached in Ads Manager; the platform never pretends to hold the media.
     */
    public function promoteContent(ContentPiece $piece): AdCampaign
    {
        $existing = AdCampaign::where('platform', 'meta')
            ->where('targeting->content_piece_id', $piece->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $isVideo = in_array($piece->content_type, self::VIDEO_TYPES, true);

        $campaign = AdCampaign::create([
            'platform' => 'meta',
            'name' => ($isVideo ? 'Video ad: ' : 'Amplify: ').mb_substr($piece->title, 0, 120),
            'type' => $isVideo ? 'video_ad' : 'amplification',
            'objective' => 'awareness',
            'status' => 'draft',
            'daily_budget' => 0,
            'targeting' => ['content_piece_id' => $piece->id, 'content_type' => $piece->content_type],
        ]);

        $group = AdGroup::create([
            'ad_campaign_id' => $campaign->id,
            'name' => $isVideo ? 'Video creative' : 'Feed creative',
            'status' => 'draft',
        ]);

        Ad::create([
            'ad_group_id' => $group->id,
            'name' => mb_substr($piece->title, 0, 100),
            'headline' => Str::limit($piece->title, self::HEADLINE_LIMIT, ''),
            'body' => $isVideo
                ? __('Attach the video in Ads Manager. Intro: :intro', ['intro' => Str::limit((string) ($piece->excerpt ?: $piece->title), 80, '')])
                : Str::limit((string) ($piece->excerpt ?: $piece->title), self::PRIMARY_TEXT_LIMIT, ''),
            'destination_url' => $piece->url,
            'status' => 'draft',
        ]);

        $this->audit->log('ads.meta.content_promoted', context: ['piece' => $piece->id, 'campaign' => $campaign->id, 'video' => $isVideo], resourceType: 'ad_campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return $campaign;
    }

    /**
     * META-008: proof/testimonial campaign from evidence on file — 4-star-plus
     * reviews with text (top 3 become ads quoting author and body) and
     * published case studies. Refuses when no proof exists: the same evidence
     * floor as the reputation proof page — nothing is ever invented.
     */
    public function proofCampaign(): AdCampaign
    {
        $reviews = Review::where('rating', '>=', 4)->whereNotNull('body')->where('body', '!=', '')
            ->orderByDesc('rating')->orderByDesc('reviewed_at')->limit(3)->get();
        $caseStudies = ContentPiece::where('content_type', 'case_study')->where('status', 'published')->limit(2)->get();

        if ($reviews->isEmpty() && $caseStudies->isEmpty()) {
            throw ValidationException::withMessages(['proof' => __('No proof on file yet — collect 4-star-plus reviews or publish a case study first.')]);
        }

        $existing = AdCampaign::where('platform', 'meta')->where('type', 'proof')->first();
        if ($existing !== null) {
            return $existing;
        }

        $campaign = AdCampaign::create([
            'platform' => 'meta',
            'name' => 'Proof: reviews & case studies',
            'type' => 'proof',
            'objective' => 'conversions',
            'status' => 'draft',
            'daily_budget' => 0,
            'targeting' => ['proof_reviews' => $reviews->count(), 'proof_case_studies' => $caseStudies->count()],
        ]);

        $group = AdGroup::create(['ad_campaign_id' => $campaign->id, 'name' => 'Social proof', 'status' => 'draft']);

        foreach ($reviews as $review) {
            Ad::create([
                'ad_group_id' => $group->id,
                'name' => 'Review — '.mb_substr((string) $review->author_name, 0, 60),
                'headline' => Str::limit($review->rating.'/5 — '.$review->author_name, self::HEADLINE_LIMIT, ''),
                'body' => Str::limit('"'.$review->body.'"', self::PRIMARY_TEXT_LIMIT, ''),
                'status' => 'draft',
            ]);
        }

        foreach ($caseStudies as $piece) {
            Ad::create([
                'ad_group_id' => $group->id,
                'name' => 'Case study — '.mb_substr($piece->title, 0, 60),
                'headline' => Str::limit($piece->title, self::HEADLINE_LIMIT, ''),
                'body' => Str::limit((string) ($piece->excerpt ?: $piece->title), self::PRIMARY_TEXT_LIMIT, ''),
                'destination_url' => $piece->url,
                'status' => 'draft',
            ]);
        }

        $this->audit->log('ads.meta.proof_campaign', context: ['reviews' => $reviews->count(), 'case_studies' => $caseStudies->count()], resourceType: 'ad_campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return $campaign;
    }

    /**
     * META-002/011: point a Meta campaign at a retargeting audience. The meta
     * customer-match CSV export is the Ads Manager upload; the same audience
     * exports for google and linkedin too — multi-platform retargeting from
     * one member list.
     */
    public function attachAudience(AdCampaign $campaign, RetargetingAudience $audience): AdCampaign
    {
        if ($campaign->platform !== 'meta') {
            throw ValidationException::withMessages(['audience_id' => __('Custom audiences attach to Meta campaigns only.')]);
        }

        $campaign->update(['targeting' => array_merge($campaign->targeting ?? [], [
            'audience_id' => $audience->id,
            'audience_name' => $audience->name,
        ])]);

        $this->audit->log('ads.meta.audience_attached', context: ['audience' => $audience->id], resourceType: 'ad_campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return $campaign;
    }

    /**
     * META-010: import Ads Manager's lead ads export CSV.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importLeads(string $path, ?string $fallbackCampaign = null): array
    {
        $counts = app(AdLeadImporter::class)->import($path, self::LEAD_ALIASES, 'facebook', $fallbackCampaign);

        $this->audit->log('ads.meta.leads_imported', context: $counts);

        return $counts;
    }
}
