<?php

namespace App\Notifications;

/**
 * A tracked competitor ranks ahead of us on a tracked keyword (CINT-013).
 * Deduped per keyword+competitor per day by the sweep.
 */
class CompetitorOutrankNotification extends PlatformNotification
{
    public function __construct(
        private int $keywordId,
        private string $phrase,
        private string $competitorDomain,
        private int $theirPosition,
        private ?int $ourPosition,
    ) {}

    public function category(): string
    {
        return 'marketing';
    }

    public function title(): string
    {
        return 'Competitor ranks ahead of you';
    }

    public function body(): string
    {
        $ours = $this->ourPosition !== null ? "#{$this->ourPosition}" : 'unranked';

        return "{$this->competitorDomain} is #{$this->theirPosition} for \"{$this->phrase}\" while you are {$ours}.";
    }

    public function url(): ?string
    {
        return '/analytics/competitors';
    }

    public function dedupeKey(): ?string
    {
        return "competitor-outrank:{$this->keywordId}:{$this->competitorDomain}";
    }
}
