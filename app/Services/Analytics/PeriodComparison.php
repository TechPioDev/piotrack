<?php

namespace App\Services\Analytics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * "This window against the one before it" for dashboard tiles (UI-P3).
 *
 * The same honesty rule as the command center: compare only flows with a real
 * event timestamp (sent_at, enrolled_at, created_at of a submission). Stocks
 * get no delta. The current window ends now and starts `days - 1` days ago at
 * midnight, so it covers exactly the days a 30-day trend chart draws.
 */
class PeriodComparison
{
    public function __construct(private int $days = CommandCenterService::DEFAULT_WINDOW_DAYS) {}

    /**
     * Delta is null when the previous window is zero: "new", not "+∞%".
     *
     * @return array{value: int, previous: int, delta_pct: float|null}
     */
    public static function of(int $current, int $previous): array
    {
        return [
            'value' => $current,
            'previous' => $previous,
            'delta_pct' => $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null,
        ];
    }

    /** Midnight at the start of the window `$windowsBack` windows ago (1 = current). */
    public function windowStart(int $windowsBack): Carbon
    {
        return now()->subDays($this->days * $windowsBack - 1)->startOfDay();
    }

    /**
     * Rows whose `$column` falls in the current window, against the previous one.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array{value: int, previous: int, delta_pct: float|null}
     */
    public function count(Builder $query, string $column): array
    {
        $current = $this->windowStart(1);
        $previous = $this->windowStart(2);

        return self::of(
            (clone $query)->where($column, '>=', $current)->count(),
            (clone $query)->where($column, '>=', $previous)->where($column, '<', $current)->count(),
        );
    }
}
