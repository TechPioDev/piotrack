<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int, string>|null $tags
 * @property string $type
 */
class SalesAsset extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'type', 'title', 'description', 'content', 'url', 'tags',
        'vertical_id', 'service_line_id',
    ];

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }
}
