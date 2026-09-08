<?php

namespace App\Seo\Contracts;

/**
 * LINK-001/002/003: the external link-index seam (ADR-0007). The fixture
 * driver ships and is tested; a live driver (Ahrefs, GSC, Majestic, …) is
 * credentials plus a class implementing this contract.
 */
interface LinkDataProvider
{
    /**
     * Backlinks pointing at the given domain.
     *
     * @return list<array{source_domain: string, url: string, anchor: string, domain_authority: int}>
     */
    public function backlinks(string $domain): array;

    public function name(): string;
}
