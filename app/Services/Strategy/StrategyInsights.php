<?php

namespace App\Services\Strategy;

use App\Models\AdCampaign;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\FormSubmission;
use App\Models\Funnel;
use App\Models\Keyword;
use App\Models\LandingPage;
use App\Models\SeoAudit;
use App\Models\Visitor;
use App\Services\Analytics\CompetitiveService;

/**
 * Computed strategy analyses (STRAT-001..004, 006, 010..013, 017..023): every
 * number here is derived from the tenant's own records — keywords, rankings,
 * visitors, deals, campaigns, funnels, audits. Where the data is too thin to
 * support a conclusion the block says so (insufficient_data) instead of
 * inventing one; the qualitative work (personas, pain points, journeys,
 * messaging, TAM) stays in the strategy workspace as human items.
 */
class StrategyInsights
{
    /** Closed deals required before win-rate projections are shown (STRAT-023). */
    public const MIN_CLOSED_FOR_MODEL = 5;

    public function __construct(private CompetitiveService $competitive) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [
            'keyword_opportunities' => $this->keywordOpportunities(),
            'competitor_landscape' => $this->competitorLandscape(),
            'geo_markets' => $this->geoMarkets(),
            'vertical_performance' => $this->verticalPerformance(),
            'conversion_audit' => $this->conversionAudit(),
            'crm_hygiene' => $this->crmHygiene(),
            'funnel_audit' => $this->funnelAudit(),
            'ppc_audit' => $this->ppcAudit(),
            'lead_gen_gaps' => $this->leadGenGaps(),
            'lead_channels' => $this->leadChannels(),
            'revenue_model' => $this->revenueModel(),
            'icp_profile' => $this->icpProfile(),
            'seo_audit_summary' => $this->seoAuditSummary(),
            'assessment' => $this->assessment(),
        ];
    }

    /**
     * STRAT-010/011: where search demand exists that we do not rank for.
     *
     * @return array{intent_mix: array<string, int>, opportunities: list<array<string, mixed>>}
     */
    public function keywordOpportunities(): array
    {
        $keywords = Keyword::where('is_tracked', true)->get();

        $opportunities = $keywords
            ->filter(fn (Keyword $k) => $k->current_position === null || $k->current_position > 10)
            ->sortBy([['search_volume', 'desc'], ['difficulty', 'asc']])
            ->take(10)
            ->map(fn (Keyword $k) => [
                'phrase' => $k->phrase,
                'search_volume' => $k->search_volume,
                'difficulty' => $k->difficulty,
                'position' => $k->current_position,
                'intent' => $k->intent,
                'location' => $k->location,
            ])->values()->all();

        return [
            'intent_mix' => $keywords->countBy('intent')->all(),
            'opportunities' => $opportunities,
        ];
    }

    /**
     * STRAT-003/004: the measured competitive landscape.
     *
     * @return array<string, mixed>
     */
    public function competitorLandscape(): array
    {
        $headToHead = collect($this->competitive->keywordHeadToHead());
        $measured = $headToHead->filter(fn (array $row) => $row['leading'] !== null);

        return [
            'share_of_voice' => $this->competitive->shareOfVoice(),
            'keywords_measured' => $measured->count(),
            'keywords_leading' => $measured->where('leading', true)->count(),
            'ai_share' => $this->competitive->aiRecommendationShare(),
        ];
    }

    /**
     * STRAT-012: per-market keyword footprint from the geo pipeline.
     *
     * @return list<array{market: string, keywords: int, avg_position: ?float, opportunities: int}>
     */
    public function geoMarkets(): array
    {
        return Keyword::whereNotNull('location')->get()
            ->groupBy('location')
            ->map(function ($keywords, $market) {
                $ranked = $keywords->whereNotNull('current_position');

                return [
                    'market' => (string) $market,
                    'keywords' => $keywords->count(),
                    'avg_position' => $ranked->isEmpty() ? null : round($ranked->avg('current_position'), 1),
                    'opportunities' => $keywords->filter(fn (Keyword $k) => $k->current_position === null || $k->current_position > 10)->count(),
                ];
            })->values()->all();
    }

    /**
     * STRAT-013: how each vertical actually performs in the pipeline.
     *
     * @return list<array{industry: string, deals: int, won: int, win_rate: ?float, won_mrr: int}>
     */
    public function verticalPerformance(): array
    {
        return Deal::with('company:id,industry')->get()
            ->groupBy(fn (Deal $d) => $d->company?->industry ?? 'Unclassified')
            ->map(function ($deals, $industry) {
                $closed = $deals->whereIn('status', ['won', 'lost']);
                $won = $deals->where('status', 'won');

                return [
                    'industry' => (string) $industry,
                    'deals' => $deals->count(),
                    'won' => $won->count(),
                    'win_rate' => $closed->isEmpty() ? null : round($won->count() / $closed->count() * 100, 1),
                    'won_mrr' => (int) $won->sum('mrr'),
                ];
            })->sortByDesc('won_mrr')->values()->all();
    }

    /**
     * STRAT-017: the site's real capture chain, end to end.
     *
     * @return array<string, mixed>
     */
    public function conversionAudit(): array
    {
        $visitors = Visitor::count();
        $identified = Visitor::whereNotNull('contact_id')->count();
        $submissions = FormSubmission::count();
        $bookings = Booking::count();
        $won = Deal::where('status', 'won')->count();

        $rate = fn (int $part, int $whole): ?float => $whole > 0 ? round($part / $whole * 100, 1) : null;

        return [
            'visitors' => $visitors,
            'identified' => $identified,
            'form_submissions' => $submissions,
            'bookings' => $bookings,
            'won_deals' => $won,
            'identification_rate' => $rate($identified, $visitors),
            'visitor_to_booking_rate' => $rate($bookings, $visitors),
        ];
    }

    /**
     * STRAT-021: CRM hygiene — the audit a consultant would run by hand.
     *
     * @return array<string, int>
     */
    public function crmHygiene(): array
    {
        return [
            'contacts_without_owner' => Contact::whereNull('owner_id')->count(),
            'contacts_without_company' => Contact::whereNull('company_id')->count(),
            'contacts_without_email' => Contact::whereNull('email')->count(),
            'stale_open_deals' => Deal::where('status', 'open')->where('updated_at', '<', now()->subDays(30))->count(),
        ];
    }

    /**
     * STRAT-020: funnels with holes — stages carrying no real assets.
     *
     * @return list<array{funnel: string, stages: int, stages_without_assets: int}>
     */
    public function funnelAudit(): array
    {
        return Funnel::with('stages.assets')->get()
            ->map(fn (Funnel $funnel) => [
                'funnel' => $funnel->name,
                'stages' => $funnel->stages->count(),
                'stages_without_assets' => $funnel->stages->filter(fn ($stage) => $stage->assets->isEmpty())->count(),
            ])->values()->all();
    }

    /**
     * STRAT-019: paid campaigns judged by their own recorded metrics
     * (provider-stamped, so fixture data stays self-describing).
     *
     * @return list<array<string, mixed>>
     */
    public function ppcAudit(): array
    {
        return AdCampaign::with('metrics')->get()
            ->map(function (AdCampaign $campaign) {
                $spend = (int) $campaign->metrics->sum('spend');
                $conversions = (int) $campaign->metrics->sum('conversions');
                $revenue = (int) $campaign->metrics->sum('revenue');

                $flags = [];
                if ($spend > 0 && $conversions === 0) {
                    $flags[] = 'no_conversions';
                }
                if ($spend > 0 && $revenue < $spend) {
                    $flags[] = 'negative_roas';
                }

                return [
                    'campaign' => $campaign->name,
                    'platform' => $campaign->platform,
                    'spend' => $spend,
                    'clicks' => (int) $campaign->metrics->sum('clicks'),
                    'conversions' => $conversions,
                    'revenue' => $revenue,
                    'providers' => $campaign->metrics->pluck('provider')->filter()->unique()->values()->all(),
                    'flags' => $flags,
                ];
            })->values()->all();
    }

    /**
     * STRAT-022: demand that never becomes a lead.
     *
     * @return array{sources: list<array{source: string, visitors: int, identified: int}>, pages_without_capture: list<string>}
     */
    public function leadGenGaps(): array
    {
        $sources = Visitor::whereNotNull('utm_source')->get()
            ->groupBy('utm_source')
            ->map(fn ($visitors, $source) => [
                'source' => (string) $source,
                'visitors' => $visitors->count(),
                'identified' => $visitors->whereNotNull('contact_id')->count(),
            ])
            ->filter(fn (array $row) => $row['visitors'] >= 3 && $row['identified'] === 0)
            ->values()->all();

        $pages = LandingPage::where('view_count', '>', 0)->whereNull('form_id')
            ->pluck('name')->values()->all();

        return ['sources' => $sources, 'pages_without_capture' => $pages];
    }

    /**
     * LEAD-010..014: identified leads by derived acquisition channel — the
     * classifier is deterministic over immutable first-touch data.
     *
     * @return array<string, array{visitors: int, leads: int}>
     */
    public function leadChannels(): array
    {
        $result = [];
        foreach (Visitor::get() as $visitor) {
            $channel = $visitor->channel();
            $result[$channel] ??= ['visitors' => 0, 'leads' => 0];
            $result[$channel]['visitors']++;
            if ($visitor->contact_id !== null) {
                $result[$channel]['leads']++;
            }
        }
        ksort($result);

        return $result;
    }

    /**
     * STRAT-023: pipeline projected by the org's OWN close history — and an
     * explicit refusal to project from thin air.
     *
     * @return array<string, mixed>
     */
    public function revenueModel(): array
    {
        $won = Deal::where('status', 'won')->count();
        $lost = Deal::where('status', 'lost')->count();
        $closed = $won + $lost;

        $openDeals = Deal::where('status', 'open')->get();
        $openMrr = (int) $openDeals->sum('mrr');

        if ($closed < self::MIN_CLOSED_FOR_MODEL) {
            return [
                'insufficient_data' => true,
                'closed_deals' => $closed,
                'open_deals' => $openDeals->count(),
                'open_mrr' => $openMrr,
                'win_rate' => null,
                'projected_mrr' => null,
            ];
        }

        $winRate = $won / $closed;

        return [
            'insufficient_data' => false,
            'closed_deals' => $closed,
            'open_deals' => $openDeals->count(),
            'open_mrr' => $openMrr,
            'win_rate' => round($winRate * 100, 1),
            'projected_mrr' => (int) round($openMrr * $winRate),
        ];
    }

    /**
     * STRAT-006: the ICP your closed-won history actually describes.
     *
     * @return array<string, mixed>
     */
    public function icpProfile(): array
    {
        $wonDeals = Deal::with('company:id,industry')->where('status', 'won')->get();

        if ($wonDeals->isEmpty()) {
            return ['insufficient_data' => true, 'won_deals' => 0, 'top_industries' => [], 'top_sources' => [], 'median_mrr' => null];
        }

        $mrrs = $wonDeals->pluck('mrr')->map(fn ($m) => (int) $m)->sort()->values();
        $mid = intdiv($mrrs->count(), 2);
        $median = $mrrs->count() % 2 === 1
            ? $mrrs[$mid]
            : (int) (($mrrs[$mid - 1] + $mrrs[$mid]) / 2);

        return [
            'insufficient_data' => false,
            'won_deals' => $wonDeals->count(),
            'top_industries' => $wonDeals->groupBy(fn (Deal $d) => $d->company?->industry ?? 'Unclassified')
                ->map->count()->sortDesc()->take(3)->all(),
            'top_sources' => $wonDeals->groupBy(fn (Deal $d) => $d->lead_source ?? 'unknown')
                ->map->count()->sortDesc()->take(3)->all(),
            'median_mrr' => $median,
        ];
    }

    /**
     * STRAT-018: roll-up of stored technical audits.
     *
     * @return array<string, mixed>
     */
    public function seoAuditSummary(): array
    {
        $audits = SeoAudit::get();

        return [
            'audits' => $audits->count(),
            'avg_score' => $audits->isEmpty() ? null : round($audits->avg('score'), 1),
            'total_issues' => (int) $audits->sum('issues_count'),
            'worst_url' => $audits->sortBy('score')->first()?->url,
        ];
    }

    /**
     * STRAT-001/002: the composite assessment — issue and strength counts
     * aggregated from the computed audits above.
     *
     * @return array{issues: array<string, int>, strengths: array<string, int>}
     */
    public function assessment(): array
    {
        $hygiene = $this->crmHygiene();
        $gaps = $this->leadGenGaps();

        return [
            'issues' => [
                'crm_hygiene' => array_sum($hygiene),
                'funnel_stage_gaps' => (int) collect($this->funnelAudit())->sum('stages_without_assets'),
                'ppc_flags' => (int) collect($this->ppcAudit())->sum(fn (array $c) => count($c['flags'])),
                'unconverted_sources' => count($gaps['sources']),
                'pages_without_capture' => count($gaps['pages_without_capture']),
                'keyword_opportunities' => count($this->keywordOpportunities()['opportunities']),
            ],
            'strengths' => [
                'page_one_keywords' => Keyword::whereNotNull('current_position')->where('current_position', '<=', 10)->count(),
                'won_deals' => Deal::where('status', 'won')->count(),
                'active_funnels' => Funnel::count(),
            ],
        ];
    }
}
