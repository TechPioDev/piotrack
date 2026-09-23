<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ChatConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One visitor's conversation with a chat widget, plus the CRM/qualification state
 * it accumulates (answers, score, attribution, the linked contact/lead).
 *
 * @property array<string, mixed>|null $answers
 * @property array<string, mixed>|null $attribution
 * @property int $lead_score
 * @property bool $is_preview
 * @property bool $is_live
 * @property Carbon|null $handoff_requested_at
 * @property Carbon|null $last_message_at
 * @property string|null $summary
 * @property Carbon|null $summary_generated_at
 * @property Carbon|null $visitor_seen_at
 * @property int|null $visitor_seen_message_id
 */
class ChatConversation extends Model
{
    /** @use HasFactory<ChatConversationFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUSES = ['new', 'open', 'waiting', 'assigned', 'qualified', 'converted', 'closed', 'spam'];

    protected $fillable = [
        'organization_id',
        'chat_widget_id',
        'token',
        'status',
        'visitor_id',
        'is_preview',
        'is_live',
        'handoff_requested_at',
        'assignee_id',
        'contact_id',
        'lead_id',
        'lead_score',
        'tags',
        'rating',
        'rated_at',
        'answers',
        'attribution',
        'last_message_at',
    ];

    protected static function booted(): void
    {
        // Opaque id the widget uses to post further messages without exposing the PK.
        static::creating(function (ChatConversation $conversation) {
            if (empty($conversation->token)) {
                $conversation->token = 'cv_'.Str::lower(Str::random(32));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'tags' => 'array',
            'rating' => 'integer',
            'rated_at' => 'datetime',
            'summary_generated_at' => 'datetime',
            'attribution' => 'array',
            'lead_score' => 'integer',
            'is_preview' => 'boolean',
            'is_live' => 'boolean',
            'handoff_requested_at' => 'datetime',
            'last_message_at' => 'datetime',
            'visitor_seen_at' => 'datetime',
            'visitor_seen_message_id' => 'integer',
        ];
    }

    /**
     * The visitor's widget has just been in touch; with $deliveredThrough, it
     * now holds every message up to that id. Presence is written at most every
     * few seconds, so a polling widget costs no extra write per poll.
     */
    public function markSeen(?int $deliveredThrough = null): void
    {
        $through = max((int) $this->visitor_seen_message_id, (int) $deliveredThrough);
        $stale = $this->visitor_seen_at === null || $this->visitor_seen_at->lt(now()->subSeconds(10));

        if ($stale || $through !== (int) $this->visitor_seen_message_id) {
            $this->forceFill(['visitor_seen_at' => now(), 'visitor_seen_message_id' => $through ?: null])->save();
        }
    }

    /** @return BelongsTo<ChatWidget, $this> */
    public function widget(): BelongsTo
    {
        return $this->belongsTo(ChatWidget::class, 'chat_widget_id');
    }

    /** @return HasMany<ChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /** @return HasMany<ChatEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ChatEvent::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
