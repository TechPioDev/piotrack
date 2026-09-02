<?php

namespace App\Services\Content;

use App\Content\Contracts\ReviewProvider;
use App\Models\AuthorityAsset;
use App\Models\ContentPiece;
use App\Models\LandingPage;
use App\Models\Review;
use App\Models\ReviewRequest;
use App\Support\AuditLogger;
use RuntimeException;

/**
 * Reputation management (REP): reviews, review-acquisition requests, and rating
 * + sentiment aggregation. Sentiment is derived from the rating (in-house);
 * review sync goes through the ReviewProvider (fixture tested).
 */
class ReputationService
{
    /**
     * Every kind of authority the platform records (REP-005..017): manual
     * proof plus the typed placements the outreach pipeline creates.
     */
    public const ASSET_TYPES = [
        'award', 'certification', 'logo', 'mention', 'proof',
        'video_testimonial', 'directory_profile', 'article', 'press',
        'expert_quote', 'thought_leadership', 'backlink',
    ];

    public function __construct(
        private ReviewProvider $provider,
        private AuditLogger $audit,
    ) {}

    /**
     * Per-profile optimization checklist for industry directories
     * (REP-006/007): deterministic checks on tenant-entered fields. Live
     * directory metrics need vendor APIs and are never invented.
     *
     * @return list<array{id: int, directory: string|null, name: string, ok: bool, checks: list<array{key: string, label: string, ok: bool, detail: string}>}>
     */
    public function directoryChecklists(): array
    {
        return AuthorityAsset::where('type', 'directory_profile')->orderBy('issuer')->get()
            ->map(function (AuthorityAsset $asset) {
                $details = $asset->details ?? [];

                $checks = [
                    ['key' => 'url', 'label' => 'Profile URL', 'ok' => ($asset->url ?? '') !== '',
                        'detail' => ($asset->url ?? '') !== '' ? 'Profile link on record.' : 'Add the live profile URL.'],
                    ['key' => 'description', 'label' => 'Description', 'ok' => ($details['description'] ?? '') !== '',
                        'detail' => ($details['description'] ?? '') !== '' ? 'Description recorded.' : 'Write the profile description — empty profiles rank last in directory search.'],
                    ['key' => 'services', 'label' => 'Service lines listed', 'ok' => ($details['services'] ?? []) !== [],
                        'detail' => ($details['services'] ?? []) !== [] ? count((array) $details['services']).' services listed.' : 'List your service lines so the directory categorizes you.'],
                    ['key' => 'reviews', 'label' => 'Reviews on profile', 'ok' => (int) ($details['review_count'] ?? 0) > 0,
                        'detail' => (int) ($details['review_count'] ?? 0) > 0 ? $details['review_count'].' reviews recorded.' : 'Drive review requests at this directory — profiles without reviews convert nobody.'],
                ];

                return [
                    'id' => $asset->id,
                    'directory' => $asset->issuer,
                    'name' => $asset->name,
                    'ok' => ! in_array(false, array_column($checks, 'ok'), true),
                    'checks' => $checks,
                ];
            })->all();
    }

    /**
     * Proof-first landing page (REP-019): a draft assembled from real records
     * only — 4-star-plus reviews with text, client logos, published case
     * studies. With nothing on file it refuses: a proof page with invented
     * proof would be worse than none.
     */
    public function createProofPage(): LandingPage
    {
        $reviews = Review::where('rating', '>=', 4)->whereNotNull('body')->where('body', '!=', '')
            ->latest('id')->limit(3)->get();
        $logos = AuthorityAsset::where('type', 'logo')->limit(8)->get();
        $caseStudies = ContentPiece::where('content_type', 'case_study')->where('status', 'published')->limit(2)->get();

        if ($reviews->isEmpty() && $logos->isEmpty() && $caseStudies->isEmpty()) {
            throw new RuntimeException('No proof on file yet — collect reviews, client logos or case studies first.');
        }

        $sections = [];
        foreach ($reviews as $review) {
            $author = e((string) ($review->author_name ?: 'A client'));
            $sections[] = '<blockquote>“'.e((string) $review->body).'” — '.$author.' ('.str_repeat('★', (int) $review->rating).')</blockquote>';
        }
        if ($logos->isNotEmpty()) {
            $sections[] = '<p>Trusted by '.e($logos->pluck('name')->implode(', ')).'.</p>';
        }
        foreach ($caseStudies as $study) {
            $sections[] = '<p>Case study: '.e($study->title).($study->excerpt ? ' — '.e((string) $study->excerpt) : '').'</p>';
        }

        $base = 'proof';
        $slug = $base;
        $n = 1;
        while (LandingPage::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        $page = LandingPage::create([
            'name' => 'Proof — what clients say',
            'slug' => $slug,
            'headline' => 'The results speak for themselves',
            'subheadline' => 'Real reviews, real clients, real outcomes.',
            'body_html' => implode("\n", $sections),
            'status' => 'draft',
        ]);

        $this->audit->log('content.reputation.proof_page', context: ['reviews' => $reviews->count(), 'logos' => $logos->count(), 'case_studies' => $caseStudies->count()], resourceType: 'landing_page', resourceId: (string) $page->id, organizationId: $page->organization_id);

        return $page;
    }

    public function sentiment(int $rating): string
    {
        return $rating >= 4 ? 'positive' : ($rating === 3 ? 'neutral' : 'negative');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordReview(array $data): Review
    {
        $data['sentiment'] = $this->sentiment((int) $data['rating']);
        $review = Review::create($data);

        $this->audit->log('content.review.recorded', context: ['source' => $review->source, 'rating' => $review->rating], resourceType: 'review', resourceId: (string) $review->id, organizationId: $review->organization_id);

        return $review;
    }

    /**
     * Store a reply to a review.
     *
     * The reply is recorded against the review and nothing more:
     * ReviewProvider exposes only fetch(), so no driver can post a reply back
     * to Google, Clutch or any other platform. response_published_at stays null
     * to say so, rather than leaving `responded` to imply the public has seen
     * it. Once a driver can publish, set it there.
     */
    public function respond(Review $review, string $response): Review
    {
        $review->update([
            'responded' => true,
            'response' => $response,
            'response_published_at' => null,
        ]);
        $this->audit->log('content.review.responded', context: ['published_externally' => false], resourceType: 'review', resourceId: (string) $review->id, organizationId: $review->organization_id);

        return $review;
    }

    public function sendRequest(ReviewRequest $request): ReviewRequest
    {
        $request->update(['status' => 'sent', 'sent_at' => now()]);
        $this->audit->log('content.review_request.sent', context: ['channel' => $request->channel], resourceType: 'review_request', resourceId: (string) $request->id, organizationId: $request->organization_id);

        return $request;
    }

    /**
     * Import reviews for a source profile via the provider.
     */
    public function import(string $source, string $identifier): int
    {
        $rows = $this->provider->fetch($source, $identifier);

        foreach ($rows as $row) {
            Review::create([
                // `source` is the platform the review is filed under; `provider`
                // is the driver that produced it. The fixture driver invents an
                // author, rating and body, which must never read as a real
                // customer's words filed under Google.
                'source' => $source,
                'provider' => (string) config('content.review_provider', 'fixture'),
                'author_name' => $row['author_name'],
                'rating' => $row['rating'],
                'body' => $row['body'],
                'url' => $row['url'],
                'sentiment' => $this->sentiment($row['rating']),
                'reviewed_at' => $row['reviewed_at'],
            ]);
        }

        return count($rows);
    }

    /**
     * @return array{count: int, average: float, by_sentiment: array<string, int>, by_source: array<string, int>}
     */
    public function aggregate(): array
    {
        $reviews = Review::all();

        return [
            'count' => $reviews->count(),
            'average' => round((float) ($reviews->avg('rating') ?? 0), 2),
            'by_sentiment' => [
                'positive' => $reviews->where('sentiment', 'positive')->count(),
                'neutral' => $reviews->where('sentiment', 'neutral')->count(),
                'negative' => $reviews->where('sentiment', 'negative')->count(),
            ],
            'by_source' => $reviews->groupBy('source')->map->count()->all(),
        ];
    }
}
