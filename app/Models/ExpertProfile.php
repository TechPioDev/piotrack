<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A person entity in the organization's knowledge graph (LLMO-007/008): the
 * engineer, vCIO or founder whose credentials make the MSP's expertise
 * machine-readable. Rendered as schema.org Person with hasCredential,
 * knowsAbout and sameAs.
 *
 * @property int $id
 * @property string $name
 * @property string|null $title
 * @property string|null $bio
 * @property list<string>|null $credentials
 * @property list<string>|null $knows_about
 * @property list<string>|null $same_as
 * @property bool $is_active
 */
class ExpertProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'title', 'bio',
        'credentials', 'knows_about', 'same_as', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'array',
            'knows_about' => 'array',
            'same_as' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
