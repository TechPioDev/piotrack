<?php

use App\Authorization\Role;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\SeoLocation;
use App\Models\ServiceLine;
use App\Models\SitePage;
use App\Models\User;
use App\Models\Vertical;
use App\Services\Web\LocationService;
use App\Services\Web\SiteBuilderService;
use App\Services\Web\SiteHealthService;
use App\Services\Web\TaxonomyService;
use App\Support\CurrentOrganization;

/**
 * An organization on a plan that includes `marketing` (which gates the website).
 *
 * @return array{0: Organization, 1: User}
 */
function webOrganization(string $name = 'Test Org'): array
{
    [$org, $owner] = makeOrganization($name);
    subscribeOrganization($org, 'growth'); // Growth includes `marketing`

    return [$org, $owner];
}

/** A page with one visible section, ready to publish. */
function publishablePage(SiteBuilderService $builder, array $overrides = []): SitePage
{
    $page = $builder->createPage($overrides + ['title' => 'Managed IT Services', 'type' => 'service']);
    $builder->addSection($page, ['type' => 'hero', 'heading' => 'Managed IT']);

    return $page;
}

it('seeds the MSP taxonomy when an organization is created', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);

    // 24 service lines and 12 verticals, provisioned with the organization.
    expect(ServiceLine::count())->toBe(24)
        ->and(Vertical::count())->toBe(12)
        ->and(ServiceLine::where('key', 'soc')->exists())->toBeTrue()
        ->and(Vertical::where('key', 'healthcare')->first()->compliance_notes)->toContain('HIPAA');

    // Re-provisioning is idempotent.
    $added = app(TaxonomyService::class)->provision();
    expect($added['service_lines'])->toBe(0)->and(ServiceLine::count())->toBe(24);
    app(CurrentOrganization::class)->forget();
});

it('builds a page from sections with a unique slug', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $builder = app(SiteBuilderService::class);

    $first = $builder->createPage(['title' => 'Cloud Services', 'type' => 'service']);
    $second = $builder->createPage(['title' => 'Cloud Services', 'type' => 'service']);

    expect($first->slug)->toBe('cloud-services')
        ->and($second->slug)->toBe('cloud-services-2')  // collision resolved
        ->and($first->status)->toBe(SitePage::STATUS_DRAFT);

    $builder->addSection($first, ['type' => 'hero', 'heading' => 'Cloud']);
    $builder->addSection($first, ['type' => 'cta', 'heading' => 'Talk to us']);

    expect($first->sections()->count())->toBe(2)
        ->and($first->sections()->pluck('sort_order')->all())->toBe([1, 2])
        ->and(fn () => $builder->addSection($first, ['type' => 'nonsense']))->toThrow(RuntimeException::class);
    app(CurrentOrganization::class)->forget();
});

it('reorders sections', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $builder = app(SiteBuilderService::class);

    $page = $builder->createPage(['title' => 'Ordered']);
    $a = $builder->addSection($page, ['type' => 'hero']);
    $b = $builder->addSection($page, ['type' => 'content']);
    $c = $builder->addSection($page, ['type' => 'cta']);

    $builder->reorderSections($page, [$c->id, $a->id, $b->id]);
    app(CurrentOrganization::class)->forget();

    expect($page->sections()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id]);
});

it('refuses to publish a page with no visible section', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $builder = app(SiteBuilderService::class);

    $empty = $builder->createPage(['title' => 'Empty']);
    expect(fn () => $builder->publish($empty))->toThrow(RuntimeException::class, 'at least one visible section');

    // A hidden section does not count either.
    $builder->addSection($empty, ['type' => 'hero', 'is_visible' => false]);
    expect(fn () => $builder->publish($empty))->toThrow(RuntimeException::class);
    expect($empty->refresh()->status)->toBe(SitePage::STATUS_DRAFT);
    app(CurrentOrganization::class)->forget();
});

it('publishes and unpublishes a page', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $builder = app(SiteBuilderService::class);
    $page = publishablePage($builder);

    $published = $builder->publish($page);
    expect($published->status)->toBe(SitePage::STATUS_PUBLISHED)->and($published->published_at)->not->toBeNull();

    $draft = $builder->unpublish($published);
    expect($draft->status)->toBe(SitePage::STATUS_DRAFT)->and($draft->published_at)->toBeNull();
    app(CurrentOrganization::class)->forget();
});

it('serves a published page publicly and counts the view', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $builder = app(SiteBuilderService::class);
    $page = publishablePage($builder, ['title' => 'Managed IT Toronto', 'headline' => 'IT that just works']);
    $builder->addSection($page, ['type' => 'cta', 'heading' => 'Book a call']);
    $builder->publish($page);
    app(CurrentOrganization::class)->forget();

    $response = $this->get('/s/'.$page->slug);

    $response->assertOk()
        ->assertSee('IT that just works', escape: false)
        ->assertSee('Book a call', escape: false);

    expect($page->refresh()->view_count)->toBe(1);
});

it('404s a draft page so an unpublished URL cannot be guessed', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $page = app(SiteBuilderService::class)->createPage(['title' => 'Secret Launch']);
    app(CurrentOrganization::class)->forget();

    $this->get('/s/'.$page->slug)->assertNotFound();
});

it('scores page health from real content and fails checks with no data', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $builder = app(SiteBuilderService::class);
    $health = app(SiteHealthService::class);

    $bare = $builder->createPage(['title' => 'Bare']);
    $bareScore = $health->score($bare);

    // A blank page must not look healthy.
    expect($bareScore['score'])->toBeLessThan(30)
        ->and(collect($bareScore['checks'])->firstWhere('check', 'Has a call to action')['passed'])->toBeFalse()
        ->and(collect($bareScore['checks'])->firstWhere('check', 'Includes third-party proof')['passed'])->toBeFalse();

    $good = $builder->createPage([
        'title' => 'Complete Page',
        'headline' => 'Managed IT for Toronto law firms',
        'meta_title' => 'Managed IT Toronto',
        'meta_description' => str_repeat('Reliable managed IT for professional services firms. ', 2),
    ]);
    $builder->addSection($good, ['type' => 'hero']);
    $builder->addSection($good, ['type' => 'testimonials']);
    $builder->addSection($good, ['type' => 'cta']);
    $builder->publish($good);

    $goodScore = $health->score($good->refresh());
    app(CurrentOrganization::class)->forget();

    expect($goodScore['score'])->toBe(100);
});

it('rolls site health up weakest-first', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $builder = app(SiteBuilderService::class);

    $builder->createPage(['title' => 'Weak']);
    $strong = publishablePage($builder, ['title' => 'Strong', 'headline' => 'Yes']);
    $builder->addSection($strong, ['type' => 'cta']);

    $report = app(SiteHealthService::class)->siteReport();
    app(CurrentOrganization::class)->forget();

    expect($report['pages'])->toBe(2)
        ->and($report['weakest'][0]['title'])->toBe('Weak');
});

it('reports taxonomy coverage so gaps are visible', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $builder = app(SiteBuilderService::class);

    $soc = ServiceLine::where('key', 'soc')->first();
    $page = $builder->createPage(['title' => 'SOC Services', 'type' => 'service', 'service_line_id' => $soc->id]);
    $builder->addSection($page, ['type' => 'hero']);
    $builder->publish($page);

    $coverage = app(TaxonomyService::class)->serviceCoverage($soc);
    $gaps = app(TaxonomyService::class)->serviceGaps();
    app(CurrentOrganization::class)->forget();

    expect($coverage['pages'])->toBe(1)
        ->and($coverage['published_pages'])->toBe(1)
        // Weakest first: an untargeted service line leads the gap list.
        ->and($gaps[0]['pages'])->toBe(0)
        ->and(collect($gaps)->firstWhere('key', 'soc')['pages'])->toBe(1);
});

it('attributes leads to a branch through the company address', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);

    $toronto = app(LocationService::class)->create(['name' => 'Toronto', 'city' => 'Toronto', 'territory' => 'Ontario']);
    app(LocationService::class)->create(['name' => 'Calgary', 'city' => 'Calgary', 'territory' => 'Alberta']);

    $local = Company::create(['name' => 'Local Law', 'city' => 'Toronto']);
    Contact::create(['first_name' => 'In', 'email' => 'in@x.com', 'company_id' => $local->id, 'lifecycle_stage' => 'sql']);
    // No company: deliberately unattributed rather than guessed onto a branch.
    Contact::create(['first_name' => 'Unknown', 'email' => 'unknown@x.com']);

    $attributed = app(LocationService::class)->attributedContacts($toronto);
    $report = collect(app(LocationService::class)->report());
    app(CurrentOrganization::class)->forget();

    expect($attributed)->toHaveCount(1)
        ->and($report->firstWhere('name', 'Toronto')['leads'])->toBe(1)
        ->and($report->firstWhere('name', 'Toronto')['sqls'])->toBe(1)
        ->and($report->firstWhere('name', 'Calgary')['leads'])->toBe(0);
});

it('links a location page to its branch', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $location = app(LocationService::class)->create(['name' => 'Ottawa', 'city' => 'Ottawa']);
    $builder = app(SiteBuilderService::class);
    $page = $builder->createPage(['title' => 'IT Support Ottawa', 'type' => 'location', 'seo_location_id' => $location->id]);
    $builder->addSection($page, ['type' => 'hero']);
    $builder->publish($page);

    $row = collect(app(LocationService::class)->report())->firstWhere('name', 'Ottawa');
    app(CurrentOrganization::class)->forget();

    expect($row['has_page'])->toBeTrue()->and($row['published_page'])->toBeTrue();
});

it('builds pages through the controller and gates management', function () {
    [$org, $owner] = webOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)->get(route('web.pages.index'))->assertOk();
    $this->actingAs($viewer)->post(route('web.pages.store'), ['title' => 'Nope', 'type' => 'landing'])->assertForbidden();

    $this->actingAs($owner)->post(route('web.pages.store'), ['title' => 'Cybersecurity', 'type' => 'service'])->assertRedirect();
    expect(SitePage::withoutGlobalScope('tenant')->where('title', 'Cybersecurity')->exists())->toBeTrue();
});

it('lets an existing page be re-targeted to close a coverage gap', function () {
    // The coverage report exists to expose gaps; a gap you can only close by
    // creating a new page rather than re-pointing an existing one is far less useful.
    [$org, $owner] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $page = app(SiteBuilderService::class)->createPage(['title' => 'Untargeted', 'type' => 'landing']);
    $mdr = ServiceLine::where('key', 'mdr')->first();
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->patch(route('web.pages.update', $page->id), [
        'type' => 'service',
        'service_line_id' => $mdr->id,
    ])->assertRedirect();

    app(CurrentOrganization::class)->set($org);
    expect($page->refresh()->service_line_id)->toBe($mdr->id)
        ->and($page->type)->toBe('service')
        ->and(app(TaxonomyService::class)->serviceCoverage($mdr)['pages'])->toBe(1);
    app(CurrentOrganization::class)->forget();
});

it('refuses a navigation item with nowhere to go', function () {
    [, $owner] = webOrganization();

    $this->actingAs($owner)
        ->post(route('web.navigation.store'), ['label' => 'Dead link', 'placement' => 'header'])
        ->assertSessionHasErrors(['site_page_id', 'url']);
});

it('isolates pages across tenants', function () {
    [, $ownerA] = webOrganization('Tenant A');
    [$orgB] = webOrganization('Tenant B');

    app(CurrentOrganization::class)->set($orgB);
    $pageB = app(SiteBuilderService::class)->createPage(['title' => 'B page']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($ownerA)->delete(route('web.pages.destroy', $pageB->id))->assertNotFound();
    expect(SitePage::withoutGlobalScope('tenant')->whereKey($pageB->id)->exists())->toBeTrue();
});

it('never lets one tenant page shadow another on the public URL space', function () {
    // Regression: slug uniqueness was originally per tenant while /s/{slug} is a
    // single global URL space, so two organizations naming a page "Managed IT"
    // collided and the second page became unreachable. Slugs are now globally
    // unique and the collision is resolved with a suffix.
    [$orgA] = webOrganization('Alpha MSP');
    [$orgB] = webOrganization('Beta MSP');

    foreach ([$orgA, $orgB] as $org) {
        app(CurrentOrganization::class)->set($org);
        $builder = app(SiteBuilderService::class);
        $page = $builder->createPage(['title' => 'Managed IT']);
        $builder->addSection($page, ['type' => 'hero', 'heading' => $org->name]);
        $builder->publish($page);
        app(CurrentOrganization::class)->forget();
    }

    $slugs = SitePage::withoutGlobalScope('tenant')->orderBy('id')->pluck('slug')->all();
    expect($slugs)->toBe(['managed-it', 'managed-it-2']);

    // Each slug resolves to its OWN tenant's content — neither is shadowed.
    foreach (SitePage::withoutGlobalScope('tenant')->get() as $page) {
        $expected = Organization::find($page->organization_id)->name;
        $this->get('/s/'.$page->slug)->assertOk()->assertSee($expected, escape: false);
    }
});

/**
 * A published page led with its headline as an H1 and then repeated the same
 * sentence as an H2 immediately beneath, because the page headline and the hero
 * section's heading both rendered. It read as a mistake to every visitor.
 */
it('does not print the headline twice when a hero section repeats it', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $builder = app(SiteBuilderService::class);

    $page = $builder->createPage(['title' => 'Managed IT Services', 'type' => 'service', 'headline' => 'IT that just works']);
    // A hero echoing the headline is what the builder's templates produce.
    $builder->addSection($page, ['type' => 'hero', 'heading' => 'IT that just works']);
    $builder->addSection($page, ['type' => 'cta', 'heading' => 'Book a call']);
    $builder->publish($page);
    app(CurrentOrganization::class)->forget();

    $html = $this->get('/s/'.$page->slug)->assertOk()->getContent();

    expect(substr_count($html, 'IT that just works'))->toBe(1);
});

it('keeps a hero section that says something of its own', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $builder = app(SiteBuilderService::class);

    $page = $builder->createPage(['title' => 'Managed IT Services', 'type' => 'service', 'headline' => 'IT that just works']);
    $builder->addSection($page, ['type' => 'hero', 'heading' => 'Trusted by 40 Toronto clinics']);
    $builder->publish($page);
    app(CurrentOrganization::class)->forget();

    // Distinct content is not a duplicate, so dropping it would lose the page's words.
    $this->get('/s/'.$page->slug)->assertOk()
        ->assertSee('IT that just works', escape: false)
        ->assertSee('Trusted by 40 Toronto clinics', escape: false);
});

/**
 * These are marketing pages, so what search engines and visitors need is part of
 * the deliverable: a description on every page, a canonical URL, structured data
 * describing the business, and internal links so pages are not orphans.
 */
it('publishes a page search engines can actually read', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);

    $location = SeoLocation::create([
        'name' => 'Toronto HQ', 'street' => '120 Adelaide St W', 'city' => 'Toronto',
        'region' => 'Ontario', 'postal_code' => 'M5H 1T1', 'country' => 'Canada',
        'phone' => '+14165550100',
    ]);

    $builder = app(SiteBuilderService::class);
    $page = $builder->createPage([
        'title' => 'Managed IT Toronto', 'type' => 'location',
        'headline' => 'IT that just works', 'seo_location_id' => $location->id,
    ]);
    $builder->addSection($page, ['type' => 'cta', 'heading' => 'Book a call']);
    $builder->publish($page);
    app(CurrentOrganization::class)->forget();

    $html = $this->get('/s/'.$page->slug)->assertOk()->getContent();

    // Discoverability
    expect($html)->toContain('rel="canonical"')
        ->and($html)->toContain('name="description"')
        ->and($html)->toContain('property="og:title"');

    // Structured data, parseable rather than merely present
    preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);
    expect($m[1])->not->toBeEmpty();
    $types = [];
    foreach ($m[1] as $block) {
        $decoded = json_decode($block, true);
        expect($decoded)->toBeArray();
        $types[] = $decoded['@type'] ?? null;
    }
    expect($types)->toContain('LocalBusiness')->toContain('BreadcrumbList');

    // The branch's phone, as something a visitor can tap and a crawler can read
    expect($html)->toContain('tel:+14165550100')
        ->and($html)->toContain('120 Adelaide St W');
});

it('links a page to its siblings so published pages are not orphans', function () {
    [$org] = webOrganization();
    app(CurrentOrganization::class)->set($org);
    $builder = app(SiteBuilderService::class);

    foreach (['Managed IT Services', 'Cybersecurity', 'Cloud Migration'] as $title) {
        $p = $builder->createPage(['title' => $title, 'type' => 'service']);
        $builder->addSection($p, ['type' => 'cta', 'heading' => 'Book a call']);
        $builder->publish($p);
    }
    $first = SitePage::where('title', 'Managed IT Services')->firstOrFail();
    app(CurrentOrganization::class)->forget();

    $html = $this->get('/s/'.$first->slug)->assertOk()->getContent();

    // Its siblings are reachable from it, which is how the rest of the site is found.
    expect($html)->toContain('Cybersecurity')->toContain('Cloud Migration');
});
