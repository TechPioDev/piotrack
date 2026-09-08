<?php

namespace App\Services\Content;

use App\Models\AdMetric;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Deal;
use App\Models\Review;
use Illuminate\Validation\ValidationException;

/**
 * DPR-009: research stories are data journalism — and the only data the
 * platform can honestly put a tenant's name on is the tenant's OWN. The
 * draft compiles their real aggregates with an explicit provenance line;
 * below the data floor it refuses instead of padding with fiction.
 */
class ResearchStoryBuilder
{
    /** Minimum contacts on file before a story is worth drafting. */
    public const MIN_CONTACTS = 10;

    public function draft(): ContentPiece
    {
        $contacts = Contact::count();

        if ($contacts < self::MIN_CONTACTS) {
            throw ValidationException::withMessages([
                'story' => __('Only :n contacts on file (floor: :min) — a research story needs enough of your own data to say something true.', ['n' => $contacts, 'min' => self::MIN_CONTACTS]),
            ]);
        }

        $wonDeals = Deal::where('status', 'won')->count();
        $totalDeals = Deal::count();
        $winRate = $totalDeals > 0 ? round($wonDeals / $totalDeals * 100, 1) : null;
        $avgDeal = $wonDeals > 0 ? (int) round((float) Deal::where('status', 'won')->avg('value')) : null;

        $reviews = Review::count();
        $avgRating = $reviews > 0 ? round((float) Review::avg('rating'), 1) : null;

        $impressions = (int) AdMetric::sum('impressions');
        $clicks = (int) AdMetric::sum('clicks');
        $ctr = $impressions > 0 ? round($clicks / $impressions * 100, 2) : null;

        $sent = (int) Campaign::sum('stat_sent');
        $opened = (int) Campaign::sum('stat_opened');
        $openRate = $sent > 0 ? round($opened / $sent * 100, 1) : null;

        $lines = array_filter([
            __('What :n prospect relationships taught us this year', ['n' => $contacts]),
            '',
            __('Findings from our own operating data:'),
            $winRate !== null ? __('- :won of :total tracked opportunities closed won (:rate% win rate).', ['won' => $wonDeals, 'total' => $totalDeals, 'rate' => $winRate]) : null,
            $avgDeal !== null ? __('- Average won engagement: $:v.', ['v' => number_format($avgDeal / 100, 2)]) : null,
            $avgRating !== null ? __('- :n client reviews averaging :avg/5.', ['n' => $reviews, 'avg' => $avgRating]) : null,
            $ctr !== null ? __('- Search/social ads: :ctr% CTR over :imp impressions.', ['ctr' => $ctr, 'imp' => number_format($impressions)]) : null,
            $openRate !== null ? __('- Email: :rate% open rate across :sent sends.', ['rate' => $openRate, 'sent' => number_format($sent)]) : null,
            '',
            __('Methodology & provenance: every figure above is computed live from this organization\'s own records in Piotrack at draft time. No external, estimated or industry-average numbers are included. Add narrative and publish only what you are comfortable disclosing.'),
        ], fn ($l) => $l !== null);

        return ContentPiece::create([
            'title' => __('By the numbers: what :n client relationships taught us', ['n' => $contacts]),
            'slug' => 'research-story-'.now()->format('Ymd-His'),
            'content_type' => 'article',
            'funnel_stage' => 'tof',
            'status' => 'draft',
            'body' => implode("\n", $lines),
            'excerpt' => __('Original research from our own operating data — :n relationships, real outcomes.', ['n' => $contacts]),
        ]);
    }
}
