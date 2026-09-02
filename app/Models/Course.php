<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A topic-tagged training course (TRAIN-001..007): the materials behind the
 * human-delivered consulting and training engagements.
 *
 * @property string $title
 * @property string $topic
 * @property string|null $description
 * @property bool $is_published
 */
class Course extends Model
{
    use BelongsToTenant;

    public const TOPICS = ['marketing', 'seo', 'sales', 'executive'];

    protected $fillable = [
        'organization_id', 'title', 'topic', 'description', 'is_published',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }
}
