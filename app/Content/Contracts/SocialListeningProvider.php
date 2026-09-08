<?php

namespace App\Content\Contracts;

/**
 * SOC-022/023/024: the social-listening seam (ADR-0007). The fixture driver
 * ships and is tested; a live driver (Brandwatch, Mention, X API, …) is
 * credentials plus a class implementing this contract.
 */
interface SocialListeningProvider
{
    /**
     * Public mentions of the given term.
     *
     * @return list<array{network: string, author: string, text: string, url: string, days_ago: int}>
     */
    public function mentions(string $term): array;

    public function name(): string;
}
