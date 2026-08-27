<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One platform asset attached to a funnel stage (FUNL). The asset itself lives
 * in its own module's table; asset_type names which one (allow-listed in
 * FunnelService::TYPES).
 */
class FunnelAsset extends Model
{
    use BelongsToTenant;

    protected $fillable = ['organization_id', 'funnel_stage_id', 'asset_type', 'asset_id'];

    /** @return BelongsTo<FunnelStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(FunnelStage::class, 'funnel_stage_id');
    }
}
