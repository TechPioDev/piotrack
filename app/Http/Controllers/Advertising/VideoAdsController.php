<?php

namespace App\Http\Controllers\Advertising;

use App\Http\Controllers\Controller;
use App\Models\ContentPiece;
use App\Services\Advertising\VideoAdsService;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * VID-014: YouTube video-ad drafts. Draft-only — nothing talks to the Ads
 * API (ADR-0006).
 */
class VideoAdsController extends Controller
{
    public function promoteContent(Request $request, VideoAdsService $video): RedirectResponse
    {
        $data = $request->validate([
            'content_piece_id' => ['required', 'integer', TenantExists::in('content_pieces')],
        ]);

        $campaign = $video->youtubeCampaign(ContentPiece::whereKey($data['content_piece_id'])->firstOrFail());

        return back()->with('status', __('Draft YouTube campaign ":name" ready — upload the video in YouTube Studio, then set budget and targeting under Ads → Campaigns.', ['name' => $campaign->name]));
    }
}
