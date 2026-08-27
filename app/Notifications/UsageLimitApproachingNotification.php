<?php

namespace App\Notifications;

use Illuminate\Support\Str;

/**
 * A plan limit is nearly used up (ALRT / NOTIF-007): warn before the wall,
 * not at it. Deduped per limit per day by the sweep.
 */
class UsageLimitApproachingNotification extends PlatformNotification
{
    public function __construct(
        private string $limitKey,
        private int $used,
        private int $limit,
    ) {}

    public function category(): string
    {
        return 'billing';
    }

    public function title(): string
    {
        return Str::headline($this->limitKey).' limit approaching';
    }

    public function body(): string
    {
        $pct = (int) round($this->used / max(1, $this->limit) * 100);

        return 'You have used '.number_format($this->used).' of '.number_format($this->limit).' '
            .Str::headline($this->limitKey).' ('.$pct.'%). Upgrade your plan to avoid interruptions.';
    }

    public function url(): ?string
    {
        return '/billing';
    }

    public function dedupeKey(): ?string
    {
        return 'usage:'.$this->limitKey;
    }
}
