<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One raw tracker event (VINT): a pageview or an identify, behind the
 * Visitor rollup.
 */
class VisitorEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['organization_id', 'visitor_id', 'type', 'path', 'title'];

    /** @return BelongsTo<Visitor, $this> */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }
}
