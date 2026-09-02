<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $reviewed_at
 * @property int $rating
 * @property string $source
 * @property string $sentiment
 * @property bool $responded
 * @property string|null $video_url
 */
class Review extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'source', 'provider', 'author_name', 'rating', 'body', 'url',
        'video_url', 'sentiment', 'responded', 'response', 'response_published_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'responded' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }
}
