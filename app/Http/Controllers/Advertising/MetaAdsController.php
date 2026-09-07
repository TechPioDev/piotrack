<?php

namespace App\Http\Controllers\Advertising;

use App\Http\Controllers\Controller;
use App\Models\ContentPiece;
use App\Services\Advertising\MetaAdsService;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Meta Advertising surfaces (Phase 38). Drafts and imports only — nothing
 * talks to the Meta Marketing API (ADR-0006).
 */
class MetaAdsController extends Controller
{
    public function __construct(private MetaAdsService $meta) {}

    /** META-006/009: content piece → draft amplification or video-ad campaign. */
    public function promoteContent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'content_piece_id' => ['required', 'integer', TenantExists::in('content_pieces')],
        ]);

        $campaign = $this->meta->promoteContent(ContentPiece::whereKey($data['content_piece_id'])->firstOrFail());

        return back()->with('status', __('Draft Meta campaign ":name" ready — set budget and targeting under Ads → Campaigns.', ['name' => $campaign->name]));
    }

    /** META-008: proof/testimonial campaign from evidence on file. */
    public function proofCampaign(): RedirectResponse
    {
        $campaign = $this->meta->proofCampaign();

        return back()->with('status', __('Proof campaign ":name" drafted from your reviews and case studies.', ['name' => $campaign->name]));
    }

    /** META-010: import Ads Manager's lead ads export CSV. */
    public function importLeads(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'campaign' => ['nullable', 'string', 'max:150'],
        ]);

        $counts = $this->meta->importLeads(
            $request->file('file')->getRealPath(),
            $request->string('campaign')->toString() ?: null,
        );

        return back()->with('status', __(':created contacts created, :updated updated, :skipped rows skipped.', $counts));
    }
}
