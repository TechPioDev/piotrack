<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $experiment_id
 * @property string $name
 * @property bool $is_control
 * @property int $impressions
 * @property int $conversions
 * @property array<string, string>|null $content
 */
class ExperimentVariant extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'experiment_id', 'name', 'is_control', 'impressions', 'conversions', 'content',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_control' => 'boolean',
            'impressions' => 'integer',
            'conversions' => 'integer',
        ];
    }

    /**
     * Conversion rate as a ratio (0..1), divisor-guarded.
     */
    public function conversionRate(): float
    {
        return $this->impressions > 0 ? $this->conversions / $this->impressions : 0.0;
    }

    /**
     * @return BelongsTo<Experiment, $this>
     */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }
}
