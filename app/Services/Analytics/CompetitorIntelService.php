<?php

namespace App\Services\Analytics;

use App\Analytics\Contracts\AdLibraryProvider;
use App\Content\Contracts\ReviewProvider;
use App\Content\Contracts\SocialListeningProvider;
use App\Models\Competitor;
use App\Models\Keyword;
use App\Seo\Contracts\LinkDataProvider;
use App\Seo\Contracts\RankProvider;
use App\Services\Content\SocialEngagementService;

/**
 * CINT-002/003/004/006/007/009: the provider-backed competitor panels — ads
 * from transparency data, backlink summaries, local-pack positions, review
 * summaries and social mention volume. Every panel names its driver; fixture
 * output is labeled simulated in the UI and never reads as market findings.
 * The first-party half (head-to-head, share of voice, content snapshots)
 * lives beside these and is never overwritten by them.
 */
class CompetitorIntelService
{
    public function __construct(
        private AdLibraryProvider $adLibrary,
        private LinkDataProvider $links,
        private RankProvider $rank,
        private ReviewProvider $reviews,
        private SocialListeningProvider $listening,
        private SocialEngagementService $engagement,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function panels(Competitor $competitor): array
    {
        return [
            'ads' => $this->ads($competitor),
            'backlinks' => $this->backlinks($competitor),
            'map' => $this->mapPositions($competitor),
            'reviews' => $this->reviewSummary($competitor),
            'social' => $this->social($competitor),
        ];
    }

    /**
     * The driver name behind each panel, for honest labeling.
     *
     * @return array<string, string>
     */
    public function providers(): array
    {
        return [
            'ads' => $this->adLibrary->name(),
            'backlinks' => $this->links->name(),
            'map' => (string) config('seo.rank_provider', 'fixture'),
            'reviews' => (string) config('content.review_provider', 'fixture'),
            'social' => $this->listening->name(),
        ];
    }

    /**
     * CINT-002/003: transparency-data ads.
     *
     * @return array{total: int, active: int, sample: list<array{platform: string, headline: string, body: string, status: string, first_seen: string}>}
     */
    private function ads(Competitor $competitor): array
    {
        $ads = $this->adLibrary->ads($competitor->name);

        return [
            'total' => count($ads),
            'active' => count(array_filter($ads, fn (array $ad) => $ad['status'] === 'active')),
            'sample' => array_slice($ads, 0, 3),
        ];
    }

    /**
     * CINT-004: backlink summary through the link seam.
     *
     * @return array{links: int, referring_domains: int, avg_da: int|null}|null
     */
    private function backlinks(Competitor $competitor): ?array
    {
        if ($competitor->domain === null || $competitor->domain === '') {
            return null;
        }

        $links = $this->links->backlinks((string) $competitor->domain);
        $das = array_column($links, 'domain_authority');

        return [
            'links' => count($links),
            'referring_domains' => count(array_unique(array_column($links, 'source_domain'))),
            'avg_da' => $das !== [] ? (int) round(array_sum($das) / count($das)) : null,
        ];
    }

    /**
     * CINT-006: the competitor's local-pack positions on the tenant's own
     * tracked, located keywords — null positions are honest misses.
     *
     * @return list<array{keyword: string, location: string|null, position: int|null}>
     */
    private function mapPositions(Competitor $competitor): array
    {
        return Keyword::where('is_tracked', true)->whereNotNull('location')->limit(5)->get()
            ->map(fn (Keyword $k) => [
                'keyword' => (string) $k->phrase,
                'location' => $k->location,
                'position' => $this->rank->localPack((string) $k->phrase, $competitor->name, $k->location),
            ])->all();
    }

    /**
     * CINT-007: review summary through the ADR-0007 seam.
     *
     * @return array{count: int, avg_rating: float|null, latest: string|null}
     */
    private function reviewSummary(Competitor $competitor): array
    {
        $reviews = $this->reviews->fetch('google', $competitor->name);
        $ratings = array_column($reviews, 'rating');

        return [
            'count' => count($reviews),
            'avg_rating' => $ratings !== [] ? round(array_sum($ratings) / count($ratings), 1) : null,
            'latest' => $reviews !== [] ? (string) $reviews[0]['body'] : null,
        ];
    }

    /**
     * CINT-009: mention volume by network with the transparent sentiment
     * heuristic — the listening seam pointed at the competitor's name.
     *
     * @return array{mentions: int, by_network: array<string, int>, negative: int, positive: int}
     */
    private function social(Competitor $competitor): array
    {
        $mentions = $this->listening->mentions($competitor->name);

        $byNetwork = [];
        $negative = 0;
        $positive = 0;
        foreach ($mentions as $mention) {
            $byNetwork[$mention['network']] = ($byNetwork[$mention['network']] ?? 0) + 1;
            $sentiment = $this->engagement->sentiment((string) $mention['text']);
            if ($sentiment === 'negative') {
                $negative++;
            } elseif ($sentiment === 'positive') {
                $positive++;
            }
        }

        return [
            'mentions' => count($mentions),
            'by_network' => $byNetwork,
            'negative' => $negative,
            'positive' => $positive,
        ];
    }
}
