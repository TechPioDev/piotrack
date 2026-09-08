<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $paid_at
 * @property Carbon|null $due_at
 * @property string $status
 * @property string $number
 * @property int $total
 * @property int|null $subscription_id
 * @property int|null $organization_id
 */
class Invoice extends Model
{
    protected $fillable = [
        'organization_id', 'subscription_id', 'provider', 'provider_id', 'number', 'status',
        'currency', 'subtotal', 'discount', 'tax', 'total', 'amount_paid',
        'period_start', 'period_end', 'due_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
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
     * @return HasMany<InvoiceLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }
}
