<?php

namespace App\Services\Content;

use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Deal;
use App\Models\SocialPost;
use App\Models\Visitor;

/**
 * Social strategy computed from the tenant's own records (SOC-006) and social
 * lead attribution through the existing channel classifier (SOC-027). What a
 * strategist can COMPUTE is computed — cadence, mix, gaps, attribution — with
 * every recommendation citing its number; creative judgement stays human.
 */
class SocialStrategy
{
    private const NETWORKS = ['linkedin', 'facebook', 'x', 'youtube'];

    /** utm_source / lead_source values that name a social network. */
    private const NETWORK_SOURCES = ['linkedin', 'facebook', 'x', 'twitter', 'youtube', 'instagram', 'reddit', 'tiktok'];

    /** A network with no published post for this many days has gone quiet. */
    private const QUIET_DAYS = 14;

    /**
     * Cadence + mix per network over the last 30 days, with recommendations.
     *
     * @return array{networks: list<array<string, mixed>>, mix: array<string, int>, recommendations: list<array{evidence: string, action: string}>}
     */
    public function report(): array
    {
        $since = now()->subDays(30);
        $networks = [];
        $recommendations = [];
        $publishedTotal = 0;

        foreach (self::NETWORKS as $network) {
            $published = SocialPost::where('channel', $network)->where('status', 'published')
                ->where('published_at', '>=', $since)->count();
            $scheduled = SocialPost::where('channel', $network)->where('status', 'scheduled')
                ->where('scheduled_at', '>=', now())->count();
            $lastAt = SocialPost::where('channel', $network)->where('status', 'published')->max('published_at');

            $publishedTotal += $published;
            $networks[] = [
                'network' => $network,
                'published_30d' => $published,
                'per_week' => round($published / (30 / 7), 1),
                'scheduled_ahead' => $scheduled,
                'last_published_at' => $lastAt,
            ];

            $quiet = $lastAt === null || now()->parse($lastAt)->lt(now()->subDays(self::QUIET_DAYS));
            if ($quiet && $scheduled === 0) {
                $recommendations[] = [
                    'evidence' => $lastAt === null
                        ? ucfirst($network).' has never had a published post.'
                        : ucfirst($network).' has been quiet since '.now()->parse($lastAt)->toDateString().' with nothing scheduled.',
                    'action' => 'Schedule posts for it (Content → Social) — the multimedia Promote button fills all four networks at once.',
                ];
            }
        }

        // Concentration: everything riding one network is a single point of failure.
        $active = collect($networks)->filter(fn (array $n) => $n['published_30d'] > 0);
        if ($publishedTotal >= 4 && $active->count() === 1) {
            $recommendations[] = [
                'evidence' => "All {$publishedTotal} posts in the last 30 days went to {$active->first()['network']}.",
                'action' => 'Spread the schedule across networks — each has its own audience and its own failure modes.',
            ];
        }

        // Long-form multimedia with no clips is distribution left on the table.
        $multimedia = ContentPiece::whereIn('content_type', ['video', 'webinar', 'podcast', 'interview'])
            ->where('status', 'published')->count();
        $clips = SocialPost::where('type', 'clip')->count();
        if ($multimedia > 0 && $clips === 0) {
            $recommendations[] = [
                'evidence' => "{$multimedia} published multimedia piece(s) and 0 clips scheduled from them.",
                'action' => 'Use "Schedule clips" on a multimedia piece — each long-form piece is a week of short-form material.',
            ];
        }

        $mix = SocialPost::where('status', 'published')->where('published_at', '>=', $since)
            ->get(['type'])
            ->groupBy(fn (SocialPost $p) => (string) ($p->type ?: 'post'))
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->all();

        return ['networks' => $networks, 'mix' => $mix, 'recommendations' => $recommendations];
    }

    /**
     * Social lead attribution per network (SOC-027): what the channel
     * classifier already recognises as social, split by utm_source — visitors,
     * identified contacts, customers and won revenue. Real records only.
     *
     * @return list<array{network: string, visitors: int, leads: int, customers: int, won_revenue: int}>
     */
    public function attribution(): array
    {
        $bySource = [];

        foreach (Visitor::whereNotNull('utm_source')->get() as $visitor) {
            if ($visitor->channel() !== 'social') {
                continue;
            }
            $network = strtolower((string) $visitor->utm_source);
            $bySource[$network] ??= ['visitors' => 0, 'leads' => 0];
            $bySource[$network]['visitors']++;
            if ($visitor->contact_id !== null) {
                $bySource[$network]['leads']++;
            }
        }

        // Contacts captured with a social lead_source count as leads for that
        // network even without a tracked visit (imported or manual capture).
        foreach (self::NETWORK_SOURCES as $network) {
            $contacts = Contact::where('lead_source', $network)->get(['id', 'lifecycle_stage']);
            if ($contacts->isEmpty() && ! isset($bySource[$network])) {
                continue;
            }
            $bySource[$network] ??= ['visitors' => 0, 'leads' => 0];
            $bySource[$network]['leads'] = max($bySource[$network]['leads'], $contacts->count());
            $bySource[$network]['customers'] = $contacts->where('lifecycle_stage', 'customer')->count();
            $bySource[$network]['won_revenue'] = (int) Deal::where('status', 'won')
                ->whereIn('contact_id', $contacts->pluck('id'))->sum('value');
        }

        return collect($bySource)->map(fn (array $row, string $network) => [
            'network' => $network,
            'visitors' => $row['visitors'],
            'leads' => $row['leads'],
            'customers' => $row['customers'] ?? 0,
            'won_revenue' => $row['won_revenue'] ?? 0,
        ])->sortByDesc('leads')->values()->all();
    }
}
