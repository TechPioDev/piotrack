<?php

namespace App\Services\Advertising;

use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\AdGroup;
use App\Models\ContentPiece;
use App\Models\RetargetingAudience;
use App\Support\AuditLogger;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * VID-014/015: YouTube video-ad drafts from video content pieces (the Meta
 * half shipped in P38), and audience attachment for youtube campaigns —
 * YouTube retargeting runs on Google Ads Customer Match, and the audience's
 * google export CSV (P16) is the upload. Live delivery stays behind the Ads
 * API (ADR-0006).
 */
class VideoAdsService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * VID-014: a video-typed piece becomes a draft YouTube campaign. The
     * creative states the video itself lives in YouTube/Google Ads — the
     * platform never pretends to hold the media.
     */
    public function youtubeCampaign(ContentPiece $piece): AdCampaign
    {
        if (! in_array($piece->content_type, MetaAdsService::VIDEO_TYPES, true)) {
            throw ValidationException::withMessages(['content_piece_id' => __('YouTube ads promote video-shaped content (video, webinar, podcast, interview).')]);
        }

        $existing = AdCampaign::where('platform', 'youtube')
            ->where('targeting->content_piece_id', $piece->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $campaign = AdCampaign::create([
            'platform' => 'youtube',
            'name' => 'Video ad: '.mb_substr($piece->title, 0, 120),
            'type' => 'video_ad',
            'objective' => 'awareness',
            'status' => 'draft',
            'daily_budget' => 0,
            'targeting' => ['content_piece_id' => $piece->id, 'content_type' => $piece->content_type],
        ]);

        $group = AdGroup::create(['ad_campaign_id' => $campaign->id, 'name' => 'Video creative', 'status' => 'draft']);

        Ad::create([
            'ad_group_id' => $group->id,
            'name' => mb_substr($piece->title, 0, 100),
            'headline' => Str::limit($piece->title, 90, ''),
            'body' => __('Upload the video in YouTube Studio / Google Ads and link it here. Intro: :intro', ['intro' => Str::limit((string) ($piece->excerpt ?: $piece->title), 80, '')]),
            'destination_url' => $piece->url,
            'status' => 'draft',
        ]);

        $this->audit->log('ads.youtube.video_promoted', context: ['piece' => $piece->id, 'campaign' => $campaign->id], resourceType: 'ad_campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return $campaign;
    }

    /**
     * VID-015: point a YouTube campaign at a retargeting audience — the
     * google customer-match CSV is the Google Ads upload.
     */
    public function attachAudience(AdCampaign $campaign, RetargetingAudience $audience): AdCampaign
    {
        if ($campaign->platform !== 'youtube') {
            throw ValidationException::withMessages(['audience_id' => __('This attach path serves YouTube campaigns.')]);
        }

        $campaign->update(['targeting' => array_merge($campaign->targeting ?? [], [
            'audience_id' => $audience->id,
            'audience_name' => $audience->name,
        ])]);

        $this->audit->log('ads.youtube.audience_attached', context: ['audience' => $audience->id], resourceType: 'ad_campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return $campaign;
    }
}
