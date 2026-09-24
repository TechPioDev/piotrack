<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A conversation as it stood at one moment, kept so it can be put back.
 *
 * Written on every save and every publish, before the change lands - so the
 * list reads as "what it was", and restoring one is just another save. Every
 * competitor worth the comparison keeps this; a builder without it makes an
 * owner frightened of their own editor.
 *
 * @property array<string, mixed> $flow
 */
class ChatFlowVersion extends Model
{
    use BelongsToTenant;

    /** How many versions of one conversation are worth keeping. */
    public const KEEP = 25;

    protected $fillable = ['organization_id', 'chat_widget_id', 'saved_by', 'flow', 'steps', 'published', 'note'];

    protected function casts(): array
    {
        return ['flow' => 'array', 'steps' => 'integer', 'published' => 'boolean'];
    }

    /** @return BelongsTo<ChatWidget, $this> */
    public function widget(): BelongsTo
    {
        return $this->belongsTo(ChatWidget::class, 'chat_widget_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by');
    }
}
