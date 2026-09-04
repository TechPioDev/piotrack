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

    protected $fillable = ['organization_id', 'visitor_id', 'type', 'path', 'title', 'x_pct', 'y_pct'];

    protected function casts(): array
    {
        return ['x_pct' => 'integer', 'y_pct' => 'integer'];
    }

    /** @return BelongsTo<Visitor, $this> */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }
}
