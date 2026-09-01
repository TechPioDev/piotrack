<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\ChannelClassifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One website visitor (VINT): the rollup of their sessions, pages, first-touch
 * attribution and — once they tell us who they are — their contact identity.
 *
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_seen_at
 */
class Visitor extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'visitor_key', 'contact_id', 'email',
        'first_seen_at', 'last_seen_at', 'visits', 'page_views', 'intent_score',
        'last_path', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign',
    ];

    /**
     * The acquisition channel derived from immutable first-touch data
     * (LEAD-010..014): organic, paid, social, content, referral or direct.
     */
    public function channel(): string
    {
        return ChannelClassifier::classify($this->utm_source, $this->utm_medium, $this->referrer);
    }

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'visits' => 'integer',
            'page_views' => 'integer',
            'intent_score' => 'integer',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return HasMany<VisitorEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(VisitorEvent::class);
    }
}
