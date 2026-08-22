<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ChatEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An analytics event emitted by a widget/conversation, powering the engagement
 * funnel and per-question drop-off report.
 *
 * @property array<string, mixed>|null $meta
 */
class ChatEvent extends Model
{
    /** @use HasFactory<ChatEventFactory> */
    use BelongsToTenant, HasFactory;

    public const TYPES = ['impression', 'open', 'start', 'complete', 'lead', 'qualified', 'meeting', 'dropoff'];

    protected $fillable = [
        'organization_id',
        'chat_widget_id',
        'chat_conversation_id',
        'type',
        'node_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<ChatWidget, $this> */
    public function widget(): BelongsTo
    {
        return $this->belongsTo(ChatWidget::class, 'chat_widget_id');
    }

    /** @return BelongsTo<ChatConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }
}
