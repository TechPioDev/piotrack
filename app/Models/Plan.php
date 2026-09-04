<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, int>|null $overage_prices
 */
class Plan extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'is_public', 'is_active', 'is_custom_priced', 'sort_order',
        'overage_prices',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'is_custom_priced' => 'boolean',
            'overage_prices' => 'array',
        ];
    }

    /**
     * @return HasMany<PlanPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /**
     * @return HasMany<PlanEntitlement, $this>
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(PlanEntitlement::class);
    }

    public function priceFor(string $interval, string $currency = 'USD'): ?PlanPrice
    {
        return $this->prices->firstWhere(fn (PlanPrice $p) => $p->interval === $interval && $p->currency === $currency);
    }
}
