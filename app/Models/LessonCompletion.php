<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One user finishing one lesson — progress is computed from these, never
 * stored where it could drift.
 *
 * @property int $lesson_id
 * @property int $user_id
 * @property Carbon $completed_at
 */
class LessonCompletion extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'lesson_id', 'user_id', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
