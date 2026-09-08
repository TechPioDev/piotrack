<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\MarketingList;
use App\Models\Vertical;
use App\Services\Marketing\CampaignService;
use App\Support\AuditLogger;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function __construct(
        private CampaignService $campaigns,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('marketing/campaigns/index', [
            'campaigns' => Campaign::with('list:id,name')->latest('id')->get()->map(fn (Campaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'channel' => $c->channel,
                'status' => $c->status,
                'list' => $c->list?->name,
                'stat_sent' => $c->stat_sent,
                'stat_opened' => $c->stat_opened,
                'stat_clicked' => $c->stat_clicked,
            ]),
            'lists' => MarketingList::orderBy('name')->get(['id', 'name'])
                ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name]),
        ]);
    }

    public function show(Campaign $campaign): Response
    {
        return Inertia::render('marketing/campaigns/show', [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'channel' => $campaign->channel,
                'type' => $campaign->type,
                'subject' => $campaign->subject,
                'subject_b' => $campaign->subject_b,
                'from_name' => $campaign->from_name,
                'from_email' => $campaign->from_email,
                'body_html' => $campaign->body_html,
                'body_text' => $campaign->body_text,
                'status' => $campaign->status,
                'marketing_list_id' => $campaign->marketing_list_id,
                'vertical_id' => $campaign->vertical_id,
                'video_url' => $campaign->video_url,
                'video_title' => $campaign->video_title,
                // EMAIL-015/019: split results + post-send conversions.
                'ab' => app(CampaignService::class)->abResults($campaign),
                'conversions' => app(CampaignService::class)->conversions($campaign),
                'stats' => [
                    'recipients' => $campaign->stat_recipients,
                    'sent' => $campaign->stat_sent,
                    'opened' => $campaign->stat_opened,
                    'clicked' => $campaign->stat_clicked,
                    'bounced' => $campaign->stat_bounced,
                    'unsubscribed' => $campaign->stat_unsubscribed,
                ],
            ],
            'lists' => MarketingList::orderBy('name')->get(['id', 'name'])
                ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name]),
            // VERT-018: bindable vertical for the coverage report.
            'verticals' => Vertical::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campaign = Campaign::create($this->validateData($request));
        $this->audit->log('campaign.created', context: ['name' => $campaign->name], resourceType: 'campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return redirect()->route('marketing.campaigns.show', $campaign->id)->with('status', __('Campaign created.'));
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_if($campaign->isSent(), 403, __('A sent campaign cannot be edited.'));
        $campaign->update($this->validateData($request));
        // AUDIT-004: campaign changes are data events.
        $this->audit->log('campaign.updated', context: ['name' => $campaign->name], resourceType: 'campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return back()->with('status', __('Campaign saved.'));
    }

    public function send(Campaign $campaign): RedirectResponse
    {
        $this->campaigns->send($campaign);
        $this->audit->log('campaign.sent', context: ['name' => $campaign->name], resourceType: 'campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return back()->with('status', __('Campaign sent.'));
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->audit->log('campaign.deleted', context: ['name' => $campaign->name], resourceType: 'campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);
        $campaign->delete();

        return redirect()->route('marketing.campaigns.index')->with('status', __('Campaign deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'channel' => ['required', Rule::in(['email', 'sms'])],
            'type' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:200'],
            // EMAIL-015: the B subject arms the A/B split at send time.
            'subject_b' => ['nullable', 'string', 'max:200'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'from_email' => ['nullable', 'email', 'max:200'],
            'body_html' => ['nullable', 'string', 'max:50000'],
            'body_text' => ['nullable', 'string', 'max:5000'],
            'marketing_list_id' => ['nullable', TenantExists::in('marketing_lists')],
            // VERT-018: vertical the campaign targets; coverage joins on it.
            'vertical_id' => ['nullable', 'integer', TenantExists::in('verticals')],
            // VID-017: the video block appended at send, click-tracked.
            'video_url' => ['nullable', 'url', 'starts_with:https://', 'max:500'],
            'video_title' => ['nullable', 'string', 'max:200'],
        ]);
    }
}
