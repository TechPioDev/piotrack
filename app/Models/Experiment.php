<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $status
 * @property int|null $winning_variant_id
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property-read Collection<int, ExperimentVariant> $variants
 */
class Experiment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'type', 'hypothesis', 'primary_metric',
        'status', 'winning_variant_id', 'site_page_id', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ExperimentVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ExperimentVariant::class);
    }
}
