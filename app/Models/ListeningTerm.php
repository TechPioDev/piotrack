<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * SOC-023: a term the social-listening seam tracks (beside the brand name,
 * which is always tracked).
 *
 * @property string $term
 * @property bool $is_active
 */
class ListeningTerm extends Model
{
    use BelongsToTenant;

    protected $fillable = ['organization_id', 'term', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
