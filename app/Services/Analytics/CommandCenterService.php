<?php

namespace App\Services\Analytics;

use App\Models\Booking;
use App\Models\ChatConversation;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\SalesAlert;
use App\Services\Sales\LeadScoringService;
use Illuminate\Support\Carbon;

/**
 * The Growth Command Center's period-scoped numbers (CMDC module).
 *
 * Windowing honesty: only flows with a real timestamp are compared across
 * periods (leads by created_at, meetings by created_at, wins by closed_at).
 * Stocks like pipeline and ARR, and states without a promotion timestamp like
 * SQL counts, are reported as-is with no delta — a delta computed from
 * updated_at or guesswork would be a lie. Delta is null when the previous
 * period is zero: "new", not "+∞%".
 */
class CommandCenterService
{
    public const DEFAULT_WINDOW_DAYS = 30;

    /** User-selectable comparison windows (DSGN-006). */
    public const WINDOWS = [30, 60, 90];

    private int $windowDays = self::DEFAULT_WINDOW_DAYS;

    public function __construct(private LeadScoringService $scoring) {}

    /**
     * Scope every windowed number to this many days (DSGN-006): the KPIs
     * compare the selected window against the one before it, and the trends
     * span exactly the same days.
     */
    public function forWindow(int $days): static
    {
        $this->windowDays = in_array($days, self::WINDOWS, true) ? $days : self::DEFAULT_WINDOW_DAYS;

        return $this;
    }

    /**
     * @return array{
     *   new_leads: array{value: int, previous: int, delta_pct: float|null},
     *   meetings: array{value: int, previous: int, delta_pct: float|null},
     *   deals_won: array{value: int, previous: int, delta_pct: float|null},
     *   new_mrr: array{value: int, previous: int, delta_pct: float|null},
     *   qualified_pipeline: int,
     *   sqls: int,
     *   arr: int,
     * }
     */
    public function kpis(): array
    {
        [$from, $mid] = [$this->windowStart(2), $this->windowStart(1)];

        $wonInWindow = fn (Carbon $start, Carbon $end) => Deal::where('status', 'won')
            ->whereNotNull('closed_at')->where('closed_at', '>=', $start)->where('closed_at', '<', $end);

        return [
            'new_leads' => $this->compared(
                Contact::where('created_at', '>=', $mid)->count(),
                Contact::where('created_at', '>=', $from)->where('created_at', '<', $mid)->count(),
            ),
            'meetings' => $this->compared(
                Booking::where('created_at', '>=', $mid)->count(),
                Booking::where('created_at', '>=', $from)->where('created_at', '<', $mid)->count(),
            ),
            'deals_won' => $this->compared(
                $wonInWindow($mid, now()->addMinute())->count(),
                $wonInWindow($from, $mid)->count(),
            ),
            'new_mrr' => $this->compared(
                (int) $wonInWindow($mid, now()->addMinute())->sum('mrr'),
                (int) $wonInWindow($from, $mid)->sum('mrr'),
            ),
            'qualified_pipeline' => (int) Deal::where('status', 'open')->sum('value'),
            'sqls' => Contact::where('lifecycle_stage', 'sql')->count(),
            'arr' => (int) Deal::where('status', 'won')->sum('arr'),
        ];
    }

    /**
     * New contacts per day across the current window.
     *
     * @return list<array{label: string, value: int}>
     */
    public function leadTrend(): array
    {
        $since = $this->windowStart(1);
        $byDay = Contact::where('created_at', '>=', $since)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return $this->days($since, fn (Carbon $day) => (int) ($byDay[$day->toDateString()] ?? 0));
    }

    /**
     * Won MRR accumulated across the current window, day by day — the line
     * answers "how much recurring revenue has this month added so far?".
     *
     * @return list<array{label: string, value: int}>
     */
    public function mrrTrend(): array
    {
        $since = $this->windowStart(1);
        $byDay = Deal::where('status', 'won')
            ->whereNotNull('closed_at')->where('closed_at', '>=', $since)
            ->selectRaw('date(closed_at) as day, sum(mrr) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $running = 0;

        return $this->days($since, function (Carbon $day) use (&$running, $byDay) {
            $running += (int) ($byDay[$day->toDateString()] ?? 0);

            return $running;
        });
    }

    /**
     * What needs a human right now.
     *
     * @return array{alerts: list<array{id: int, type: string, message: string, contact: string|null}>, waiting_chats: int, hot_leads: int}
     */
    public function attention(): array
    {
        return [
            'alerts' => SalesAlert::with('contact:id,first_name,last_name')
                ->where('is_read', false)->latest('id')->limit(5)->get()
                ->map(fn (SalesAlert $a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'message' => $a->message,
                    'contact' => $a->contact?->fullName(),
                ])->all(),
            'waiting_chats' => ChatConversation::where('is_preview', false)->where('status', 'waiting')->count(),
            'hot_leads' => Contact::query()->get(['id', 'lead_score'])
                ->filter(fn (Contact $c) => $this->scoring->temperature((int) $c->lead_score) === 'hot')
                ->count(),
        ];
    }

    /**
     * The biggest open deals — where attention pays most.
     *
     * @return list<array{id: int, name: string, stage: string|null, value: int}>
     */
    public function topDeals(int $limit = 5): array
    {
        return Deal::with('stage:id,name')
            ->where('status', 'open')
            ->orderByDesc('value')
            ->limit($limit)
            ->get()
            ->map(fn (Deal $deal) => [
                'id' => $deal->id,
                'name' => $deal->name,
                'stage' => $deal->stage?->name,
                'value' => (int) $deal->value,
            ])->all();
    }

    /**
     * @return array{value: int, previous: int, delta_pct: float|null}
     */
    private function compared(int $current, int $previous): array
    {
        return [
            'value' => $current,
            'previous' => $previous,
            'delta_pct' => $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null,
        ];
    }

    /**
     * Window boundaries in whole calendar days, today included: the current
     * window starts 29 days ago at midnight, the previous one 59 days ago —
     * so trends and KPI deltas describe exactly the same 30 days.
     */
    private function windowStart(int $windowsBack): Carbon
    {
        return now()->subDays($this->windowDays * $windowsBack - 1)->startOfDay();
    }

    /**
     * @param  callable(Carbon): int  $value
     * @return list<array{label: string, value: int}>
     */
    private function days(Carbon $since, callable $value): array
    {
        return collect(range(0, $this->windowDays - 1))
            ->map(function (int $offset) use ($since, $value) {
                $day = $since->copy()->addDays($offset);

                return ['label' => $day->format('M j'), 'value' => $value($day)];
            })
            ->all();
    }
}
