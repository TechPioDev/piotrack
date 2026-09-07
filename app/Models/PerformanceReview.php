<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PERF-011: a stored ROI review for one performance-agreement period —
 * attainment, revenue, spend and the guarded ROI ratio at generation time.
 *
 * @property array<string, mixed> $data
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 */
class PerformanceReview extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'performance_agreement_id', 'period_start', 'period_end', 'data',
    ];

    protected function casts(): array
    {
        return ['data' => 'array', 'period_start' => 'date', 'period_end' => 'date'];
    }

    /**
     * @return BelongsTo<PerformanceAgreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(PerformanceAgreement::class, 'performance_agreement_id');
    }
}
