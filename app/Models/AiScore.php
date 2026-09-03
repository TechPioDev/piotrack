<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One advisory AI score (AISA-012/013): history for calibration against real
 * outcomes. Never a source for the deterministic lead_score.
 *
 * @property int $score
 * @property string|null $reason
 * @property string $scoreable_type
 * @property int $scoreable_id
 */
class AiScore extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'scoreable_type', 'scoreable_id', 'score', 'reason',
    ];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function scoreable(): MorphTo
    {
        return $this->morphTo();
    }
}
