<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\ExpertProfile;
use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use App\Models\SeoLocation;
use App\Services\Content\OutreachService;
use App\Services\Content\ResearchStoryBuilder;
use App\Support\AuditLogger;
use App\Validation\TenantExists;
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
            // LSEO-015: bindable branches for local link building.
            'locations' => SeoLocation::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // DPR-003: experts available for commentary pitches.
            'experts' => ExpertProfile::where('is_active', true)->orderBy('name')->get(['id', 'name']),
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
                    'pitch' => $p->pitch,
                ])->all(),
            ]),
            'statuses' => self::STATUSES,
        ]);
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $campaign = OutreachCampaign::create($request->validate([
            'name' => ['required', 'string', 'max:150'],
            // POD-001: podcast booking rides the same pitch->placement pipeline.
            'type' => ['required', Rule::in(['digital_pr', 'link_building', 'podcast_booking', 'expert_commentary'])],
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
            // LSEO-015: a prospect worked for one branch builds ITS link profile.
            'seo_location_id' => ['nullable', 'integer', TenantExists::in('seo_locations')],
        ]));

        return back()->with('status', __('Prospect added.'));
    }

    /**
     * DPR-004: seed a digital-PR campaign with the curated MSP-industry
     * publication list — real, named outlets a rep would actually pitch.
     */
    public function seedPublications(OutreachCampaign $campaign): RedirectResponse
    {
        $added = 0;
        foreach (OutreachService::PUBLICATIONS as [$name, $domain]) {
            $prospect = $campaign->prospects()->firstOrCreate(
                ['domain' => $domain],
                ['name' => $name, 'status' => 'identified'],
            );
            $added += $prospect->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('status', __(':n industry publications added as prospects.', ['n' => $added]));
    }

    /**
     * DPR-003: draft an expert-commentary pitch from a REAL expert profile -
     * credentials and topics come from the record, nothing is invented.
     */
    public function draftPitch(Request $request, OutreachProspect $prospect): RedirectResponse
    {
        $data = $request->validate([
            'expert_profile_id' => ['required', 'integer', TenantExists::in('expert_profiles')],
        ]);

        $expert = ExpertProfile::whereKey($data['expert_profile_id'])->firstOrFail();

        $topics = implode(', ', array_slice($expert->knows_about ?? [], 0, 4));
        $credentials = implode(', ', $expert->credentials ?? []);

        $pitch = implode('

', array_filter([
            __('Hi :publication team,', ['publication' => $prospect->name]),
            __('Offering :name (:title) for expert commentary.', ['name' => $expert->name, 'title' => (string) $expert->title]),
            $topics !== '' ? __('Speaks credibly on: :topics.', ['topics' => $topics]) : null,
            $credentials !== '' ? __('Credentials: :credentials.', ['credentials' => $credentials]) : null,
            $expert->bio ? mb_substr((string) $expert->bio, 0, 300) : null,
            __('Available for quotes, background, or a short interview on deadline.'),
        ]));

        $prospect->update(['pitch' => $pitch]);

        return back()->with('status', __('Pitch drafted from the :name profile - review it on the prospect, then send.', ['name' => $expert->name]));
    }

    /**
     * DPR-009: a research-story draft compiled from the tenant's OWN
     * aggregates - data journalism with provenance, never invented numbers.
     */
    public function researchStory(ResearchStoryBuilder $builder): RedirectResponse
    {
        $piece = $builder->draft();

        return back()->with('status', __('Research story ":title" drafted - add narrative under Content, then pitch it with this campaign.', ['title' => $piece->title]));
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
