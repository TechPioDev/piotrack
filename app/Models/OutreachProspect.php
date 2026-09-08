<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $status
 * @property string|null $placement_url
 * @property string $name
 * @property string|null $domain
 * @property int|null $domain_authority
 */
class OutreachProspect extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'outreach_campaign_id', 'name', 'domain', 'contact_email',
        'status', 'placement_url', 'domain_authority', 'anchor_text', 'link_type',
        'seo_location_id', 'pitch',
    ];

    public function hasPlacement(): bool
    {
        return $this->status === 'won' && $this->placement_url !== null;
    }

    /**
     * @return BelongsTo<OutreachCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'outreach_campaign_id');
    }
}
