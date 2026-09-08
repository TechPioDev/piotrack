<?php

namespace App\Analytics\Providers;

use App\Analytics\Contracts\AdLibraryProvider;
use Illuminate\Support\Carbon;

/**
 * Deterministic ad-library fixture: the same advertiser always yields the
 * same ad set (seeded from a hash), across Google and Meta, with a plausible
 * active/inactive mix. The UI labels this driver's output as simulated.
 */
class FixtureAdLibraryProvider implements AdLibraryProvider
{
    private const HEADLINES = [
        'Managed IT Support That Answers', '24/7 Helpdesk for SMBs', 'Ransomware-Proof Your Business',
        'IT Costs Down 30%', 'Your Outsourced IT Department', 'Compliance Without the Headache',
    ];

    private const BODIES = [
        'Flat-rate managed services with a response-time guarantee.',
        'Local engineers, remote monitoring, no long-term contracts.',
        'Security-first IT management for regulated industries.',
        'Switch in 30 days with zero downtime.',
    ];

    public function ads(string $advertiser): array
    {
        $seed = crc32(mb_strtolower(trim($advertiser)));
        $count = 2 + ($seed % 3); // 2-4 ads
        $ads = [];

        for ($i = 0; $i < $count; $i++) {
            $ads[] = [
                'platform' => ($seed + $i) % 2 === 0 ? 'google' : 'meta',
                'headline' => self::HEADLINES[($seed + $i) % count(self::HEADLINES)],
                'body' => self::BODIES[($seed + $i * 7) % count(self::BODIES)],
                'status' => ($seed + $i) % 3 === 0 ? 'inactive' : 'active',
                'first_seen' => Carbon::now()->subDays(10 + (($seed + $i * 13) % 120))->toDateString(),
            ];
        }

        return $ads;
    }

    public function name(): string
    {
        return 'fixture';
    }
}
