<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $sent_at
 * @property string $channel
 * @property string $status
 * @property int|null $vertical_id
 * @property int $stat_recipients
 * @property int $stat_sent
 * @property int $stat_opened
 * @property int $stat_clicked
 * @property int $stat_bounced
 * @property int $stat_unsubscribed
 */
class Campaign extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'channel', 'type', 'subject', 'subject_b', 'preheader',
        'from_name', 'from_email', 'body_html', 'body_text', 'marketing_list_id',
        'status', 'scheduled_at', 'sent_at', 'vertical_id',
        'stat_recipients', 'stat_sent', 'stat_opened', 'stat_clicked', 'stat_bounced', 'stat_unsubscribed',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * @return BelongsTo<MarketingList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(MarketingList::class, 'marketing_list_id');
    }

    /**
     * @return HasMany<CampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
