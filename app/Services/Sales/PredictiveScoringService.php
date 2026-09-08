<?php

namespace App\Services\Sales;

use App\Models\Contact;
use App\Models\Deal;
use Illuminate\Support\Collection;

/**
 * LSCR-014: predictive scoring the honest way — empirical win rates from the
 * tenant's OWN closed history, bucketed by lead source, company industry and
 * deterministic score band. Every factor names its sample size; buckets below
 * their own floor are skipped; and below the model floor the whole model
 * REFUSES with the counts instead of dressing thin data in statistics.
 * Advisory only — it never touches the deterministic lead score.
 */
class PredictiveScoringService
{
    /** Closed deals required before the model speaks at all. */
    public const MODEL_FLOOR = 30;

    /** Closed deals a single bucket needs before its rate counts. */
    public const BUCKET_FLOOR = 5;

    /**
     * @return array{active: bool, closed_deals: int, needed: int, baseline: float|null}
     */
    public function status(): array
    {
        $closed = Deal::whereIn('status', ['won', 'lost'])->count();
        $won = Deal::where('status', 'won')->count();

        return [
            'active' => $closed >= self::MODEL_FLOOR,
            'closed_deals' => $closed,
            'needed' => self::MODEL_FLOOR,
            'baseline' => $closed > 0 ? round($won / $closed * 100, 1) : null,
        ];
    }

    /**
     * The empirical close probability for a contact, or an explicit refusal.
     *
     * @return array{insufficient_data: bool, probability: float|null, factors: list<array{factor: string, rate: float, sample: int}>}
     */
    public function predict(Contact $contact): array
    {
        $status = $this->status();
        if (! $status['active']) {
            return ['insufficient_data' => true, 'probability' => null, 'factors' => []];
        }

        $closed = Deal::with('contact:id,lead_source,lead_score,company_id', 'contact.company:id,industry')
            ->whereIn('status', ['won', 'lost'])->get();

        $factors = [];

        $bySource = $this->bucketRate($closed, fn (Deal $d) => $d->contact?->lead_source, $contact->lead_source);
        if ($bySource !== null) {
            $factors[] = ['factor' => 'lead source "'.$contact->lead_source.'"', 'rate' => $bySource['rate'], 'sample' => $bySource['sample']];
        }

        $industry = $contact->company_id !== null ? $contact->company?->industry : null;
        $byIndustry = $this->bucketRate($closed, fn (Deal $d) => $d->contact?->company?->industry, $industry);
        if ($byIndustry !== null) {
            $factors[] = ['factor' => 'industry "'.$industry.'"', 'rate' => $byIndustry['rate'], 'sample' => $byIndustry['sample']];
        }

        $band = fn (?int $score) => $score === null ? null : ($score >= LeadScoringService::HOT ? 'hot' : ($score >= LeadScoringService::WARM ? 'warm' : 'cold'));
        $byBand = $this->bucketRate($closed, fn (Deal $d) => $band($d->contact?->lead_score), $band((int) $contact->lead_score));
        if ($byBand !== null) {
            $factors[] = ['factor' => 'score band "'.$band((int) $contact->lead_score).'"', 'rate' => $byBand['rate'], 'sample' => $byBand['sample']];
        }

        // Sample-weighted blend of the factors that met their floors; with none,
        // the tenant-wide baseline is the honest answer.
        if ($factors === []) {
            return ['insufficient_data' => false, 'probability' => $status['baseline'], 'factors' => []];
        }

        $weighted = 0.0;
        $weight = 0;
        foreach ($factors as $factor) {
            $weighted += $factor['rate'] * $factor['sample'];
            $weight += $factor['sample'];
        }

        return [
            'insufficient_data' => false,
            'probability' => round($weighted / $weight, 1),
            'factors' => $factors,
        ];
    }

    /**
     * @param  Collection<int, Deal>  $closed
     * @return array{rate: float, sample: int}|null
     */
    private function bucketRate($closed, callable $keyOf, mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $bucket = $closed->filter(fn (Deal $d) => $keyOf($d) === $value);
        if ($bucket->count() < self::BUCKET_FLOOR) {
            return null;
        }

        return [
            'rate' => round($bucket->where('status', 'won')->count() / $bucket->count() * 100, 1),
            'sample' => $bucket->count(),
        ];
    }
}
