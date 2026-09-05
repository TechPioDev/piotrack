<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $targeting
 * @property string $status
 * @property string|null $bid_strategy
 * @property int|null $bid_amount
 */
class AdGroup extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'ad_campaign_id', 'name', 'status', 'bid_strategy', 'bid_amount', 'targeting',
    ];

    protected function casts(): array
    {
        return ['targeting' => 'array'];
    }

    /**
     * @return BelongsTo<AdCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    /**
     * @return HasMany<Ad, $this>
     */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    /**
     * @return HasMany<AdKeyword, $this>
     */
    public function keywords(): HasMany
    {
        return $this->hasMany(AdKeyword::class);
    }
}
