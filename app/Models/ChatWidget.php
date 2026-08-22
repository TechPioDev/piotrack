<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ChatWidgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A configurable website chat widget a tenant embeds on their own site.
 *
 * @property array<string, mixed>|null $flow
 * @property array<string, mixed>|null $theme
 * @property array<string, mixed>|null $targeting
 * @property array<string, mixed>|null $business_hours
 * @property array<string, mixed>|null $consent
 * @property array<string, mixed>|null $routing
 * @property array<string, mixed>|null $settings
 * @property array<int, string>|null $allowed_domains
 */
class ChatWidget extends Model
{
    /** @use HasFactory<ChatWidgetFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'active', 'paused'];

    protected $fillable = [
        'organization_id',
        'public_key',
        'name',
        'description',
        'status',
        'flow',
        'theme',
        'targeting',
        'business_hours',
        'consent',
        'routing',
        'settings',
        'allowed_domains',
    ];

    protected static function booted(): void
    {
        // A stable, non-secret id the embed script carries in page source. Prefixed
        // so it is recognisable and never collides with a numeric primary key.
        static::creating(function (ChatWidget $widget) {
            if (empty($widget->public_key)) {
                $widget->public_key = 'wc_'.Str::lower(Str::random(24));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'flow' => 'array',
            'theme' => 'array',
            'targeting' => 'array',
            'business_hours' => 'array',
            'consent' => 'array',
            'routing' => 'array',
            'settings' => 'array',
            'allowed_domains' => 'array',
        ];
    }

    /** @return HasMany<ChatConversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }

    /** @return HasMany<ChatEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ChatEvent::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
