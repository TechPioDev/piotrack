<?php

namespace App\Http\Controllers\Advertising;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdExtension;
use App\Models\AdGroup;
use App\Models\CallTrackingNumber;
use App\Models\LandingPage;
use App\Services\Advertising\AdCopywriter;
use App\Services\Advertising\AdExportService;
use App\Services\Advertising\BidAdvisor;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PPC management surfaces beyond campaign CRUD: AI copy drafts, advisory bid
 * guidance, bulk-editor export, extensions, the call-tracking bridge and the
 * landing-page bridge. Nothing here touches a live ad platform (ADR-0006).
 */
class PpcController extends Controller
{
    /** PPC-016: draft RSA-style copy for a group; always lands as a draft ad. */
    public function draftCopy(AdGroup $group, AdCopywriter $copywriter): RedirectResponse
    {
        $result = $copywriter->draft($group);

        $variants = trim(implode("\n", array_merge(
            array_map(fn (string $h) => 'Headline: '.$h, $result['headlines']),
            array_map(fn (string $d) => 'Description: '.$d, $result['descriptions']),
        )));

        return back()
            ->with('status', __('Draft ad created in ":group" — review before activating.', ['group' => $group->name]))
            ->with('ai_result', $variants === '' ? null : $variants);
    }

    /** PPC-014: on-demand AI bid advisory over the campaign's own metrics. */
    public function bidAdvice(AdCampaign $campaign, BidAdvisor $advisor): RedirectResponse
    {
        $advice = $advisor->aiAdvice($campaign);

        if ($advice === null) {
            return back()->with('status', __('Not enough recorded clicks in the last 30 days to advise on bids (floor: :n).', ['n' => BidAdvisor::MIN_CLICKS]));
        }

        return back()->with('ai_result', $advice)->with('status', __('Bid advisory ready — advisory only, nothing was changed.'));
    }

    /** PPC-001/002: Google Ads Editor / Microsoft Ads bulk-compatible CSV. */
    public function export(Request $request, AdCampaign $campaign, AdExportService $exporter): StreamedResponse
    {
        $data = $request->validate(['format' => ['required', Rule::in(AdExportService::FORMATS)]]);

        $export = $exporter->editorCsv($campaign, $data['format']);

        return response()->streamDownload(function () use ($export) {
            $out = fopen('php://output', 'w');
            foreach ($export['rows'] as $row) {
                fputcsv($out, $row, escape: '\\');
            }
            fclose($out);
        }, $export['filename'], ['Content-Type' => 'text/csv']);
    }

    /** PPC-017: add an extension/asset to the campaign. */
    public function storeExtension(Request $request, AdCampaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(AdExtension::KINDS)],
            'text' => ['required', 'string', 'max:90'],
            'url' => ['nullable', 'url', 'max:500', 'required_if:kind,sitelink'],
            'phone' => ['nullable', 'string', 'max:30', 'required_if:kind,call'],
        ]);

        $campaign->extensions()->create($data);

        return back()->with('status', __('Extension added.'));
    }

    public function destroyExtension(AdExtension $extension): RedirectResponse
    {
        $extension->delete();

        return back()->with('status', __('Extension removed.'));
    }

    /** PPC-020: attribute a call tracking number to this campaign. */
    public function attachTrackingNumber(Request $request, AdCampaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'call_tracking_number_id' => ['required', 'integer', TenantExists::in('call_tracking_numbers')],
        ]);

        CallTrackingNumber::whereKey($data['call_tracking_number_id'])->firstOrFail()->update([
            'ad_campaign_id' => $campaign->id,
            'source' => $campaign->platform,
            'campaign' => $campaign->name,
        ]);

        return back()->with('status', __('Tracking number linked — its calls now attribute to this campaign.'));
    }

    /** PPC-018: one-click draft landing page for the campaign's ads to land on. */
    public function createLandingPage(AdCampaign $campaign): RedirectResponse
    {
        $slug = Str::slug($campaign->name).'-'.Str::lower(Str::random(4));

        $page = LandingPage::create([
            'name' => $campaign->name.' landing page',
            'slug' => $slug,
            'headline' => (string) ($campaign->serviceLine()->value('name') ?? $campaign->name),
            'subheadline' => __('Built for the ":name" campaign — edit before publishing.', ['name' => $campaign->name]),
            'body_html' => '',
            'status' => 'draft',
        ]);

        return back()->with('status', __('Draft landing page ":name" created — edit it under Marketing → Landing pages, then use /p/:slug as the ad destination.', ['name' => $page->name, 'slug' => $page->slug]));
    }
}
