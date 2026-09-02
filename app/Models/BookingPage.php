<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $availability
 * @property list<array{label: string, required?: bool}>|null $questions
 * @property string|null $ics_feed_token
 * @property string $slug
 * @property string $assignment
 * @property int $duration_minutes
 * @property bool $is_active
 */
class BookingPage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'user_id', 'name', 'slug', 'meeting_type', 'duration_minutes',
        'availability', 'assignment', 'is_active', 'questions', 'ics_feed_token',
    ];

    protected function casts(): array
    {
        return [
            'availability' => 'array',
            'questions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function isPublished(): bool
    {
        return $this->is_active;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
