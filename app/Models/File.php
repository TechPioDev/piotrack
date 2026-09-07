<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property bool $client_visible
 * @property string $name
 */
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'uploaded_by', 'disk', 'path', 'name', 'mime', 'size', 'client_visible',
        'attachable_type', 'attachable_id',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer', 'client_visible' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
