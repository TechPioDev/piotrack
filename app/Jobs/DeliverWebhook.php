<?php

namespace App\Jobs;

use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * One signed webhook delivery (INTG-009). Retries with backoff; a delivery
 * that keeps failing is recorded on the endpoint (failure_count + last_error)
 * and never disturbs the flow that emitted the event.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $endpointId,
        public string $event,
        public array $payload,
        public string $occurredAt,
    ) {}

    public function handle(): void
    {
        $endpoint = WebhookEndpoint::withoutGlobalScopes()->find($this->endpointId);
        if ($endpoint === null || ! $endpoint->is_active) {
            return;
        }

        $body = json_encode([
            'event' => $this->event,
            'occurred_at' => $this->occurredAt,
            'data' => $this->payload,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        try {
            $response = Http::timeout(8)
                ->withBody($body, 'application/json')
                ->withHeaders([
                    'X-Piotrack-Event' => $this->event,
                    'X-Piotrack-Signature' => hash_hmac('sha256', $body, (string) $endpoint->secret),
                ])->post($endpoint->url);

            if ($response->successful()) {
                $endpoint->forceFill([
                    'last_delivered_at' => now(),
                    'failure_count' => 0,
                    'last_error' => null,
                ])->save();

                return;
            }

            $this->recordFailure($endpoint, 'HTTP '.$response->status());
            $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);
        } catch (\Throwable $e) {
            $this->recordFailure($endpoint, $e->getMessage());
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);
            }
        }
    }

    private function recordFailure(WebhookEndpoint $endpoint, string $error): void
    {
        $endpoint->forceFill([
            'failure_count' => $endpoint->failure_count + 1,
            'last_error' => mb_substr($error, 0, 500),
        ])->save();
    }
}
