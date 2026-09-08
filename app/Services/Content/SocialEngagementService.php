<?php

namespace App\Services\Content;

use App\Content\Contracts\SocialListeningProvider;
use App\Models\BrandProfile;
use App\Models\ChatConversation;
use App\Models\ListeningTerm;
use App\Models\Review;
use App\Models\SocialInteraction;

/**
 * SOC-020..024: the engagement inbox over what the platform actually holds —
 * logged social interactions, chat conversations waiting for a human, and
 * reviews awaiting a response — plus brand monitoring through the listening
 * seam. Sentiment is a transparent keyword heuristic, never a claim of
 * understanding; live comment/DM ingestion stays channel-API-gated.
 */
class SocialEngagementService
{
    private const NEGATIVE_WORDS = ['worst', 'terrible', 'awful', 'scam', 'outage', 'down', 'slow', 'complaint', 'avoid', 'disappointed'];

    private const POSITIVE_WORDS = ['great', 'excellent', 'love', 'recommend', 'best', 'solid', 'shoutout', 'amazing', 'helpful'];

    public function __construct(private SocialListeningProvider $listening) {}

    /**
     * SOC-020/021: everything a community manager owes a reply to right now.
     *
     * @return array{interactions: list<array<string, mixed>>, chat_waiting: int, reviews_unresponded: int, metrics: array{replied: int, open: int, avg_response_hours: float|null}}
     */
    public function inbox(): array
    {
        $open = SocialInteraction::where('status', 'open')->latest('id')->get();

        $replied = SocialInteraction::where('status', 'replied')->whereNotNull('replied_at')->get();
        $avgHours = $replied->isEmpty() ? null : round($replied->avg(
            fn (SocialInteraction $i) => $i->created_at !== null && $i->replied_at !== null
                ? abs($i->replied_at->diffInMinutes($i->created_at)) / 60
                : 0.0
        ), 1);

        return [
            'interactions' => $open->map(fn (SocialInteraction $i) => [
                'id' => $i->id,
                'network' => $i->network,
                'kind' => $i->kind,
                'author' => $i->author,
                'url' => $i->url,
                'body' => $i->body,
                'sentiment' => $i->sentiment,
                'created_at' => $i->created_at?->toIso8601String(),
            ])->all(),
            'chat_waiting' => ChatConversation::where('status', 'waiting')->count(),
            'reviews_unresponded' => Review::whereNull('response')->count(),
            'metrics' => [
                'replied' => $replied->count(),
                'open' => $open->count(),
                'avg_response_hours' => $avgHours,
            ],
        ];
    }

    /**
     * SOC-022/023/024: mentions of the brand name + every active listening
     * term through the seam, with heuristic sentiment and per-network volume.
     *
     * @return array{provider: string, terms: list<string>, mentions: list<array<string, mixed>>, by_network: array<string, int>, negative: int, positive: int}
     */
    public function monitor(): array
    {
        $brand = BrandProfile::first();
        $terms = ListeningTerm::where('is_active', true)->pluck('term')->all();
        $brandName = $brand?->legal_name;
        if ($brandName !== null && $brandName !== '' && ! in_array($brandName, $terms, true)) {
            array_unshift($terms, $brandName);
        }

        $mentions = [];
        foreach ($terms as $term) {
            foreach ($this->listening->mentions($term) as $mention) {
                $mentions[] = $mention + ['term' => $term, 'sentiment' => $this->sentiment($mention['text'])];
            }
        }

        $byNetwork = [];
        foreach ($mentions as $mention) {
            $byNetwork[$mention['network']] = ($byNetwork[$mention['network']] ?? 0) + 1;
        }

        return [
            'provider' => $this->listening->name(),
            'terms' => $terms,
            'mentions' => $mentions,
            'by_network' => $byNetwork,
            'negative' => count(array_filter($mentions, fn (array $m) => $m['sentiment'] === 'negative')),
            'positive' => count(array_filter($mentions, fn (array $m) => $m['sentiment'] === 'positive')),
        ];
    }

    /**
     * Transparent keyword heuristic — stated as such in the UI.
     */
    public function sentiment(string $text): string
    {
        $lower = mb_strtolower($text);

        foreach (self::NEGATIVE_WORDS as $word) {
            if (str_contains($lower, $word)) {
                return 'negative';
            }
        }
        foreach (self::POSITIVE_WORDS as $word) {
            if (str_contains($lower, $word)) {
                return 'positive';
            }
        }

        return 'neutral';
    }
}
