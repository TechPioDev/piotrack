<?php

namespace App\Services\Integrations;

use App\Jobs\DeliverWebhook;
use App\Models\WebhookEndpoint;
use App\Support\CurrentOrganization;

/**
 * Fans a domain event out to the tenant's subscribed webhook endpoints
 * (INTG-009). Best-effort by design: emitting is a queued side effect that can
 * never break the business flow that produced the event.
 */
class WebhookDispatcher
{
    public function __construct(private CurrentOrganization $currentOrganization) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $event, array $payload): int
    {
        $organization = $this->currentOrganization->get();
        if ($organization === null) {
            return 0;
        }

        $queued = 0;
        $endpoints = WebhookEndpoint::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)->get();

        foreach ($endpoints as $endpoint) {
            if ($endpoint->wantsEvent($event)) {
                DeliverWebhook::dispatch($endpoint->id, $event, $payload, now()->toIso8601String());
                $queued++;
            }
        }

        return $queued;
    }
}
