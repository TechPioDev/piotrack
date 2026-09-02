<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use App\Services\Content\OutreachService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OutreachController extends Controller
{
    private const STATUSES = ['identified', 'contacted', 'replied', 'won', 'lost'];

    public function __construct(
        private OutreachService $outreach,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('content/outreach/index', [
            'campaigns' => OutreachCampaign::with('prospects')->latest('id')->get()->map(fn (OutreachCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'goal' => $c->goal,
                'status' => $c->status,
                'rollup' => $this->outreach->rollup($c),
                'prospects' => $c->prospects->map(fn (OutreachProspect $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'domain' => $p->domain,
                    'status' => $p->status,
                    'placement_url' => $p->placement_url,
                    'domain_authority' => $p->domain_authority,
                    'anchor_text' => $p->anchor_text,
                ])->all(),
            ]),
            'statuses' => self::STATUSES,
        ]);
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $campaign = OutreachCampaign::create($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['digital_pr', 'link_building'])],
            'goal' => ['nullable', 'string', 'max:1000'],
        ]));

        $this->audit->log('content.outreach.created', context: ['name' => $campaign->name, 'type' => $campaign->type], resourceType: 'outreach_campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return back()->with('status', __('Outreach campaign created.'));
    }

    public function storeProspect(Request $request, OutreachCampaign $campaign): RedirectResponse
    {
        $campaign->prospects()->create($request->validate([
            'name' => ['required', 'string', 'max:200'],
            'domain' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'domain_authority' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]));

        return back()->with('status', __('Prospect added.'));
    }

    public function prospectStatus(Request $request, OutreachProspect $prospect): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(self::STATUSES)]]);
        $this->outreach->setStatus($prospect, $data['status']);

        return back()->with('status', __('Prospect updated.'));
    }

    public function markPlacement(Request $request, OutreachProspect $prospect): RedirectResponse
    {
        $data = $request->validate([
            'placement_url' => ['required', 'url', 'max:2048'],
            'domain_authority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'anchor_text' => ['nullable', 'string', 'max:200'],
            'link_type' => ['nullable', Rule::in(['dofollow', 'nofollow'])],
            'placement_kind' => ['nullable', Rule::in(OutreachService::PLACEMENT_KINDS)],
        ]);

        $this->outreach->markPlacement($prospect, $data['placement_url'], $data['domain_authority'] ?? null, $data['anchor_text'] ?? null, $data['link_type'] ?? null, $data['placement_kind'] ?? 'backlink');

        return back()->with('status', __('Placement recorded.'));
    }

    public function destroyCampaign(OutreachCampaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return back()->with('status', __('Campaign removed.'));
    }

    public function destroyProspect(OutreachProspect $prospect): RedirectResponse
    {
        $prospect->delete();

        return back()->with('status', __('Prospect removed.'));
    }
}
