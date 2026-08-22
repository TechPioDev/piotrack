<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single turn in a chat conversation.
 *
 * @property array<string, mixed>|null $meta
 */
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use BelongsToTenant, HasFactory;

    // visitor + bot are the automated conversation; agent is a human reply; note is an
    // internal-only note visitors never see; system marks assignment/status events.
    public const ROLES = ['visitor', 'bot', 'agent', 'note', 'system'];

    protected $fillable = [
        'organization_id',
        'chat_conversation_id',
        'role',
        'author_id',
        'body',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<ChatConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isInternal(): bool
    {
        return $this->role === 'note';
    }
}
