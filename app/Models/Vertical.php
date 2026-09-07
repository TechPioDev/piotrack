<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An industry vertical (VERT), carrying the compliance framing (VERT-020) and
 * the messaging framework (VERT-016) every page, campaign and sequence
 * targeting it draws on.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $compliance_notes
 * @property array<string, string>|null $messaging
 * @property bool $is_active
 */
class Vertical extends Model
{
    use BelongsToTenant;

    protected $fillable = ['organization_id', 'key', 'name', 'description', 'compliance_notes', 'messaging', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'messaging' => 'array'];
    }

    /**
     * @return HasMany<SitePage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(SitePage::class);
    }
}
