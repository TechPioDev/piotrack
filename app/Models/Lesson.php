<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered lesson inside a course.
 *
 * @property int $course_id
 * @property string $title
 * @property string|null $body
 * @property string|null $resource_url
 * @property int $sort_order
 */
class Lesson extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'course_id', 'title', 'body', 'resource_url', 'sort_order',
    ];

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
