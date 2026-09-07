<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $duration_seconds
 * @property string $status
 * @property string|null $source
 * @property string|null $campaign
 * @property int $score
 * @property bool $is_qualified
 * @property bool $converted
 * @property string|null $recording_url
 * @property string|null $transcript
 * @property Carbon|null $occurred_at
 */
class Call extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'call_tracking_number_id', 'contact_id', 'owner_id',
        'from_number', 'to_number', 'direction', 'duration_seconds', 'status',
        'source', 'campaign', 'score', 'is_qualified', 'converted',
        'recording_url', 'transcript', 'summary', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'score' => 'integer',
            'is_qualified' => 'boolean',
            'converted' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CallTrackingNumber, $this>
     */
    public function trackingNumber(): BelongsTo
    {
        return $this->belongsTo(CallTrackingNumber::class, 'call_tracking_number_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
