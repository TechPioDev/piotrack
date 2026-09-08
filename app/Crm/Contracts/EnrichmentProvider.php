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

    public function name(): string;
}
