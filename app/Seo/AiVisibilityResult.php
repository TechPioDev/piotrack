<?php

namespace App\Seo;

/**
 * The outcome of asking an AI engine a prompt and observing whether/where the
 * brand appears (ADR-0005).
 */
final class AiVisibilityResult
{
    /**
     * @param  list<string>  $citedSources
     * @param  list<string>  $competitors
     */
    public function __construct(
        public bool $mentioned,
        public ?int $position,
        public array $citedSources,
        public array $competitors,
        public int $shareOfAnswer,
        // The receipt (AIVM): the answer text the numbers were computed from.
        public string $answerExcerpt = '',
    ) {}
}
