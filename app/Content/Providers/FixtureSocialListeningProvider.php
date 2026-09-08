<?php

namespace App\Content\Providers;

use App\Content\Contracts\SocialListeningProvider;

/**
 * Deterministic listening fixture: the same term always yields the same
 * mention set, with a sentiment mix so the monitoring heuristics have real
 * work to do. The UI labels this driver's output as simulated.
 */
class FixtureSocialListeningProvider implements SocialListeningProvider
{
    private const TEMPLATES = [
        ['linkedin', 'ops-director', 'We evaluated {term} last quarter — great onboarding and the response times are excellent.'],
        ['x', 'smb_owner', 'Anyone used {term}? Looking for a provider that actually answers the phone.'],
        ['reddit', 'sysadmin_talk', '{term} came up in our vendor review. Solid, though the portal took getting used to.'],
        ['facebook', 'local-biz-group', 'Shoutout to {term} — recommend them for co-managed IT.'],
        ['x', 'grumpy_cto', 'Third outage ticket this month with our current MSP. {term} quote was the worst of the bunch though.'],
        ['linkedin', 'it-consultant', '{term} published a solid breakdown on ransomware recovery worth reading.'],
    ];

    public function mentions(string $term): array
    {
        $seed = crc32(mb_strtolower(trim($term)));
        $count = 3 + ($seed % 3);

        $mentions = [];
        for ($i = 0; $i < $count; $i++) {
            [$network, $author, $template] = self::TEMPLATES[($seed + $i) % count(self::TEMPLATES)];
            $mentions[] = [
                'network' => $network,
                'author' => $author,
                'text' => str_replace('{term}', $term, $template),
                'url' => "https://{$network}.example/posts/".(($seed + $i) % 977),
                'days_ago' => ($seed + $i) % 14,
            ];
        }

        return $mentions;
    }

    public function name(): string
    {
        return 'fixture';
    }
}
