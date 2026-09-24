<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $requester_id
 * @property int|null $assignee_id
 * @property string|null $requester_email
 * @property string|null $requester_name
 * @property int|null $contact_id
 * @property int|null $chat_conversation_id
 * @property string $subject
 * @property string $status
 * @property string $priority
 * @property Carbon|null $resolved_at
 */
class Ticket extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'requester_id', 'assignee_id', 'subject', 'body',
        'status', 'priority', 'category', 'resolved_at',
        // Someone outside the workspace - a website visitor - who asked.
        'requester_email', 'requester_name', 'contact_id', 'chat_conversation_id',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /**
     * The website chat this ticket came from, if it did.
     *
     * @return BelongsTo<ChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /**
     * The client's record, when the person asking is already in the CRM.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }
}
