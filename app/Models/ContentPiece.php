<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int, string>|null $tags
 * @property Carbon|null $published_at
 * @property int|null $vertical_id
 * @property string $content_type
 * @property string $status
 * @property bool $is_lead_magnet
 * @property int $optimization_score
 */
class ContentPiece extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'title', 'slug', 'content_type', 'format', 'funnel_stage', 'status',
        'author_id', 'excerpt', 'body', 'target_keyword', 'url', 'cta', 'pillar_id', 'tags',
        'is_lead_magnet', 'optimization_score', 'published_at', 'seo_location_id',
        'company_id', 'vertical_id',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_lead_magnet' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<ContentPiece, $this>
     */
    public function pillar(): BelongsTo
    {
        return $this->belongsTo(ContentPiece::class, 'pillar_id');
    }
}
