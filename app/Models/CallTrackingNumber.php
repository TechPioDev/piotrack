<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $phone_number
 * @property string|null $source
 * @property string|null $campaign
 * @property bool $is_active
 */
class CallTrackingNumber extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'phone_number', 'label', 'source', 'campaign',
        'provider', 'provider_id', 'is_active', 'ad_campaign_id',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<Call, $this>
     */
    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }
}
