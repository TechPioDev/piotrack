<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One capture of a competitor's public site content (CINT-005), with the
 * diff against the capture before it.
 *
 * @property int $competitor_id
 * @property list<array{url: string, title: string, hash: string}>|null $pages
 * @property int $pages_count
 * @property list<string>|null $new_pages
 * @property list<string>|null $changed_pages
 * @property list<string>|null $removed_pages
 */
class CompetitorSnapshot extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'competitor_id', 'pages', 'pages_count',
        'new_pages', 'changed_pages', 'removed_pages',
    ];

    protected function casts(): array
    {
        return [
            'pages' => 'array',
            'new_pages' => 'array',
            'changed_pages' => 'array',
            'removed_pages' => 'array',
        ];
    }
}
