<?php

namespace App\Crm\Contracts;

/**
 * CRM-027: contact/company enrichment behind a seam. The fixture driver ships
 * deterministic output labeled simulated; a live driver (Clearbit, Apollo,
 * ZoomInfo) is credentials plus one class. Enrichment data only ever fills
 * EMPTY fields — operator-entered data is never overwritten.
 */
interface EnrichmentProvider
{
    /**
     * Firmographic hints for an email address. Nulls mean "nothing known" —
     * a driver never invents a value it does not have.
     *
     * @return array{company_name: string|null, industry: string|null, employee_range: string|null, region: string|null}
     */
    public function enrich(string $email): array;

    /**
     * INTENT-002: reverse-IP company identification. Null when the address is
     * private/reserved or the provider simply has no match — an honest miss,
     * never a guess. Live drivers: Clearbit Reveal, 6sense, KickFire.
     *
     * @return array{company_name: string, domain: string, industry: string|null}|null
     */
    public function identifyCompany(string $ip): ?array;

    /**
     * ABM-004: firmographics for a company domain (same honesty contract as
     * enrich()).
     *
     * @return array{company_name: string|null, industry: string|null, employee_range: string|null, region: string|null}
     */
    public function enrichDomain(string $domain): array;

    public function name(): string;
}
