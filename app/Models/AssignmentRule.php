<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CRM-025: one lead-routing rule — when a new lead's field matches the value,
 * it is assigned to the named owner. Rules run in position order, first match
 * wins; no match falls through to the tested least-loaded round-robin.
 *
 * @property int $id
 * @property int $position
 * @property string $field
 * @property string $value
 * @property int $user_id
 */
class AssignmentRule extends Model
{
    use BelongsToTenant;

    public const FIELDS = ['lead_source', 'email_domain', 'lifecycle_stage'];

    protected $fillable = ['organization_id', 'position', 'field', 'value', 'user_id'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
