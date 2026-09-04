<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One add-on attached to a subscription (BILL-005): its price bills each
 * renewal, its grants boost the plan's limits through the central
 * Entitlements resolver.
 *
 * @property string $code
 * @property string $name
 * @property int $price
 * @property array<string, int> $grants
 * @property int $quantity
 */
class SubscriptionAddon extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'subscription_id', 'code', 'name', 'price', 'grants', 'quantity',
    ];

    protected function casts(): array
    {
        return ['grants' => 'array', 'price' => 'integer', 'quantity' => 'integer'];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
