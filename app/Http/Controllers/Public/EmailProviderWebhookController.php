<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CampaignRecipient;
use App\Models\Organization;
use App\Models\OutboundMessage;
use App\Services\Marketing\SuppressionService;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PRIV-006: provider-agnostic ESP bounce/complaint webhook. Any ESP that can
 * POST {type, email} with the shared secret plugs in; a bounce or complaint
 * suppresses the address for every tenant that has messaged it (the dispatch
 * pipeline already honors suppressions) and marks the matching recipient rows.
 * With no secret configured the endpoint refuses everything — it can never be
 * an open suppression-injection door.
 */
class EmailProviderWebhookController extends Controller
{
    public function __invoke(Request $request, SuppressionService $suppressions, CurrentOrganization $current): JsonResponse
    {
        $secret = (string) config('services.email_webhook.secret', '');
        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Webhook-Secret'))) {
            abort(403);
        }

        $data = $request->validate([
            'type' => ['required', 'in:bounce,complaint'],
            'email' => ['required', 'email', 'max:255'],
        ]);
        $address = mb_strtolower(trim($data['email']));

        // Every tenant that has messaged this address gets the suppression.
        $organizationIds = OutboundMessage::withoutGlobalScope('tenant')->where('channel', 'email')->where('address', $address)
            ->pluck('organization_id')
            ->merge(CampaignRecipient::withoutGlobalScope('tenant')->where('address', $address)->pluck('organization_id'))
            ->unique();

        $suppressed = 0;
        foreach ($organizationIds as $organizationId) {
            $organization = Organization::find($organizationId);
            if ($organization === null) {
                continue;
            }

            $current->set($organization);
            try {
                $suppressions->suppress('email', $address, $data['type']);
                CampaignRecipient::where('address', $address)->whereNull('unsubscribed_at')
                    ->where('status', 'sent')->update(['status' => 'bounced', 'error' => $data['type']]);
                $suppressed++;
            } finally {
                $current->forget();
            }
        }

        return response()->json(['ok' => true, 'tenants' => $suppressed]);
    }
}
