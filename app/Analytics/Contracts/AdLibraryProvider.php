<?php

namespace App\Analytics\Contracts;

/**
 * CINT-002/003: competitor ad monitoring behind a seam. Ad-transparency data
 * is genuinely public — the Meta Ad Library is a real API, and Google's Ads
 * Transparency Center is served by SerpApi — so a live driver is a token plus
 * one class. The fixture is deterministic and labeled simulated in the UI.
 */
interface AdLibraryProvider
{
    /**
     * Ads currently or recently run by an advertiser, per transparency data.
     *
     * @return list<array{platform: string, headline: string, body: string, status: string, first_seen: string}>
     */
    public function ads(string $advertiser): array;

    public function name(): string;
}
