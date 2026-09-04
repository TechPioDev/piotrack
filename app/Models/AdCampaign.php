<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed>|null $targeting
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string $platform
 * @property string $status
 * @property int $daily_budget
 */
class AdCampaign extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'platform', 'name', 'type', 'objective', 'status',
        'daily_budget', 'total_budget', 'start_date', 'end_date', 'targeting', 'external_id',
        'seo_location_id', 'service_line_id',
    ];

    protected function casts(): array
    {
        return [
            'targeting' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * @return HasMany<AdGroup, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(AdGroup::class);
    }

    /**
     * @return HasMany<AdMetric, $this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(AdMetric::class);
    }
}
