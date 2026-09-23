<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An answer the team types often, kept once and picked from the reply box.
 *
 * Shared across the team rather than owned by one agent: the point is that the
 * answer to "how much does onboarding cost?" is the same whoever is on chat.
 * The body may carry the same {{first_name}} placeholders the flow uses.
 */
class ChatSavedReply extends Model
{
    use BelongsToTenant;

    protected $fillable = ['organization_id', 'created_by', 'title', 'body'];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
