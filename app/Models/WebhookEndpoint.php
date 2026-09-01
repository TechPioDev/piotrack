<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * An outbound webhook subscription (INTG-009): where to POST signed event
 * payloads, and which events the receiver asked for (empty = all).
 */
class WebhookEndpoint extends Model
{
    use BelongsToTenant;

    /** Events the platform emits. `ping` is reserved for the test button. */
    public const EVENTS = ['lead.captured', 'booking.created', 'deal.won', 'alert.fired'];

    protected $fillable = [
        'organization_id', 'url', 'secret', 'events', 'is_active',
        'failure_count', 'last_delivered_at', 'last_error',
    ];

    /** @var list<string> */
    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'last_delivered_at' => 'datetime',
        ];
    }

    public function wantsEvent(string $event): bool
    {
        $events = $this->events ?? [];

        return $events === [] || in_array($event, $events, true);
    }
}
