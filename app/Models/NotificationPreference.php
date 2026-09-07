<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    /** Notification categories. */
    public const CATEGORIES = ['billing', 'members', 'operations', 'security', 'sales', 'marketing'];

    /** Channels a user can toggle. Security notices ignore opt-out; SMS is opt-in. */
    public const CHANNELS = ['in_app', 'email', 'sms'];

    protected $fillable = ['user_id', 'category', 'channel', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
