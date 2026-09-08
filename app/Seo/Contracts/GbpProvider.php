<?php

namespace App\Seo\Contracts;

use App\Models\SeoLocation;

/**
 * MLOC-002: Google Business Profile management behind a WRITE seam (the SMS
 * provider precedent). The payload is the branch's REAL NAP record; the
 * fixture driver acknowledges the push and is labeled simulated in the UI;
 * the live driver is Google Business Profile API OAuth plus one class.
 */
interface GbpProvider
{
    /**
     * Push the branch's profile fields. Returns which fields were sent and
     * whether the provider accepted them.
     *
     * @return array{accepted: bool, fields: list<string>}
     */
    public function pushProfile(SeoLocation $location): array;

    public function name(): string;
}
