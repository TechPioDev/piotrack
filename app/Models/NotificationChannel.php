<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * NOTIF-004/005: an organization-level outbound notification channel — a
 * Slack or Teams incoming-webhook URL, or a generic webhook endpoint signed
 * with the channel's secret. Fired once per organization-level notification.
 *
 * @property string $kind
 * @property string $url
 * @property string|null $secret
 * @property bool $is_active
 */
class NotificationChannel extends Model
{
    use BelongsToTenant;

    public const KINDS = ['slack', 'teams', 'webhook'];

    protected $fillable = ['organization_id', 'kind', 'url', 'secret', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
