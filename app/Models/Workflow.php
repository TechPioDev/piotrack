<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $trigger_config
 * @property string $trigger_type
 * @property string $status
 * @property int $enrolled_count
 * @property int $completed_count
 */
class Workflow extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'description', 'trigger_type', 'trigger_config',
        'status', 'enrolled_count', 'completed_count', 'vertical_id',
    ];

    protected function casts(): array
    {
        return ['trigger_config' => 'array'];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * @return HasMany<WorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('position');
    }

    /**
     * @return HasMany<WorkflowEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(WorkflowEnrollment::class);
    }
}
