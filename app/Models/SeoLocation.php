<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoLocation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'street', 'city', 'region', 'postal_code',
        'country', 'phone', 'website',
        // Multi-location (MLOC): a branch belongs to a sales territory, may map
        // to its own Google Business Profile, and can be deactivated.
        'territory', 'gbp_place_id', 'is_active',
        // BOOK-005: the branch rep who takes territory-matched bookings.
        'owner_id',
    ];

    /**
     * @return HasMany<Citation, $this>
     */
    public function citations(): HasMany
    {
        return $this->hasMany(Citation::class);
    }
}
