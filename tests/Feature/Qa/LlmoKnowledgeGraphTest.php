<?php

declare(strict_types=1);

/**
 * LLM Optimization close-out (Phase 10 — LLMO-004..010/012/013/014/016/018).
 *
 * The knowledge graph is assembled purely from first-party records — brand
 * entity facts, service lines, locations, expert profiles — into one @graph
 * whose nodes reference each other by @id, which is what lets an answer engine
 * resolve the brand as a single entity. The scorer measures citation-
 * friendliness, concise definitions and fact density deterministically.
 */

use App\Authorization\Role;
use App\Models\BrandProfile;
use App\Models\ExpertProfile;
use App\Models\SeoLocation;
use App\Models\ServiceLine;
use App\Models\StructuredData;
use App\Services\Seo\KnowledgeGraphService;
use App\Services\Seo\LlmoContentScorer;
use App\Support\CurrentOrganization;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Northwind IT Services');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function seedGraphEntities(): void
{
    BrandProfile::create([
        'legal_name' => 'Northwind IT Services LLC',
        'alternate_names' => ['Northwind IT'],
        'website_url' => 'https://northwind-it.test',
        'logo_url' => 'https://northwind-it.test/logo.png',
        'founded_year' => 2011,
        'same_as' => ['https://www.linkedin.com/company/northwind-it', 'https://g.page/northwind-it'],
        'disambiguation' => 'The Philadelphia managed IT provider for regulated manufacturers.',
        'tagline' => 'IT that passes the audit.',
    ]);
    // Organizations are provisioned with the default MSP taxonomy; narrow it
    // to one fully described line so the graph under test is exact.
    ServiceLine::query()->update(['is_active' => false]);
    ServiceLine::where('key', 'cmmc')->update(['name' => 'CMMC Compliance', 'category' => 'Compliance', 'description' => 'CMMC readiness and remediation.', 'is_active' => true]);
    SeoLocation::create(['name' => 'Philadelphia HQ', 'street' => '1 Market St', 'city' => 'Philadelphia', 'region' => 'PA', 'postal_code' => '19106', 'country' => 'US', 'phone' => '+1-215-555-0100', 'is_active' => true]);
    ExpertProfile::create(['name' => 'Dana Reyes', 'title' => 'Security Lead', 'bio' => 'Leads the security practice.', 'credentials' => ['CISSP'], 'knows_about' => ['CMMC compliance'], 'same_as' => ['https://www.linkedin.com/in/dana-reyes-test'], 'is_active' => true]);
}

it('builds one graph from first-party records with @id relationships between every entity', function () {
    seedGraphEntities();

    $graph = app(KnowledgeGraphService::class)->build();
    $nodes = collect($graph['@graph']);

    expect($graph['@context'])->toBe('https://schema.org')->and($nodes)->toHaveCount(4);

    // LLMO-005/010: the organization node carries identity + disambiguation.
    $org = $nodes->firstWhere('@type', 'Organization');
    expect($org['@id'])->toBe('https://northwind-it.test#organization')
        ->and($org['legalName'])->toBe('Northwind IT Services LLC')
        ->and($org['alternateName'])->toBe(['Northwind IT'])
        ->and($org['sameAs'])->toHaveCount(2)
        ->and($org['description'])->toContain('Philadelphia managed IT')
        ->and($org['foundingDate'])->toBe('2011')
        ->and($org['knowsAbout'])->toBe(['CMMC Compliance']);

    // LLMO-004/006: the service references its provider and carries taxonomy.
    $service = $nodes->firstWhere('@type', 'Service');
    expect($service['serviceType'])->toBe('Compliance')
        ->and($service['provider'])->toBe(['@id' => 'https://northwind-it.test#organization'])
        ->and($service['areaServed'])->toBe(['Philadelphia']);

    // LLMO-009: the branch is a LocalBusiness under the organization.
    $location = $nodes->firstWhere('@type', 'LocalBusiness');
    expect($location['parentOrganization'])->toBe(['@id' => 'https://northwind-it.test#organization'])
        ->and($location['address']['addressLocality'])->toBe('Philadelphia');

    // LLMO-007/008: the expert carries verifiable credentials and topics.
    $person = $nodes->firstWhere('@type', 'Person');
    expect($person['worksFor'])->toBe(['@id' => 'https://northwind-it.test#organization'])
        ->and($person['hasCredential'])->toBe([['@type' => 'EducationalOccupationalCredential', 'name' => 'CISSP']])
        ->and($person['knowsAbout'])->toBe(['CMMC compliance']);
});

it('audits retrieval readiness with a fix per missing signal, and reaches 100 when complete', function () {
    // A fresh tenant (default taxonomy only): nearly everything is missing
    // and every failing item says what to do.
    $empty = app(KnowledgeGraphService::class)->completeness();
    expect($empty['score'])->toBeLessThan(30);
    $byKey = collect($empty['items'])->keyBy('key');
    expect($byKey['organization']['ok'])->toBeFalse()
        ->and($byKey['disambiguation']['ok'])->toBeFalse()
        ->and($byKey['disambiguation']['detail'])->toContain('sameAs')
        ->and($byKey['services']['ok'])->toBeFalse()
        ->and($byKey['experts']['detail'])->toContain('credentials');

    seedGraphEntities();

    $full = app(KnowledgeGraphService::class)->completeness();
    expect($full['score'])->toBe(100)
        ->and(collect($full['items'])->every(fn (array $i) => $i['ok']))->toBeTrue();
});

it('scores LLMO content factors deterministically', function () {
    $rich = '<html><body><main><h1>What is CMMC?</h1>'
        .'<p>CMMC is the certification framework defense contractors must pass to keep DoD work.</p>'
        .'<h2>Why does it matter?</h2>'
        .'<p>Level 2 covers 110 controls; assessments run $30,000 to $100,000 and 72% of primes now require it. See '
        .'<a href="https://dodcio.defense.gov/cmmc">the DoD CMMC page</a> and <a href="https://www.nist.gov/sp800-171">NIST SP 800-171</a>.</p>'
        .'<ul><li>14 domains</li><li>3 levels</li></ul>'
        .'<script type="application/ld+json">{}</script>'
        .str_repeat('<p>Practical detail on scoping, evidence and remediation for manufacturers. </p>', 40)
        .'</main></body></html>';

    $result = app(LlmoContentScorer::class)->score($rich);
    expect($result['score'])->toBe(100)->and($result['factors'])->toHaveCount(9);

    // Thin marketing copy: no sources, no definition, no facts.
    $thin = '<html><body><div><h1>We are great</h1><p>Call us today for amazing service.</p></div></body></html>';
    $weak = collect(app(LlmoContentScorer::class)->score($thin)['factors'])->keyBy('key');
    expect($weak['citations']['ok'])->toBeFalse()
        ->and($weak['definitions']['ok'])->toBeFalse()
        ->and($weak['facts']['ok'])->toBeFalse();
});

it('manages the entity, experts and published graph over HTTP with audit trail', function () {
    seedGraphEntities();

    $this->actingAs($this->owner)
        ->post(route('seo.llmo.entity'), [
            'legal_name' => 'Northwind IT Services LLC',
            'alternate_names' => ['Northwind IT', 'NWIT'],
            'website_url' => 'https://northwind-it.test',
            'same_as' => ['https://www.linkedin.com/company/northwind-it', 'https://g.page/northwind-it'],
            'disambiguation' => 'Managed IT for regulated manufacturers.',
        ])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    expect(BrandProfile::first()->alternate_names)->toBe(['Northwind IT', 'NWIT']);

    $this->actingAs($this->owner)
        ->post(route('seo.llmo.experts.store'), [
            'name' => 'Sam Okafor', 'title' => 'vCIO', 'credentials' => ['CCNA'],
        ])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    expect(ExpertProfile::where('name', 'Sam Okafor')->exists())->toBeTrue();

    $this->actingAs($this->owner)->post(route('seo.llmo.graph'))->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    $published = StructuredData::where('schema_type', 'KnowledgeGraph')->first();
    expect($published)->not->toBeNull()
        ->and($published->jsonld)->toContain('#organization')
        ->and(DB::table('audit_logs')->where('action', 'seo.knowledge_graph.published')->count())->toBe(1);

    // The index page assembles everything, and a score round-trips via flash.
    $this->actingAs($this->owner)->get(route('seo.llmo.index'))->assertInertia(
        fn (AssertableInertia $page) => $page->component('seo/llmo/index')
            ->where('completeness.score', 100)
            ->has('experts', 2)
            ->where('published.id', $published->id),
    );

    $this->actingAs($this->owner)
        ->from(route('seo.llmo.index'))
        ->post(route('seo.llmo.score'), ['html' => '<html><body><p>Hi.</p></body></html>'])
        ->assertRedirect(route('seo.llmo.index'));
    $this->actingAs($this->owner)->get(route('seo.llmo.index'))->assertInertia(
        fn (AssertableInertia $page) => $page->has('scoreResult.factors', 9),
    );
});

it('keeps the graph tenant-scoped and the endpoints permission-gated', function () {
    seedGraphEntities();
    $expert = ExpertProfile::firstOrFail();

    // A viewer can read the page but cannot manage anything on it.
    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)->get(route('seo.llmo.index'))->assertOk();
    $this->actingAs($viewer)->post(route('seo.llmo.graph'))->assertForbidden();
    $this->actingAs($viewer)->delete(route('seo.llmo.experts.destroy', $expert))->assertForbidden();

    // Another organization sees an empty graph — and cannot reach this
    // tenant's expert through the route model binding.
    [$otherOrg, $otherOwner] = makeOrganization('Rival MSP');
    subscribeOrganization($otherOrg, 'enterprise');

    $this->actingAs($otherOwner)->delete(route('seo.llmo.experts.destroy', $expert))->assertNotFound();

    app(CurrentOrganization::class)->set($otherOrg);
    $graph = app(KnowledgeGraphService::class)->build();
    expect(collect($graph['@graph'])->firstWhere('@type', 'Person'))->toBeNull()
        ->and(ExpertProfile::count())->toBe(0);
});
