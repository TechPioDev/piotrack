<?php

namespace App\Services\Analytics;

use App\Models\Booking;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Experiment;
use App\Models\SitePage;

/**
 * Step-level funnel analysis and conversion-path aggregation (CRO-012/013),
 * plus the optimization recommendations built on them (CRO-015/016). Every
 * number is computed from the tenant's records; every recommendation cites the
 * number that triggered it; too little data says so instead of guessing.
 */
class FunnelInsights
{
    /** Fewer leads than this and step conversions would be noise. */
    public const MIN_LEADS = 10;

    /** Lifecycle stages that mean the contact reached MQL, in order. */
    private const MQL_PLUS = ['mql', 'sql', 'opportunity', 'customer', 'evangelist'];

    private const SQL_PLUS = ['sql', 'opportunity', 'customer', 'evangelist'];

    public function __construct(
        private AttributionService $attribution,
        private BehaviorAnalytics $behavior,
    ) {}

    /**
     * Cumulative step drop-off: each step counts everyone who REACHED it, so
     * conversions between steps are honest even though lifecycle_stage stores
     * only the current stage.
     *
     * @return array{insufficient_data: bool, required_leads: int, steps: list<array{step: string, count: int, conversion_from_previous: int|null}>, weakest_step: string|null}
     */
    public function dropOff(): array
    {
        $counts = [
            'leads' => Contact::count(),
            'mql' => Contact::whereIn('lifecycle_stage', self::MQL_PLUS)->count(),
            'sql' => Contact::whereIn('lifecycle_stage', self::SQL_PLUS)->count(),
            'meetings' => Booking::count(),
            'won' => Deal::where('status', 'won')->count(),
        ];

        $insufficient = $counts['leads'] < self::MIN_LEADS;

        $steps = [];
        $previous = null;
        $weakest = null;
        $weakestRate = null;
        foreach ($counts as $step => $count) {
            $rate = null;
            if (! $insufficient && $previous !== null) {
                $rate = $previous['count'] > 0 ? (int) round($count / $previous['count'] * 100) : 0;
                if ($weakestRate === null || $rate < $weakestRate) {
                    $weakestRate = $rate;
                    $weakest = $previous['step'].' → '.$step;
                }
            }
            $steps[] = $previous = ['step' => $step, 'count' => $count, 'conversion_from_previous' => $rate];
        }

        return [
            'insufficient_data' => $insufficient,
            'required_leads' => self::MIN_LEADS,
            'steps' => $steps,
            'weakest_step' => $insufficient ? null : $weakest,
        ];
    }

    /**
     * Aggregate conversion paths (CRO-013): first-touch → last-touch channel
     * pairs for contacts on closed-won deals, most common first.
     *
     * @return list<array{path: string, wins: int}>
     */
    public function conversionPaths(): array
    {
        $contactIds = Deal::where('status', 'won')->whereNotNull('contact_id')->pluck('contact_id')->unique();

        $paths = [];
        foreach (Contact::whereIn('id', $contactIds)->get() as $contact) {
            $key = $this->attribution->firstTouch($contact).' → '.$this->attribution->lastTouch($contact);
            $paths[$key] = ($paths[$key] ?? 0) + 1;
        }
        arsort($paths);

        return collect($paths)->map(fn (int $wins, string $path) => ['path' => $path, 'wins' => $wins])
            ->values()->all();
    }

    /**
     * CRO-015/016: optimization recommendations that name the platform action
     * AND the number that triggered it. No data, no advice.
     *
     * @return list<array{area: string, evidence: string, action: string}>
     */
    public function recommendations(): array
    {
        $out = [];
        $funnel = $this->dropOff();

        if ($funnel['insufficient_data']) {
            return [[
                'area' => 'data',
                'evidence' => 'Fewer than '.self::MIN_LEADS.' leads captured so far.',
                'action' => 'Publish pages and forms first — recommendations start once real funnel data exists.',
            ]];
        }

        $byStep = collect($funnel['steps'])->keyBy('step');

        if ($funnel['weakest_step'] === 'sql → meetings') {
            $out[] = [
                'area' => 'meeting_conversion',
                'evidence' => 'Only '.$byStep['meetings']['conversion_from_previous'].'% of SQLs get to a meeting ('.$byStep['sql']['count'].' SQLs, '.$byStep['meetings']['count'].' meetings).',
                'action' => 'Put the booking page link in every SQL touch and enable no-show rebooking automation (Sales → Booking).',
            ];
        }

        if ($funnel['weakest_step'] === 'leads → mql' || $funnel['weakest_step'] === 'mql → sql') {
            $out[] = [
                'area' => 'lead_conversion',
                'evidence' => $funnel['weakest_step'].' converts at '.($byStep[explode(' → ', $funnel['weakest_step'])[1]]['conversion_from_previous']).'% — the weakest step in the funnel.',
                'action' => 'Tighten scoring rules and nurture workflows for that stage (Marketing → Workflows), and review what the winning paths below have in common.',
            ];
        }

        // High-bounce landing pages are lead-conversion leaks with a named fix.
        foreach ($this->behavior->bounceRates() as $row) {
            if (! $row['insufficient'] && $row['bounce_rate'] !== null && $row['bounce_rate'] >= 70) {
                $out[] = [
                    'area' => 'bounce',
                    'evidence' => $row['landing_path'].' bounces '.$row['bounce_rate'].'% of '.$row['sessions'].' sessions.',
                    'action' => 'Bind an experiment to that page (Analytics → Experiments) and fix its failing health checks (Website → Pages).',
                ];
            }
        }

        // A live funnel with nothing running on it is optimization left idle.
        $running = Experiment::where('status', 'running')->count();
        $published = SitePage::where('status', SitePage::STATUS_PUBLISHED)->count();
        if ($running === 0 && $published > 0) {
            $out[] = [
                'area' => 'experiments',
                'evidence' => $published.' published pages and 0 running experiments.',
                'action' => 'Start a headline experiment on the highest-traffic page — bound experiments split live traffic automatically.',
            ];
        }

        if ($out === []) {
            $out[] = [
                'area' => 'healthy',
                'evidence' => 'No step is underperforming its neighbours and no landing page bounces above 70%.',
                'action' => 'Keep the running experiments going and revisit after more traffic accumulates.',
            ];
        }

        return $out;
    }
}
