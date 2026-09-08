<?php

namespace App\Services\Strategy;

use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Engagement;
use Illuminate\Support\Carbon;

/**
 * PROJ-015/016: the review DATA PACK — period-bounded facts compiled fresh
 * from real records for a monthly review (QBR) or quarterly strategy review.
 * The platform contributes the numbers; the review itself stays
 * human-delivered consulting, exactly as the register says.
 */
class ReviewPacketService
{
    public function __construct(private KpiTargetService $targets) {}

    /**
     * The engagement's review period: the month around a QBR, the quarter
     * around a strategy review (anchored on scheduled_at, else today).
     *
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public function periodFor(Engagement $engagement): array
    {
        $anchor = $engagement->scheduled_at ?? now();

        if ($engagement->type === 'strategy_review') {
            return [
                'start' => $anchor->copy()->startOfQuarter(),
                'end' => $anchor->copy()->endOfQuarter(),
                'label' => 'Q'.$anchor->quarter.' '.$anchor->year,
            ];
        }

        return [
            'start' => $anchor->copy()->startOfMonth(),
            'end' => $anchor->copy()->endOfMonth(),
            'label' => $anchor->format('F Y'),
        ];
    }

    /**
     * PDF-ready lines, every figure period-bounded and from records.
     *
     * @return list<array<string, mixed>>
     */
    public function lines(Engagement $engagement): array
    {
        $period = $this->periodFor($engagement);
        [$start, $end] = [$period['start'], $period['end']];

        $newLeads = Contact::whereBetween('created_at', [$start, $end])->count();
        $mqls = Contact::whereBetween('created_at', [$start, $end])->where('lifecycle_stage', 'mql')->count();
        $wonDeals = Deal::where('status', 'won')->whereBetween('closed_at', [$start, $end])->get();
        $lostDeals = Deal::where('status', 'lost')->whereBetween('closed_at', [$start, $end])->count();
        $campaignsSent = Campaign::whereBetween('sent_at', [$start, $end])->count();
        $bookings = Booking::whereBetween('scheduled_at', [$start, $end])->count();

        $money = fn (int $minor) => '$'.number_format($minor / 100, 2);

        $lines = [
            ['text' => 'Review period: '.$period['label'].' ('.$start->toDateString().' - '.$end->toDateString().')', 'size' => 10],
            ['text' => ''],
            ['text' => 'Pipeline', 'size' => 13, 'bold' => true],
            ['text' => 'New leads: '.$newLeads],
            ['text' => 'New MQLs: '.$mqls],
            ['text' => 'Deals won: '.$wonDeals->count().' ('.$money((int) $wonDeals->sum(fn (Deal $d) => (int) $d->value)).' value, '.$money((int) $wonDeals->sum(fn (Deal $d) => (int) $d->mrr)).' MRR)'],
            ['text' => 'Deals lost: '.$lostDeals],
            ['text' => ''],
            ['text' => 'Activity', 'size' => 13, 'bold' => true],
            ['text' => 'Campaigns sent: '.$campaignsSent],
            ['text' => 'Meetings booked: '.$bookings],
            ['text' => ''],
            ['text' => 'KPI attainment', 'size' => 13, 'bold' => true],
        ];

        $attainment = $this->targets->attainment();
        if ($attainment === []) {
            $lines[] = ['text' => '(no KPI targets set)', 'size' => 9];
        }
        foreach ($attainment as $row) {
            $lines[] = ['text' => sprintf('%s: %s of %s (%s%%)%s',
                (string) $row['metric'], (string) $row['actual'], (string) $row['target'],
                (string) $row['attainment'], $row['on_track'] ? ' - on track' : '')];
        }

        $lines[] = ['text' => ''];
        $lines[] = ['text' => 'Every figure above is computed from platform records for the stated period.', 'size' => 8];

        return $lines;
    }
}
