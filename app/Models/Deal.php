<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Contracts\HasActivities;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $expected_close_date
 * @property Carbon|null $closed_at
 * @property int|null $service_line_id
 */
class Deal extends Model implements HasActivities
{
    /** @use HasFactory<DealFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * Annual recurring revenue is annualised monthly recurring revenue, so it is
     * derived rather than asked for twice. DealController accepts `arr` as
     * optional, and a deal saved with only MRR filled in previously left ARR at
     * its column default of 0 - the revenue dashboard then reported $0 ARR
     * against real MRR.
     *
     * An explicitly supplied ARR wins: prepaid multi-year terms do not always
     * annualise cleanly, and the operator's figure is the authoritative one.
     */
    protected static function booted(): void
    {
        static::saving(function (Deal $deal): void {
            if (! $deal->isDirty('arr') && (int) $deal->mrr > 0) {
                $deal->arr = (int) $deal->mrr * 12;
            }
        });
    }

    protected $fillable = [
        'organization_id', 'pipeline_id', 'stage_id', 'name', 'contact_id', 'company_id',
        'value', 'mrr', 'arr', 'contract_term_months', 'ltv', 'currency', 'status',
        'lead_source', 'campaign', 'owner_id', 'marketing_owner_id', 'expected_close_date', 'closed_at', 'proposal_sent_at',
        'service_line_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'mrr' => 'integer',
            'arr' => 'integer',
            'ltv' => 'integer',
            'contract_term_months' => 'integer',
            'expected_close_date' => 'date',
            'closed_at' => 'datetime',
            'proposal_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * STRAT-014: the service line this deal sells (hard binding, not name matching).
     *
     * @return BelongsTo<ServiceLine, $this>
     */
    public function serviceLine(): BelongsTo
    {
        return $this->belongsTo(ServiceLine::class);
    }

    /**
     * @return BelongsTo<PipelineStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
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
     * @return MorphMany<Activity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }
}
