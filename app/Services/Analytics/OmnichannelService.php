<?php

namespace App\Services\Analytics;

use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\AiVisibilityCheck;
use App\Models\AuthorityAsset;
use App\Models\Citation;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Keyword;
use App\Models\OutboundMessage;
use App\Models\OutreachProspect;
use App\Models\RetargetingAudience;
use App\Models\SeoLocation;
use App\Models\SocialPost;

/**
 * Omnichannel marketing view (OMNI). A unified, per-channel performance rollup
 * across every acquisition surface the platform already runs, plus the unified
 * prospect journey (one contact's cross-channel touchpoints + lifecycle). A
 * channel with no data is reported inactive with a zero metric — never faked.
 */
class OmnichannelService
{
    public function __construct(private AttributionService $attribution) {}

    /**
     * Per-channel summary — one row per register channel (OMNI-001..015), each
     * computed from the tenant's own records: a stable key, a label, an active
     * flag, one primary metric, an optional secondary detail, and a link into
     * the module that manages the channel (the module pages are the
     * drill-downs — that is the coordination). Per-network numbers are what the
     * PLATFORM did on that channel; live network-side insights need the
     * connectors and are never invented here.
     *
     * @return list<array{channel: string, label: string, active: bool, metric: string, value: int, detail: string|null, href: string}>
     */
    public function channels(): array
    {
        $seoTracked = Keyword::where('is_tracked', true)->count();
        $emailSent = OutboundMessage::where('channel', 'email')->whereNotNull('sent_at')->count();
        $smsSent = OutboundMessage::where('channel', 'sms')->whereNotNull('sent_at')->count();
        $contentPublished = ContentPiece::where('status', 'published')->count();
        $videoPublished = ContentPiece::where('status', 'published')->whereIn('content_type', ['video', 'webinar'])->count();
        $retargeting = (int) RetargetingAudience::sum('member_count');
        $aiMentions = AiVisibilityCheck::where('mentioned', true)->count();

        // Google Maps = the GBP surface Local SEO coordinates.
        $mapsLocations = SeoLocation::whereNotNull('gbp_place_id')->count();
        $consistentCitations = Citation::where('status', 'consistent')->count();

        // PR = the earned-media pipeline: placements actually won, never pitched-only.
        $placements = OutreachProspect::where('status', 'won')->whereNotNull('placement_url')->count();
        $authorityAssets = AuthorityAsset::count();

        // Per-network activity from the tenant's own posting and campaign records.
        $posts = SocialPost::where('status', 'published')
            ->selectRaw('channel, count(*) as n')->groupBy('channel')->pluck('n', 'channel');
        $adsByPlatform = $this->adClicksByPlatform();

        $social = fn (string $network): int => (int) ($posts[$network] ?? 0);
        $clicks = fn (string $platform): int => $adsByPlatform[$platform]['clicks'] ?? 0;
        $campaigns = fn (string $platform): int => $adsByPlatform[$platform]['campaigns'] ?? 0;
        $adDetail = fn (string $platform): ?string => $campaigns($platform) > 0 ? $campaigns($platform).' campaigns' : null;
        $clickDetail = fn (string $platform): ?string => $clicks($platform) > 0 ? $clicks($platform).' ad clicks' : null;

        return [
            ['channel' => 'seo', 'label' => 'SEO', 'active' => $seoTracked > 0, 'metric' => 'tracked keywords', 'value' => $seoTracked, 'detail' => null, 'href' => '/seo/keywords'],
            ['channel' => 'google_maps', 'label' => 'Google Maps', 'active' => $mapsLocations > 0 || $consistentCitations > 0, 'metric' => 'locations on Maps', 'value' => $mapsLocations, 'detail' => $consistentCitations > 0 ? $consistentCitations.' consistent citations' : null, 'href' => '/seo/local'],
            ['channel' => 'google_ads', 'label' => 'Google Ads', 'active' => $clicks('google_search') > 0 || $campaigns('google_search') > 0, 'metric' => 'ad clicks', 'value' => $clicks('google_search'), 'detail' => $adDetail('google_search'), 'href' => '/ads/campaigns'],
            ['channel' => 'microsoft_ads', 'label' => 'Microsoft Ads', 'active' => $clicks('microsoft') > 0 || $campaigns('microsoft') > 0, 'metric' => 'ad clicks', 'value' => $clicks('microsoft'), 'detail' => $adDetail('microsoft'), 'href' => '/ads/campaigns'],
            ['channel' => 'linkedin', 'label' => 'LinkedIn', 'active' => $social('linkedin') > 0 || $clicks('linkedin') > 0, 'metric' => 'posts published', 'value' => $social('linkedin'), 'detail' => $clickDetail('linkedin'), 'href' => '/content/social'],
            ['channel' => 'facebook', 'label' => 'Facebook', 'active' => $social('facebook') > 0 || $clicks('meta') > 0, 'metric' => 'posts published', 'value' => $social('facebook'), 'detail' => $clickDetail('meta'), 'href' => '/content/social'],
            ['channel' => 'x', 'label' => 'X / Twitter', 'active' => $social('x') > 0, 'metric' => 'posts published', 'value' => $social('x'), 'detail' => null, 'href' => '/content/social'],
            ['channel' => 'youtube', 'label' => 'YouTube', 'active' => $social('youtube') > 0 || $clicks('youtube') > 0, 'metric' => 'posts published', 'value' => $social('youtube'), 'detail' => $clickDetail('youtube'), 'href' => '/content/social'],
            ['channel' => 'email', 'label' => 'Email', 'active' => $emailSent > 0, 'metric' => 'sent', 'value' => $emailSent, 'detail' => null, 'href' => '/marketing/campaigns'],
            ['channel' => 'sms', 'label' => 'SMS', 'active' => $smsSent > 0, 'metric' => 'sent', 'value' => $smsSent, 'detail' => null, 'href' => '/marketing/campaigns'],
            ['channel' => 'video', 'label' => 'Video', 'active' => $videoPublished > 0, 'metric' => 'video pieces published', 'value' => $videoPublished, 'detail' => null, 'href' => '/content/pieces'],
            ['channel' => 'content', 'label' => 'Content', 'active' => $contentPublished > 0, 'metric' => 'published', 'value' => $contentPublished, 'detail' => null, 'href' => '/content/pieces'],
            ['channel' => 'retargeting', 'label' => 'Retargeting', 'active' => $retargeting > 0, 'metric' => 'audience', 'value' => $retargeting, 'detail' => null, 'href' => '/ads/retargeting'],
            ['channel' => 'ai_search', 'label' => 'AI Search', 'active' => $aiMentions > 0, 'metric' => 'mentions', 'value' => $aiMentions, 'detail' => null, 'href' => '/ai/visibility'],
            ['channel' => 'pr', 'label' => 'Public Relations', 'active' => $placements > 0 || $authorityAssets > 0, 'metric' => 'placements earned', 'value' => $placements, 'detail' => $authorityAssets > 0 ? $authorityAssets.' authority assets' : null, 'href' => '/content/outreach'],
        ];
    }

    /**
     * Clicks + campaign counts per ad platform, from the tenant's own
     * campaigns and their recorded metrics.
     *
     * @return array<string, array{campaigns: int, clicks: int}>
     */
    private function adClicksByPlatform(): array
    {
        $out = [];

        foreach (AdCampaign::query()->get(['id', 'platform']) as $campaign) {
            $out[$campaign->platform] ??= ['campaigns' => 0, 'clicks' => 0];
            $out[$campaign->platform]['campaigns']++;
        }

        if ($out === []) {
            return $out;
        }

        $clicks = AdMetric::query()
            ->join('ad_campaigns', 'ad_campaigns.id', '=', 'ad_metrics.ad_campaign_id')
            ->selectRaw('ad_campaigns.platform as platform, sum(ad_metrics.clicks) as clicks')
            ->groupBy('ad_campaigns.platform')
            ->pluck('clicks', 'platform');

        foreach ($clicks as $platform => $sum) {
            $out[$platform] ??= ['campaigns' => 0, 'clicks' => 0];
            $out[$platform]['clicks'] = (int) $sum;
        }

        return $out;
    }

    /**
     * The unified prospect journey for one contact: chronological cross-channel
     * touchpoints + current lifecycle stage (OMNI-016/017/018).
     *
     * @return array<string, mixed>
     */
    public function journey(Contact $contact): array
    {
        return [
            'contact_id' => $contact->id,
            'lifecycle_stage' => $contact->lifecycle_stage,
            'lead_score' => $contact->lead_score,
            'first_touch' => $this->attribution->firstTouch($contact),
            'last_touch' => $this->attribution->lastTouch($contact),
            'touchpoints' => $this->attribution->touchpoints($contact),
        ];
    }
}
