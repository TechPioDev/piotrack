<?php

namespace App\Http\Controllers\Advertising;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\ContentPiece;
use App\Models\RetargetingAudience;
use App\Services\Advertising\LinkedInAdsService;
use App\Services\Advertising\MetaAdsService;
use App\Services\Advertising\RetargetingService;
use App\Services\Advertising\VideoAdsService;
use App\Services\Sales\AccountService;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * LinkedIn Advertising surfaces (Phase 37). Everything here produces drafts,
 * briefs and imports — nothing talks to the LinkedIn Marketing API (ADR-0006).
 */
class LinkedInAdsController extends Controller
{
    public function __construct(private LinkedInAdsService $linkedin) {}

    /** LIAD-016/017: content piece → draft sponsored-content campaign. */
    public function promoteContent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'content_piece_id' => ['required', 'integer', TenantExists::in('content_pieces')],
        ]);

        $campaign = $this->linkedin->promoteContent(ContentPiece::whereKey($data['content_piece_id'])->firstOrFail());

        return back()->with('status', __('Draft LinkedIn campaign ":name" ready — set budget and targeting under Ads → Campaigns.', ['name' => $campaign->name]));
    }

    /** LIAD-013 / META-002: attach a matched audience (platform-dispatched). */
    public function attachAudience(Request $request, AdCampaign $campaign, MetaAdsService $meta): RedirectResponse
    {
        $data = $request->validate([
            'audience_id' => ['required', 'integer', TenantExists::in('retargeting_audiences')],
        ]);

        $audience = RetargetingAudience::whereKey($data['audience_id'])->firstOrFail();

        if ($campaign->platform === 'meta') {
            $meta->attachAudience($campaign, $audience);

            return back()->with('status', __('Audience attached — upload its export CSV in Ads Manager (Audiences → Customer list) and select it as the custom audience.'));
        }

        // VID-015: YouTube retargeting rides Google Ads Customer Match.
        if ($campaign->platform === 'youtube') {
            app(VideoAdsService::class)->attachAudience($campaign, $audience);

            return back()->with('status', __('Audience attached — upload its google export CSV in Google Ads (Audience Manager → Customer list) and target it on the video campaign.'));
        }

        $this->linkedin->attachAudience($campaign, $audience);

        return back()->with('status', __('Audience attached — upload its export CSV in Campaign Manager and select it as the matched audience.'));
    }

    /** LIAD-014: ABM tier → draft LinkedIn campaign on the committee audience. */
    public function abmCampaign(Request $request, AccountService $accounts, RetargetingService $retargeting): RedirectResponse
    {
        $data = $request->validate(['tier' => ['required', 'integer', 'between:1,3']]);

        $campaign = $this->linkedin->abmCampaign((int) $data['tier'], $accounts, $retargeting);

        return back()->with('status', __('ABM campaign ":name" is ready with the tier committee audience attached.', ['name' => $campaign->name]));
    }

    /** LIAD-015: import Campaign Manager's lead-gen form export CSV. */
    public function importLeads(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'campaign' => ['nullable', 'string', 'max:150'],
        ]);

        $counts = $this->linkedin->importLeads(
            $request->file('file')->getRealPath(),
            $request->string('campaign')->toString() ?: null,
        );

        return back()->with('status', __(':created contacts created, :updated updated, :skipped rows skipped.', $counts));
    }

    /** LIAD-002: the Campaign Manager setup brief. */
    public function brief(AdCampaign $campaign): StreamedResponse
    {
        $brief = $this->linkedin->brief($campaign);

        return response()->streamDownload(function () use ($brief) {
            $out = fopen('php://output', 'w');
            foreach ($brief['rows'] as $row) {
                fputcsv($out, $row, escape: '\\');
            }
            fclose($out);
        }, $brief['filename'], ['Content-Type' => 'text/csv']);
    }
}
