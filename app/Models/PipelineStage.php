<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stages belong to a (tenant-scoped) pipeline; they carry no organization_id of
 * their own and are always reached through their pipeline.
 */
class PipelineStage extends Model
{
    protected $fillable = ['pipeline_id', 'name', 'sort_order', 'is_won', 'is_lost', 'is_proposal'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
            'is_proposal' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'stage_id');
    }
}
