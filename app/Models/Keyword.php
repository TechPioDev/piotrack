<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $phrase
 * @property string $intent
 * @property string|null $mapped_url
 * @property string|null $cluster
 * @property bool $is_tracked
 * @property int|null $current_position
 */
class Keyword extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'phrase', 'intent', 'type', 'search_volume', 'difficulty',
        'mapped_url', 'cluster', 'is_tracked', 'current_position', 'location',
    ];

    protected function casts(): array
    {
        return ['is_tracked' => 'boolean'];
    }

    /**
     * @return HasMany<KeywordRanking, $this>
     */
    public function rankings(): HasMany
    {
        return $this->hasMany(KeywordRanking::class)->latest('checked_at');
    }
}
