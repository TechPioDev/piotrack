<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $position
 * @property string $category
 * @property string|null $lifecycle_stage
 */
class FunnelStage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'funnel_id', 'name', 'position', 'category', 'lifecycle_stage',
    ];

    /**
     * @return BelongsTo<Funnel, $this>
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    /**
     * @return HasMany<FunnelAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(FunnelAsset::class);
    }
}
