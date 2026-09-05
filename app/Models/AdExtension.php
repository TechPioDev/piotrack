<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PPC-017: an ad extension/asset attached to a campaign — sitelink, callout,
 * structured snippet, or call extension. Exported with the campaign and
 * checked by the account audit.
 *
 * @property string $kind
 * @property string $text
 * @property string|null $url
 * @property string|null $phone
 */
class AdExtension extends Model
{
    use BelongsToTenant;

    public const KINDS = ['sitelink', 'callout', 'structured_snippet', 'call'];

    protected $fillable = [
        'organization_id', 'ad_campaign_id', 'kind', 'text', 'url', 'phone',
    ];

    /**
     * @return BelongsTo<AdCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }
}
