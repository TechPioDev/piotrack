<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ChatAgentPresenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Whether an agent is available to take a live chat right now.
 *
 * @property Carbon|null $last_seen_at
 */
class ChatAgentPresence extends Model
{
    /** @use HasFactory<ChatAgentPresenceFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'chat_agent_presence';

    public const STATUSES = ['online', 'away', 'busy', 'offline'];

    /** An agent that stops sending heartbeats is treated as away after this long. */
    public const STALE_AFTER_MINUTES = 5;

    protected $fillable = [
        'organization_id',
        'user_id',
        'status',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Online, and actually still there. A browser that closed without signing
     * out leaves the row "online" forever, so the heartbeat is what counts.
     */
    public function isAvailable(): bool
    {
        return $this->status === 'online'
            && $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    /** The status to show, downgrading a stale "online" to away. */
    public function effectiveStatus(): string
    {
        if ($this->status === 'online' && ! $this->isAvailable()) {
            return 'away';
        }

        return $this->status;
    }
}
