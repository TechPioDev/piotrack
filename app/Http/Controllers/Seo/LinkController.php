<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\OutreachCampaign;
use App\Services\Seo\BacklinkAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * LINK-001/002/003: the backlink audit page — provider data through the
 * link-index seam (labeled simulated on the fixture driver), first-party
 * verified links, the disavow export, and competitor gaps that feed outreach.
 */
class LinkController extends Controller
{
    public function __construct(private BacklinkAuditService $audit) {}

    public function index(): Response
    {
        return Inertia::render('seo/links', [
            'audit' => $this->audit->audit(),
            // TSEO-026: profile health — anchors, DA, toxic share, top sources.
            'profile' => $this->audit->profile(),
            'gaps' => $this->audit->competitorGaps(),
        ]);
    }

    /** LINK-002: Google-format disavow file for the flagged domains. */
    public function disavow(): StreamedResponse
    {
        $txt = $this->audit->disavowTxt();

        return response()->streamDownload(fn () => print $txt, 'disavow.txt', ['Content-Type' => 'text/plain']);
    }

    /** LINK-003: a gap domain becomes an outreach prospect in one click. */
    public function prospectFromGap(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_domain' => ['required', 'string', 'max:200'],
            'domain_authority' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $campaign = OutreachCampaign::firstOrCreate(
            ['name' => 'Competitor link gaps', 'type' => 'link_building'],
            ['goal' => __('Domains linking to tracked competitors but not to us — surfaced by the backlink audit.')],
        );

        $campaign->prospects()->firstOrCreate(
            ['domain' => $data['source_domain']],
            ['name' => $data['source_domain'], 'status' => 'identified', 'domain_authority' => $data['domain_authority'] ?? null],
        );

        return back()->with('status', __(':domain added to the "Competitor link gaps" outreach campaign.', ['domain' => $data['source_domain']]));
    }
}
