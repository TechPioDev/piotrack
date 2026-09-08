<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $action_config
 * @property string $action_type
 * @property int $position
 * @property int $delay_minutes
 */
class WorkflowStep extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'workflow_id', 'position', 'action_type', 'action_config', 'delay_minutes',
        'condition',
    ];

    protected function casts(): array
    {
        return ['action_config' => 'array', 'condition' => 'array'];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
