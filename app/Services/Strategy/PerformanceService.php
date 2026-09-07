<?php

namespace App\Services\Strategy;

use App\Models\AdMetric;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Deliverable;
use App\Models\LeadReplacement;
use App\Models\PerformanceAgreement;
use App\Models\PerformanceReview;
use App\Services\Analytics\AnalyticsService;
use App\Support\AuditLogger;

/**
 * Lead-guarantee / performance model (PERF). Attainment is computed from the
 * real Stage 11 funnel, so a guarantee is measured against actual delivery
 * rather than a self-reported number. Replaced leads are excluded from the
 * delivered count — a lead that failed the quality bar does not count twice.
 */
class PerformanceService
{
    public function __construct(
        private AnalyticsService $analytics,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PerformanceAgreement
    {
        // Column defaults are not hydrated onto the in-memory model after create(),
        // so anything read back in the same request must be defaulted here.
        $data['status'] ??= 'active';
        $data['model'] ??= 'guarantee';
        $data['sla_days'] ??= 30;
        $data['lead_target'] ??= 0;
        $data['sql_target'] ??= 0;
        $data['meeting_target'] ??= 0;

        $agreement = PerformanceAgreement::create($data);

        $this->audit->log('performance.agreement.created', context: ['name' => $agreement->name, 'model' => $agreement->model],
            resourceType: 'performance_agreement', resourceId: (string) $agreement->id);

        return $agreement;
    }

    /**
     * Attainment against the agreement's targets, net of replaced leads.
     *
     * @return array<string, mixed>
     */
    public function attainment(PerformanceAgreement $agreement): array
    {
        $funnel = $this->analytics->funnel();
        $replaced = LeadReplacement::where('performance_agreement_id', $agreement->id)->count();

        $leads = max(0, $funnel['leads'] - $replaced);
        $sqls = $funnel['sqls'];
        $meetings = $funnel['meetings'];

        $rows = [
            'leads' => $this->row($leads, $agreement->lead_target),
            'sqls' => $this->row($sqls, $agreement->sql_target),
            'meetings' => $this->row($meetings, $agreement->meeting_target),
        ];

        $met = collect($rows)->every(fn (array $r) => $r['met']);

        return [
            'targets' => $rows,
            'replaced_leads' => $replaced,
            'all_targets_met' => $met,
            // Only a live agreement whose window has closed can be judged breached.
            'status' => $this->status($agreement, $met),
            'sla_days' => $agreement->sla_days,
            'period' => [
                'start' => $agreement->period_start?->toDateString(),
                'end' => $agreement->period_end?->toDateString(),
            ],
        ];
    }

    /**
     * Does a contact meet the agreement's lead-quality criteria (PERF-007/009)?
     * Criteria are simple attribute expectations; an empty criteria set accepts.
     */
    public function meetsQualityCriteria(PerformanceAgreement $agreement, Contact $contact): bool
    {
        /** @var array<string, mixed> $criteria */
        $criteria = $agreement->quality_criteria ?? [];

        foreach ($criteria as $attribute => $expected) {
            $actual = match ($attribute) {
                'min_lead_score' => $contact->lead_score,
                'lifecycle_stage' => $contact->lifecycle_stage,
                'has_company' => $contact->company_id !== null,
                'has_phone' => $contact->phone !== null && $contact->phone !== '',
                default => null,
            };

            $ok = match ($attribute) {
                'min_lead_score' => is_numeric($actual) && $actual >= (int) $expected,
                'has_company', 'has_phone' => (bool) $actual === (bool) $expected,
                default => $actual === $expected,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * Record a replaced lead under the guarantee (PERF-008).
     */
    public function replaceLead(PerformanceAgreement $agreement, Contact $contact, string $reason, ?Contact $replacement = null): LeadReplacement
    {
        return LeadReplacement::create([
            'performance_agreement_id' => $agreement->id,
            'contact_id' => $contact->id,
            'replacement_contact_id' => $replacement?->id,
            'reason' => $reason,
            'replaced_at' => now(),
        ]);
    }

    /**
     * @return array{actual: int, target: int, attainment: float, met: bool}
     */
    private function row(int $actual, int $target): array
    {
        return [
            'actual' => $actual,
            'target' => $target,
            'attainment' => $target > 0 ? round($actual / $target * 100, 2) : 0.0,
            // A target of zero is not a promise, so it cannot be missed.
            'met' => $target === 0 || $actual >= $target,
        ];
    }

    /**
     * PERF-004: promised deliverables (stored on the agreement) reconciled
     * against real project deliverables by normalized title. Automatic — the
     * report says per item whether something matching was delivered and
     * whether it cleared approval.
     *
     * @return array{items: list<array{promised: string, matched: string|null, status: string|null, approved: bool, delivered: bool}>, promised: int, delivered: int, approved: int, all_delivered: bool}
     */
    public function reconcileDeliverables(PerformanceAgreement $agreement): array
    {
        $promised = $agreement->deliverables ?? [];
        $actual = Deliverable::orderBy('id')->get();

        $items = [];
        foreach ($promised as $title) {
            $needle = mb_strtolower(trim($title));
            $match = $actual->first(function (Deliverable $d) use ($needle) {
                $have = mb_strtolower(trim($d->title));

                return $have === $needle || str_contains($have, $needle) || str_contains($needle, $have);
            });

            $delivered = $match !== null && in_array($match->status, ['delivered', 'complete', 'completed'], true);
            $approved = $match !== null && $match->approval_status === 'approved';

            $items[] = [
                'promised' => $title,
                'matched' => $match?->title,
                'status' => $match?->status,
                'approved' => $approved,
                // An approved deliverable counts as delivered even if its
                // status label lags — the client sign-off is the stronger fact.
                'delivered' => $delivered || $approved,
            ];
        }

        $deliveredCount = count(array_filter($items, fn (array $i) => $i['delivered']));

        return [
            'items' => $items,
            'promised' => count($items),
            'delivered' => $deliveredCount,
            'approved' => count(array_filter($items, fn (array $i) => $i['approved'])),
            'all_delivered' => $items !== [] && $deliveredCount === count($items),
        ];
    }

    /**
     * PERF-011: generate and store the formal ROI review for the agreement's
     * period — attainment, deliverable reconciliation, revenue closed in the
     * window, ad spend in the window, and the guarded ROI ratio.
     */
    public function generateRoiReview(PerformanceAgreement $agreement): PerformanceReview
    {
        $start = $agreement->period_start;
        $endOfDay = $agreement->period_end?->copy()->endOfDay();
        $endDate = $agreement->period_end?->toDateString();

        $revenue = (int) Deal::where('status', 'won')
            ->when($start !== null, fn ($q) => $q->where('closed_at', '>=', $start))
            ->when($endOfDay !== null, fn ($q) => $q->where('closed_at', '<=', $endOfDay))
            ->sum('value');

        $spend = (int) AdMetric::query()
            ->when($start !== null, fn ($q) => $q->where('date', '>=', $start?->toDateString()))
            ->when($endDate !== null, fn ($q) => $q->where('date', '<=', $endDate))
            ->sum('spend');

        $review = PerformanceReview::create([
            'performance_agreement_id' => $agreement->id,
            'period_start' => $start?->toDateString(),
            'period_end' => $endDate,
            'data' => [
                'attainment' => $this->attainment($agreement),
                'deliverables' => $this->reconcileDeliverables($agreement),
                'won_revenue' => $revenue,
                'ad_spend' => $spend,
                // Guarded: no spend recorded means no ratio, never infinity.
                'roi' => $spend > 0 ? round($revenue / $spend, 2) : null,
            ],
        ]);

        $this->audit->log('strategy.performance.roi_review', context: ['agreement' => $agreement->name, 'revenue' => $revenue, 'spend' => $spend], resourceType: 'performance_review', resourceId: (string) $review->id, organizationId: $review->organization_id);

        return $review;
    }

    private function status(PerformanceAgreement $agreement, bool $met): string
    {
        if ($agreement->status !== 'active') {
            return $agreement->status;
        }

        $ended = $agreement->period_end !== null && $agreement->period_end->isPast();

        return $ended ? ($met ? 'completed' : 'breached') : 'in_progress';
    }
}
