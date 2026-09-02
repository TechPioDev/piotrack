<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $achieved_on
 * @property string $type
 * @property array<string, mixed>|null $details
 */
class AuthorityAsset extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'type', 'name', 'issuer', 'url', 'image_url', 'achieved_on', 'details',
    ];

    protected function casts(): array
    {
        return ['achieved_on' => 'date', 'details' => 'array'];
    }
}
