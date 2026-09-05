<?php

namespace App\Services\Analytics;

use App\Models\AdMetric;
use App\Models\Call;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Deal;
use App\Models\FormSubmission;
use App\Models\SitePage;
use App\Models\Visitor;
use Illuminate\Support\Facades\DB;

/**
 * Revenue attribution (ATTR). First/last/linear multi-touch across a contact's
 * touchpoints (acquisition source + logged activities), plus channel/campaign/
 * owner revenue rollups of won deals, CAC and marketing ROI. All figures come
 * from real tenant rows; money is minor units.
 */
class AttributionService
{
    /**
     * Chronological touchpoints for a contact: the acquisition source (first) then
     * each logged activity, keyed by channel.
     *
     * @return list<array{channel: string, at: string}>
     */
    public function touchpoints(Contact $contact): array
    {
        $touches = [];

        $source = $contact->lead_source ?: 'direct';
        $touches[] = ['channel' => $source, 'at' => (string) ($contact->created_at?->toIso8601String() ?? '')];

        $activities = DB::table('activities')
            ->where('subject_type', 'contact')
            ->where('subject_id', $contact->id)
            ->orderByRaw('COALESCE(occurred_at, created_at)')
            ->get(['type', 'occurred_at', 'created_at']);

        foreach ($activities as $a) {
            $touches[] = [
                'channel' => (string) $a->type,
                'at' => (string) ($a->occurred_at ?? $a->created_at ?? ''),
            ];
        }

        return $touches;
    }

    public function firstTouch(Contact $contact): string
    {
        return $this->touchpoints($contact)[0]['channel'];
    }

    public function lastTouch(Contact $contact): string
    {
        $touches = $this->touchpoints($contact);

        return $touches[count($touches) - 1]['channel'];
    }

    /**
     * Linear multi-touch: split one unit of credit equally across all touchpoints,
     * summed per channel (values total 1.0).
     *
     * @return array<string, float>
     */
    public function multiTouch(Contact $contact): array
    {
        $touches = $this->touchpoints($contact);
        $count = count($touches);
        if ($count === 0) {
            return [];
        }

        $credit = 1 / $count;
        $out = [];
        foreach ($touches as $t) {
            $out[$t['channel']] = round(($out[$t['channel']] ?? 0) + $credit, 4);
        }

        return $out;
    }

    /**
     * Won-deal revenue grouped by acquisition channel (minor units).
     *
     * @return array<string, int>
     */
    public function channelRevenue(): array
    {
        return $this->wonRevenueBy('lead_source');
    }

    /**
     * Won-deal revenue grouped by campaign (minor units).
     *
     * @return array<string, int>
     */
    public function campaignRevenue(): array
    {
        return $this->wonRevenueBy('campaign');
    }

    /**
     * Won-deal revenue grouped by sales owner (minor units).
     *
     * @return array<int|string, int>
     */
    public function salesAttribution(): array
    {
        return Deal::whereHas('stage', fn ($q) => $q->where('is_won', true))
            ->selectRaw('owner_id, COALESCE(SUM(value),0) AS revenue')
            ->groupBy('owner_id')
            ->pluck('revenue', 'owner_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Customer acquisition cost = total ad spend / customers won (minor units).
     */
    public function cac(): int
    {
        $customers = Deal::whereHas('stage', fn ($q) => $q->where('is_won', true))->count();
        if ($customers === 0) {
            return 0;
        }

        $spend = (int) AdMetric::sum('spend');

        return (int) round($spend / $customers);
    }

    /**
     * Marketing ROI = won revenue / ad spend (ratio, divisor-guarded).
     */
    public function marketingRoi(): float
    {
        $spend = (int) AdMetric::sum('spend');
        if ($spend === 0) {
            return 0.0;
        }

        $revenue = (int) Deal::whereHas('stage', fn ($q) => $q->where('is_won', true))->sum('value');

        return round($revenue / $spend, 2);
    }

    /**
     * @return array<string, int>
     */
    private function wonRevenueBy(string $column): array
    {
        return Deal::whereHas('stage', fn ($q) => $q->where('is_won', true))
            ->selectRaw("COALESCE(NULLIF($column, ''), 'unattributed') AS bucket, COALESCE(SUM(value),0) AS revenue")
            ->groupBy('bucket')
            ->pluck('revenue', 'bucket')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Won revenue rolled up per attribution dimension (ATTR-006..011): keyword
     * (utm_term), ad creative (utm_content), landing page (first_path), content
     * (the page/piece the landing path belongs to), form and call source.
     * Every bucket is visitor-first-touch -> identified contact -> won deals:
     * anonymous visitors and unwon contacts contribute nothing.
     *
     * @return array<string, list<array{bucket: string, contacts: int, revenue: int}>>
     */
    public function dimensionAttribution(): array
    {
        return [
            'keywords' => $this->revenueByVisitorField('utm_term'),
            'ads' => $this->revenueByVisitorField('utm_content'),
            'landing_pages' => $this->revenueByVisitorField('first_path'),
            'content' => $this->contentAttribution(),
            'forms' => $this->formAttribution(),
            'calls' => $this->callAttribution(),
        ];
    }

    /**
     * @return list<array{bucket: string, contacts: int, revenue: int}>
     */
    private function revenueByVisitorField(string $field): array
    {
        $byContact = Visitor::whereNotNull('contact_id')->whereNotNull($field)
            ->get(['contact_id', $field])
            ->groupBy('contact_id')
            // One first-touch value per contact: the earliest visitor row wins.
            ->map(fn ($visitors) => (string) $visitors->first()->{$field});

        return $this->bucketRevenue($byContact->all());
    }

    /**
     * ATTR-008: landing paths resolved to the content they belong to — a site
     * page by its /s/{slug} URL or a content piece by its own URL path —
     * labeled with the content title. Unresolved paths stay raw, never guessed.
     *
     * @return list<array{bucket: string, contacts: int, revenue: int}>
     */
    private function contentAttribution(): array
    {
        $pages = SitePage::get(['slug', 'title'])->keyBy(fn (SitePage $p) => '/s/'.$p->slug);
        $pieces = ContentPiece::whereNotNull('url')->get(['url', 'title'])
            ->keyBy(fn (ContentPiece $p) => (string) (parse_url((string) $p->url, PHP_URL_PATH) ?: $p->url));

        $byContact = Visitor::whereNotNull('contact_id')->whereNotNull('first_path')
            ->get(['contact_id', 'first_path'])
            ->groupBy('contact_id')
            ->map(function ($visitors) use ($pages, $pieces) {
                $path = (string) $visitors->first()->first_path;

                return $pages->get($path)->title ?? $pieces->get($path)->title ?? $path;
            });

        return $this->bucketRevenue($byContact->all());
    }

    /**
     * ATTR-009: the earliest form submission is the contact's capturing form.
     *
     * @return list<array{bucket: string, contacts: int, revenue: int}>
     */
    private function formAttribution(): array
    {
        $byContact = FormSubmission::whereNotNull('contact_id')->with('form:id,name')
            ->orderBy('id')->get(['contact_id', 'form_id'])
            ->groupBy('contact_id')
            ->map(fn ($submissions) => (string) ($submissions->first()->form->name ?? 'unknown form'));

        return $this->bucketRevenue($byContact->all());
    }

    /**
     * ATTR-010: the earliest call per contact attributes to its source (which
     * the tracking number already stamped).
     *
     * @return list<array{bucket: string, contacts: int, revenue: int}>
     */
    private function callAttribution(): array
    {
        $byContact = Call::whereNotNull('contact_id')->whereNotNull('source')
            ->orderBy('id')->get(['contact_id', 'source'])
            ->groupBy('contact_id')
            ->map(fn ($calls) => (string) $calls->first()->source);

        return $this->bucketRevenue($byContact->all());
    }

    /**
     * Won revenue + contact counts per bucket, biggest first.
     *
     * @param  array<int|string, string>  $bucketByContact  contact_id => bucket
     * @return list<array{bucket: string, contacts: int, revenue: int}>
     */
    private function bucketRevenue(array $bucketByContact): array
    {
        if ($bucketByContact === []) {
            return [];
        }

        $revenue = Deal::where('status', 'won')
            ->whereIn('contact_id', array_keys($bucketByContact))
            ->selectRaw('contact_id, COALESCE(SUM(value),0) AS revenue')
            ->groupBy('contact_id')
            ->pluck('revenue', 'contact_id');

        $out = [];
        foreach ($bucketByContact as $contactId => $bucket) {
            $out[$bucket] ??= ['bucket' => $bucket, 'contacts' => 0, 'revenue' => 0];
            $out[$bucket]['contacts']++;
            $out[$bucket]['revenue'] += (int) ($revenue[$contactId] ?? 0);
        }
        usort($out, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return $out;
    }
}
