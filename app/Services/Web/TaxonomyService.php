<?php

namespace App\Services\Web;

use App\Models\AdCampaign;
use App\Models\Campaign;
use App\Models\ContentPiece;
use App\Models\Keyword;
use App\Models\ServiceLine;
use App\Models\SitePage;
use App\Models\TargetAccount;
use App\Models\Vertical;
use App\Models\Workflow;
use App\Web\TaxonomyCatalog;

/**
 * Service lines (SVC) and verticals (VERT).
 *
 * These are a taxonomy, and a taxonomy earns its place only if things TARGET it.
 * `coverage()` answers "what have we actually built for this service line or
 * vertical" across pages, keywords, campaigns and content — which is the
 * question a marketer asks, and the reason the register rows for the individual
 * services and industries describe targeting rather than 36 bespoke campaigns.
 */
class TaxonomyService
{
    /**
     * Provision the catalog for an organization. Idempotent: re-running adds
     * anything new without disturbing edits to existing rows.
     *
     * @return array{service_lines: int, verticals: int}
     */
    public function provision(): array
    {
        $services = 0;
        foreach (TaxonomyCatalog::serviceLines() as $entry) {
            $created = ServiceLine::firstOrCreate(
                ['key' => $entry['key']],
                ['name' => $entry['name'], 'category' => $entry['category'], 'is_active' => true],
            );
            $services += $created->wasRecentlyCreated ? 1 : 0;
        }

        $verticals = 0;
        foreach (TaxonomyCatalog::verticals() as $entry) {
            $created = Vertical::firstOrCreate(
                ['key' => $entry['key']],
                ['name' => $entry['name'], 'compliance_notes' => $entry['compliance_notes'], 'is_active' => true],
            );
            $verticals += $created->wasRecentlyCreated ? 1 : 0;
        }

        return ['service_lines' => $services, 'verticals' => $verticals];
    }

    /**
     * What targets this service line today.
     *
     * @return array<string, int>
     */
    public function serviceCoverage(ServiceLine $service): array
    {
        return [
            'pages' => SitePage::where('service_line_id', $service->id)->count(),
            'published_pages' => SitePage::where('service_line_id', $service->id)
                ->where('status', SitePage::STATUS_PUBLISHED)->count(),
            'keywords' => $this->keywordMatches($service->name),
            'campaigns' => $this->campaignMatches($service->name),
            'content' => $this->contentMatches($service->name),
        ];
    }

    /**
     * VERT-014..019: coverage joins on the explicit vertical bindings, with
     * name matching kept as a fallback for unbound records. Keywords stay
     * name-matched — a keyword phrase has no vertical axis to bind.
     *
     * @return array<string, int>
     */
    public function verticalCoverage(Vertical $vertical): array
    {
        return [
            'pages' => SitePage::where('vertical_id', $vertical->id)->count(),
            'published_pages' => SitePage::where('vertical_id', $vertical->id)
                ->where('status', SitePage::STATUS_PUBLISHED)->count(),
            'keywords' => $this->keywordMatches($vertical->name),
            'campaigns' => Campaign::where(fn ($q) => $q->where('vertical_id', $vertical->id)
                ->orWhereLike('name', '%'.$vertical->name.'%'))->count(),
            'content' => ContentPiece::where(fn ($q) => $q->where('vertical_id', $vertical->id)
                ->orWhereLike('title', '%'.$vertical->name.'%'))->count(),
            'ads' => AdCampaign::where('vertical_id', $vertical->id)->count(),
            'case_studies' => ContentPiece::where('vertical_id', $vertical->id)
                ->where('content_type', 'case_study')->count(),
            'sequences' => Workflow::where('vertical_id', $vertical->id)->count()
                + Campaign::where('vertical_id', $vertical->id)->where('channel', 'email')->count(),
            'accounts' => TargetAccount::where('vertical_id', $vertical->id)->count(),
        ];
    }

    /**
     * Coverage for every active service line, weakest first — the gaps are the
     * point of the report.
     *
     * @return list<array<string, mixed>>
     */
    public function serviceGaps(): array
    {
        return ServiceLine::where('is_active', true)->orderBy('name')->get()
            ->map(fn (ServiceLine $s) => ['id' => $s->id, 'key' => $s->key, 'name' => $s->name, 'category' => $s->category]
                + $this->serviceCoverage($s))
            ->sortBy('pages')->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function verticalGaps(): array
    {
        return Vertical::where('is_active', true)->orderBy('name')->get()
            ->map(fn (Vertical $v) => ['id' => $v->id, 'key' => $v->key, 'name' => $v->name, 'compliance_notes' => $v->compliance_notes, 'messaging' => $v->messaging]
                + $this->verticalCoverage($v))
            ->sortBy('pages')->values()->all();
    }

    private function keywordMatches(string $term): int
    {
        return Keyword::whereLike('phrase', '%'.$term.'%')->count();
    }

    private function campaignMatches(string $term): int
    {
        return Campaign::whereLike('name', '%'.$term.'%')->count();
    }

    private function contentMatches(string $term): int
    {
        return ContentPiece::whereLike('title', '%'.$term.'%')->count();
    }
}
