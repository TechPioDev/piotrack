<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A bounded technical-SEO site crawl (TSEO-002) and its findings.
 *
 * @property string $start_url
 * @property int $pages_crawled
 * @property int $issues_count
 * @property array<string, mixed>|null $report
 */
class SiteCrawl extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'start_url', 'pages_crawled', 'issues_count', 'report',
    ];

    protected function casts(): array
    {
        return ['report' => 'array'];
    }
}
