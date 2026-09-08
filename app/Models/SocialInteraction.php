<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * SOC-020/021: one logged social interaction — a comment, DM or mention a
 * rep captured (or promoted from the listening feed) — triaged through the
 * engagement inbox. Live ingestion stays channel-API-gated.
 *
 * @property string $network
 * @property string $kind
 * @property string|null $author
 * @property string|null $url
 * @property string $body
 * @property string|null $sentiment
 * @property string $status
 * @property string $source
 * @property Carbon|null $replied_at
 */
class SocialInteraction extends Model
{
    use BelongsToTenant;

    public const KINDS = ['comment', 'dm', 'mention'];

    public const NETWORKS = ['linkedin', 'facebook', 'x', 'youtube', 'instagram', 'reddit', 'other'];

    protected $fillable = [
        'organization_id', 'network', 'kind', 'author', 'url', 'body',
        'sentiment', 'status', 'source', 'replied_at',
    ];

    protected function casts(): array
    {
        return ['replied_at' => 'datetime'];
    }
}
