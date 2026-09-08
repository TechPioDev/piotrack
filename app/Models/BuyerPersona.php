<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * STRAT-007: one buyer persona. The narrative fields are rep-authored; the
 * research page renders them beside evidence computed from real records
 * (buying roles, won-deal titles, visitor questions) so personas are
 * developed against data, not invented in a vacuum.
 *
 * @property int $id
 * @property string $name
 * @property string|null $role_title
 * @property string|null $seniority
 * @property string|null $goals
 * @property string|null $pains
 * @property string|null $channels
 * @property string|null $objections
 * @property string|null $notes
 */
class BuyerPersona extends Model
{
    use BelongsToTenant;

    public const SENIORITIES = ['owner', 'executive', 'director', 'manager', 'practitioner'];

    protected $fillable = [
        'organization_id', 'name', 'role_title', 'seniority',
        'goals', 'pains', 'channels', 'objections', 'notes',
    ];
}
