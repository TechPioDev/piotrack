<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Contracts\HasActivities;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $lead_score
 * @property bool $email_opt_in
 * @property bool $sms_opt_in
 * @property string $lifecycle_stage
 */
class Contact extends Model implements HasActivities
{
    /** @use HasFactory<ContactFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /** Funnel order. The single source for validation and stage pickers (CRMT). */
    public const LIFECYCLE_STAGES = ['subscriber', 'lead', 'mql', 'sql', 'opportunity', 'customer', 'evangelist'];

    protected $fillable = [
        'organization_id', 'company_id', 'first_name', 'last_name', 'email', 'phone',
        'title', 'lead_source', 'campaign', 'owner_id',
        'lifecycle_stage', 'lead_score', 'email_opt_in', 'sms_opt_in',
    ];

    protected function casts(): array
    {
        return [
            'email_opt_in' => 'boolean',
            'sms_opt_in' => 'boolean',
        ];
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /**
     * @return MorphMany<Activity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /**
     * @return BelongsToMany<MarketingList, $this>
     */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(MarketingList::class, 'list_memberships', 'contact_id', 'marketing_list_id');
    }

    /**
     * @return HasMany<IntentSignal, $this>
     */
    public function intentSignals(): HasMany
    {
        return $this->hasMany(IntentSignal::class);
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(fn (Builder $q) => $q
            ->whereLike('first_name', $like)
            ->orWhereLike('last_name', $like)
            ->orWhereLike('email', $like));
    }
}
