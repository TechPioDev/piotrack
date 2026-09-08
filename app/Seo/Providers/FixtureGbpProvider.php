<?php

namespace App\Seo\Providers;

use App\Models\SeoLocation;
use App\Seo\Contracts\GbpProvider;

/**
 * Deterministic GBP fixture: acknowledges the push and reports exactly which
 * of the branch's REAL fields it would send — never pretending Google saw
 * them. The UI labels this driver's pushes as simulated.
 */
class FixtureGbpProvider implements GbpProvider
{
    public function pushProfile(SeoLocation $location): array
    {
        $fields = [];
        foreach (['name', 'street', 'city', 'region', 'postal_code', 'country', 'phone', 'website'] as $field) {
            $value = $location->getAttribute($field);
            if ($value !== null && $value !== '') {
                $fields[] = $field;
            }
        }

        return ['accepted' => true, 'fields' => $fields];
    }

    public function name(): string
    {
        return 'fixture';
    }
}
