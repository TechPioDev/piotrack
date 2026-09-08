<?php

namespace App\Services\Strategy;

use App\Models\Booking;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Deal;
use App\Models\LandingPage;
use App\Models\Visitor;

/**
 * STRAT-009: the buyer journey as the platform actually measures it — four
 * stages, each carrying real counts from the funnel chain, the published
 * content covering it (tof/mof/bof bindings), and conversion rates between
 * stages. The narrative interpretation stays human; the map itself is data.
 */
class JourneyMapService
{
    /**
     * @return array{stages: list<array<string, mixed>>, avg_days_to_close: ?float, capture_pages: int}
     */
    public function map(): array
    {
        $visitors = Visitor::count();
        $identified = Visitor::whereNotNull('contact_id')->count();
        $leads = Contact::count();
        $mqls = Contact::where('lifecycle_stage', 'mql')->count();
        $sqls = Contact::where('lifecycle_stage', 'sql')->count();
        $meetings = Booking::count();
        $open = Deal::where('status', 'open')->count();
        $won = Deal::where('status', 'won')->count();
        $lost = Deal::where('status', 'lost')->count();

        $content = ContentPiece::whereNotNull('published_at')->get();
        $contentBy = fn (string $stage): int => $content->where('funnel_stage', $stage)->count();

        $rate = fn (int $part, int $whole): ?float => $whole > 0 ? round($part / $whole * 100, 1) : null;

        $wonDeals = Deal::where('status', 'won')->whereNotNull('closed_at')->get();
        $avgDays = $wonDeals->isEmpty() ? null : round($wonDeals->avg(
            fn (Deal $d) => $d->created_at !== null && $d->closed_at !== null
                ? abs($d->closed_at->diffInMinutes($d->created_at)) / 1440
                : 0.0
        ), 1);

        return [
            'stages' => [
                [
                    'stage' => 'awareness',
                    'funnel_stage' => 'tof',
                    'metrics' => ['visitors' => $visitors, 'identified' => $identified],
                    'published_content' => $contentBy('tof'),
                    'conversion' => ['label' => 'visitor → identified lead', 'rate' => $rate($identified, $visitors)],
                ],
                [
                    'stage' => 'consideration',
                    'funnel_stage' => 'mof',
                    'metrics' => ['leads' => $leads, 'mqls' => $mqls],
                    'published_content' => $contentBy('mof'),
                    'conversion' => ['label' => 'lead → MQL', 'rate' => $rate($mqls, $leads)],
                ],
                [
                    'stage' => 'decision',
                    'funnel_stage' => 'bof',
                    'metrics' => ['sqls' => $sqls, 'meetings' => $meetings, 'open_deals' => $open],
                    'published_content' => $contentBy('bof'),
                    'conversion' => ['label' => 'MQL → SQL', 'rate' => $rate($sqls, $mqls)],
                ],
                [
                    'stage' => 'customer',
                    'funnel_stage' => null,
                    'metrics' => ['won' => $won, 'lost' => $lost],
                    'published_content' => 0,
                    'conversion' => ['label' => 'closed deal win rate', 'rate' => $rate($won, $won + $lost)],
                ],
            ],
            'avg_days_to_close' => $avgDays,
            'capture_pages' => LandingPage::whereNotNull('form_id')->count(),
        ];
    }
}
