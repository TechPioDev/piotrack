<?php

namespace App\Services\Seo;

use App\Models\BrandProfile;
use App\Models\ExpertProfile;
use App\Models\SeoLocation;
use App\Models\ServiceLine;
use App\Models\StructuredData;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Collection;

/**
 * The organization's knowledge graph (LLMO-004..010/016/018): one schema.org
 *
 * @graph assembled purely from first-party records — the organization and its
 * brand-entity facts, active service lines as a taxonomy, branch locations,
 * and expert profiles. Every node carries an @id and relationships are @id
 * references (provider, worksFor, parentOrganization, areaServed, knowsAbout),
 * which is what lets an LLM resolve the brand as one entity rather than
 * scattered strings. Deterministic; nothing external, nothing invented.
 */
class KnowledgeGraphService
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $organization = $this->currentOrganization->get();
        if ($organization === null) {
            return ['@context' => 'https://schema.org', '@graph' => []];
        }

        $brand = BrandProfile::first();
        $services = ServiceLine::where('is_active', true)->orderBy('name')->get();
        $locations = SeoLocation::where('is_active', true)->orderBy('id')->get();
        $experts = ExpertProfile::where('is_active', true)->orderBy('name')->get();

        $baseUrl = rtrim($brand?->website_url ?: url('/'), '/');
        $orgId = $baseUrl.'#organization';

        $graph = [$this->organizationNode($organization->name, $orgId, $baseUrl, $brand, $services, $locations)];

        foreach ($services as $service) {
            $graph[] = array_filter([
                '@type' => 'Service',
                '@id' => $baseUrl.'#service-'.$service->key,
                'name' => $service->name,
                'serviceType' => $service->category,
                'description' => $service->description,
                'provider' => ['@id' => $orgId],
                'areaServed' => $locations->pluck('city')->filter()->unique()->values()->all() ?: null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        foreach ($locations as $location) {
            $graph[] = array_filter([
                '@type' => 'LocalBusiness',
                '@id' => $baseUrl.'#location-'.$location->id,
                'name' => $location->name,
                'telephone' => $location->phone,
                'address' => $this->address($location),
                'parentOrganization' => ['@id' => $orgId],
            ], fn ($v) => $v !== null && $v !== '' && $v !== []);
        }

        foreach ($experts as $expert) {
            $graph[] = array_filter([
                '@type' => 'Person',
                '@id' => $baseUrl.'#person-'.$expert->id,
                'name' => $expert->name,
                'jobTitle' => $expert->title,
                'description' => $expert->bio,
                'worksFor' => ['@id' => $orgId],
                'hasCredential' => array_map(fn (string $c) => [
                    '@type' => 'EducationalOccupationalCredential',
                    'name' => $c,
                ], $expert->credentials ?? []) ?: null,
                'knowsAbout' => $expert->knows_about ?: null,
                'sameAs' => $expert->same_as ?: null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /**
     * Retrieval-readiness audit (LLMO-018): how resolvable is this brand as an
     * entity, and what is missing. Each failing item names its fix.
     *
     * @return array{score: int, items: list<array{key: string, label: string, ok: bool, detail: string}>}
     */
    public function completeness(): array
    {
        $brand = BrandProfile::first();
        $services = ServiceLine::where('is_active', true)->get();
        $locations = SeoLocation::where('is_active', true)->get();
        $experts = ExpertProfile::where('is_active', true)->get();

        $sameAs = $brand->same_as ?? [];
        $alternates = $brand->alternate_names ?? [];
        $credentialed = $experts->filter(fn (ExpertProfile $e) => ($e->credentials ?? []) !== [])->count();
        $categorized = $services->filter(fn (ServiceLine $s) => $s->category !== null && $s->description !== null)->count();
        $fullNap = $locations->filter(fn (SeoLocation $l) => $l->street && $l->city && $l->region && $l->phone)->count();

        // Relationship edges the graph will emit: provider + parentOrganization
        // + worksFor references (LLMO-004).
        $edges = $services->count() + $locations->count() + $experts->count();

        $items = [
            $this->item('organization', 'Organization entity', $brand?->website_url !== null && $brand->logo_url !== null,
                'Canonical URL and logo identify the organization node.',
                'Set the website URL and logo so engines can anchor the organization entity.'),
            $this->item('disambiguation', 'Brand disambiguation', count($sameAs) >= 2 && ($brand->disambiguation ?? '') !== '',
                count($sameAs).' sameAs links + a disambiguating description.',
                'Add at least two sameAs profile links (LinkedIn, GBP…) and a one-line disambiguating description.'),
            $this->item('alternate_names', 'Alternate names', $alternates !== [] || ($brand->legal_name ?? '') !== '',
                'Legal/alternate names help resolve brand-name variants.',
                'Record the legal name and any names customers actually use.'),
            $this->item('services', 'Service taxonomy', $services->count() > 0 && $categorized === $services->count(),
                $services->count().' active services, all categorized and described.',
                'Give every active service line a category and description — that is the taxonomy engines read.'),
            $this->item('locations', 'Location entities', $locations->count() > 0 && $fullNap === $locations->count(),
                $locations->count().' locations with complete NAP.',
                'Add at least one location with full street/city/region/phone.'),
            $this->item('experts', 'Expertise signals', $credentialed > 0,
                $credentialed.' expert profiles carry credentials.',
                'Add expert profiles with real credentials (CISSP, CCNA, vCIO…) — expertise LLMs can verify.'),
            $this->item('relationships', 'Entity relationships', $edges >= 3,
                $edges.' relationship edges connect the graph.',
                'The graph needs services, locations and people linked to the organization to read as one entity.'),
        ];

        $passed = count(array_filter($items, fn (array $i) => $i['ok']));

        return ['score' => (int) round($passed / count($items) * 100), 'items' => $items];
    }

    /**
     * Store the current graph as structured data the tenant can embed; the
     * schema page lists it alongside every other generated JSON-LD block.
     */
    public function publish(): StructuredData
    {
        $graph = $this->build();
        $brand = BrandProfile::first();

        $item = StructuredData::create([
            'url' => $brand?->website_url,
            'schema_type' => 'KnowledgeGraph',
            'jsonld' => json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        ]);

        $this->audit->log(
            'seo.knowledge_graph.published',
            context: ['nodes' => count($graph['@graph'])],
            resourceType: 'structured_data',
            resourceId: (string) $item->id,
        );

        return $item;
    }

    /**
     * @param  Collection<int, ServiceLine>  $services
     * @param  Collection<int, SeoLocation>  $locations
     * @return array<string, mixed>
     */
    private function organizationNode(string $name, string $orgId, string $baseUrl, ?BrandProfile $brand, $services, $locations): array
    {
        $primary = $locations->first();

        return array_filter([
            '@type' => 'Organization',
            '@id' => $orgId,
            'name' => $name,
            'legalName' => $brand?->legal_name,
            'alternateName' => $brand?->alternate_names ?: null,
            'url' => $baseUrl,
            'logo' => $brand?->logo_url,
            'slogan' => $brand?->tagline,
            'description' => $brand?->disambiguation ?: $brand?->positioning_statement,
            'foundingDate' => $brand?->founded_year !== null ? (string) $brand->founded_year : null,
            'sameAs' => $brand?->same_as ?: null,
            'knowsAbout' => $services->pluck('name')->values()->all() ?: null,
            'telephone' => $primary?->phone,
            'address' => $primary !== null ? $this->address($primary) : null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function address(SeoLocation $location): array
    {
        $fields = array_filter([
            'streetAddress' => $location->street,
            'addressLocality' => $location->city,
            'addressRegion' => $location->region,
            'postalCode' => $location->postal_code,
            'addressCountry' => $location->country,
        ], fn ($v) => $v !== null && $v !== '');

        return $fields === [] ? [] : ['@type' => 'PostalAddress'] + $fields;
    }

    /**
     * @return array{key: string, label: string, ok: bool, detail: string}
     */
    private function item(string $key, string $label, bool $ok, string $okDetail, string $fix): array
    {
        return ['key' => $key, 'label' => $label, 'ok' => $ok, 'detail' => $ok ? $okDetail : $fix];
    }
}
