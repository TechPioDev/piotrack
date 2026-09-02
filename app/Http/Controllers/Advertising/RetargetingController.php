<?php

namespace App\Http\Controllers\Advertising;

use App\Http\Controllers\Controller;
use App\Models\MarketingList;
use App\Models\RetargetingAudience;
use App\Services\Advertising\RetargetingService;
use App\Services\Marketing\MessageDispatcher;
use App\Support\AuditLogger;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RetargetingController extends Controller
{
    public function __construct(
        private RetargetingService $retargeting,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('advertising/retargeting/index', [
            'audiences' => RetargetingAudience::with('list:id,name')->latest('id')->get()->map(fn (RetargetingAudience $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'source' => $a->source,
                'list' => $a->list?->name,
                'platforms' => $a->platforms ?? [],
                'exclude_converted' => $a->exclude_converted,
                'member_count' => $a->member_count,
            ]),
            'lists' => MarketingList::orderBy('name')->get(['id', 'name'])
                ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'source' => ['required', Rule::in(['list', 'behavior', 'funnel_stage', 'all_contacts'])],
            'marketing_list_id' => ['nullable', TenantExists::in('marketing_lists')],
            'rules' => ['nullable', 'array'],
            'rules.lifecycle_stage' => ['nullable', 'string', 'max:40'],
            'rules.min_lead_score' => ['nullable', 'integer', 'min:0'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['string', 'max:40'],
            'exclude_converted' => ['boolean'],
        ]);

        $audience = RetargetingAudience::create($data);
        $this->retargeting->rebuild($audience);

        $this->audit->log('ads.retargeting.created', context: ['name' => $audience->name, 'source' => $audience->source], resourceType: 'retargeting_audience', resourceId: (string) $audience->id, organizationId: $audience->organization_id);

        return back()->with('status', __('Audience created with :n members.', ['n' => $audience->fresh()->member_count]));
    }

    public function rebuild(RetargetingAudience $audience): RedirectResponse
    {
        $count = $this->retargeting->rebuild($audience);

        return back()->with('status', __('Audience rebuilt: :n members.', ['n' => $count]));
    }

    /**
     * RETG-001..005: the platform's customer-list CSV — hashed emails under
     * the header its Ads UI expects — downloaded for manual upload.
     */
    public function export(Request $request, RetargetingAudience $audience): StreamedResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(array_keys(RetargetingService::EXPORT_PLATFORMS))],
        ]);

        $csv = $this->retargeting->exportCsv($audience, $data['platform']);
        $filename = Str::slug($audience->name).'-'.$data['platform'].'-customer-match.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** RETG-009: the SMS engine driven by this audience. */
    public function sms(Request $request, RetargetingAudience $audience, MessageDispatcher $dispatcher): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:480']]);

        $counts = $this->retargeting->smsReengage($audience, $data['message'], $dispatcher);

        return back()->with('status', __(':sent SMS sent (:targeted targeted, :suppressed suppressed or opted out, :no_phone without a phone).', $counts));
    }

    public function destroy(RetargetingAudience $audience): RedirectResponse
    {
        $audience->delete();

        return back()->with('status', __('Audience removed.'));
    }
}
