<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Organization;
use App\Services\Marketing\CampaignService;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a campaign off the request cycle. Re-establishes tenant context from the
 * campaign's organization before touching the DB (a queued worker has no
 * ambient tenant). The service is idempotent per recipient, so a retry does not
 * double-send — and the job is unique per campaign (JOBS-003), so duplicate
 * dispatches collapse instead of queueing a second send.
 */
class SendCampaignJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $campaignId, public int $organizationId) {}

    public function uniqueId(): string
    {
        return 'send-campaign:'.$this->campaignId;
    }

    public function handle(CampaignService $campaigns, CurrentOrganization $current): void
    {
        $organization = Organization::find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $current->set($organization);

        $campaign = Campaign::find($this->campaignId);

        if ($campaign === null || $campaign->isSent()) {
            return;
        }

        $campaigns->send($campaign);
    }
}
