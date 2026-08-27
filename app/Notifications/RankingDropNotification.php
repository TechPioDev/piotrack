<?php

namespace App\Notifications;

/**
 * A tracked keyword fell noticeably between its two most recent recorded
 * checks (ALRT / NOTIF-009). The detection runs on stored rankings whatever
 * their provider; deduped per keyword per day by the sweep.
 */
class RankingDropNotification extends PlatformNotification
{
    public function __construct(
        private int $keywordId,
        private string $phrase,
        private int $from,
        private int $to,
    ) {}

    public function category(): string
    {
        return 'marketing';
    }

    public function title(): string
    {
        return 'Keyword ranking dropped';
    }

    public function body(): string
    {
        return "\"{$this->phrase}\" fell from position {$this->from} to {$this->to}.";
    }

    public function url(): ?string
    {
        return '/seo/keywords';
    }

    public function dedupeKey(): ?string
    {
        return 'ranking-drop:'.$this->keywordId;
    }
}
