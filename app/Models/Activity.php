<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property bool $client_visible
 * @property string|null $title
 * @property string|null $body
 * @property Carbon|null $occurred_at
 */
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const TYPES = ['note', 'task', 'call', 'email', 'meeting'];

    protected $fillable = [
        'organization_id', 'subject_type', 'subject_id', 'type', 'user_id',
        'title', 'body', 'due_at', 'completed_at', 'occurred_at', 'client_visible',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'occurred_at' => 'datetime',
            'client_visible' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
