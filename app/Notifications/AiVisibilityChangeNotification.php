<?php

namespace App\Notifications;

/**
 * The AI-answer mention rate moved sharply between windows (ALRT / NOTIF-009,
 * on the existing AIVIS-017 comparison). Deduped per day by the sweep.
 */
class AiVisibilityChangeNotification extends PlatformNotification
{
    public function __construct(
        private string $direction,
        private float $delta,
        private float $current,
    ) {}

    public function category(): string
    {
        return 'marketing';
    }

    public function title(): string
    {
        return 'AI visibility '.($this->direction === 'up' ? 'improved' : 'declined');
    }

    public function body(): string
    {
        $moved = ($this->delta > 0 ? '+' : '').$this->delta;

        return "Your AI answer-engine mention rate moved {$moved} points to {$this->current}% over the last week.";
    }

    public function url(): ?string
    {
        return '/seo/ai-visibility';
    }

    public function dedupeKey(): ?string
    {
        return 'ai-visibility-change';
    }
}
