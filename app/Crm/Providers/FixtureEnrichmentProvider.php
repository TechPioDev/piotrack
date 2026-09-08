<?php

namespace App\Crm\Providers;

use App\Crm\Contracts\EnrichmentProvider;

/**
 * Deterministic enrichment fixture: the same email domain always yields the
 * same firmographics (seeded from a hash), and free-mail domains honestly
 * yield nothing — there is no company behind gmail.com. The UI labels this
 * driver's output as simulated.
 */
class FixtureEnrichmentProvider implements EnrichmentProvider
{
    private const FREE_MAIL = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com', 'icloud.com', 'proton.me'];

    private const INDUSTRIES = ['Manufacturing', 'Healthcare', 'Legal Services', 'Financial Services', 'Construction', 'Logistics', 'Education', 'Nonprofit'];

    private const EMPLOYEE_RANGES = ['1-10', '11-50', '51-200', '201-500'];

    private const REGIONS = ['Northeast', 'Southeast', 'Midwest', 'Southwest', 'West'];

    public function enrich(string $email): array
    {
        $email = mb_strtolower(trim($email));
        if (! str_contains($email, '@')) {
            return ['company_name' => null, 'industry' => null, 'employee_range' => null, 'region' => null];
        }

        $domain = explode('@', $email)[1];
        if (in_array($domain, self::FREE_MAIL, true)) {
            // A personal mailbox carries no firmographics; say so.
            return ['company_name' => null, 'industry' => null, 'employee_range' => null, 'region' => null];
        }

        $seed = crc32($domain);
        $label = explode('.', $domain)[0];
        $companyName = ucwords(str_replace(['-', '_'], ' ', $label));

        return [
            'company_name' => $companyName,
            'industry' => self::INDUSTRIES[$seed % count(self::INDUSTRIES)],
            'employee_range' => self::EMPLOYEE_RANGES[($seed >> 3) % count(self::EMPLOYEE_RANGES)],
            'region' => self::REGIONS[($seed >> 5) % count(self::REGIONS)],
        ];
    }

    public function identifyCompany(string $ip): ?array
    {
        // Private/reserved space can never be a company; say nothing.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        $seed = crc32($ip);

        // A realistic reverse-IP index misses most of the time; the fixture
        // does too (3 in 4 addresses yield no match), deterministically.
        if ($seed % 4 !== 0) {
            return null;
        }

        $label = ['summit', 'harborview', 'ridgeline', 'lakeside', 'ironworks', 'bluepeak'][($seed >> 2) % 6];

        return [
            'company_name' => ucfirst($label).' '.self::INDUSTRIES[$seed % count(self::INDUSTRIES)],
            'domain' => $label.'-corp.example',
            'industry' => self::INDUSTRIES[$seed % count(self::INDUSTRIES)],
        ];
    }

    public function name(): string
    {
        return 'fixture';
    }
}
