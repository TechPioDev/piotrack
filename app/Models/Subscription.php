<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $ends_at
 */
class Subscription extends Model
{
    public const ACTIVE_STATES = ['trialing', 'active', 'past_due'];

    protected $fillable = [
        'organization_id', 'plan_id', 'coupon_id', 'provider', 'provider_id', 'status',
        'interval', 'quantity', 'trial_ends_at', 'current_period_start', 'current_period_end',
        'cancel_at_period_end', 'canceled_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'cancel_at_period_end' => 'boolean',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<SubscriptionAddon, $this>
     */
    public function addons(): HasMany
    {
        return $this->hasMany(SubscriptionAddon::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATES, true);
    }

    public function onTrial(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled' || $this->cancel_at_period_end;
    }
}
