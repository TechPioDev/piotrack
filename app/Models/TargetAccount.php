<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $tier
 * @property string $status
 * @property int $account_score
 */
class TargetAccount extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'company_id', 'tier', 'status', 'account_score', 'notes', 'vertical_id',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
