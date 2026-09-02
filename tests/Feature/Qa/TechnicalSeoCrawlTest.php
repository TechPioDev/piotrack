<?php

declare(strict_types=1);

/**
 * Technical SEO close-out (Phase 11 — TSEO-002/003/004/010/013/014/016/017/018/021/022).
 *
 * A fake site with one planted defect per report section: a noindex page, a
 * robots-blocked-but-linked page, a page missing from the sitemap, a
 * sitemap-only orphan, a dead end, duplicate titles/descriptions, a broken
 * link and a redirect. The crawler must find each defect in its section and
 * invent nothing else. Public-IP-literal host: no DNS, and the SSRF guard's
 * allow path is exercised (established TechnicalAuditTest pattern).
 */

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\SiteCrawl;
use App\Services\Seo\SiteCrawler;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

const CRAWL_BASE = 'https://93.184.216.34';

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Crawl Test Org');
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function fakeSite(): void
{
    $page = fn (string $title, string $desc, string $body, array $links, string $extraHead = '') => Http::response(
        "<html><head><title>{$title}</title>".
        ($desc !== '' ? "<meta name=\"description\" content=\"{$desc}\">" : '').
        $extraHead.
        '</head><body><p>'.$body.'</p>'.
        implode('', array_map(fn (string $href) => "<a href=\"{$href}\">link</a>", $links)).
        '</body></html>'
    );

    Http::fake([
        CRAWL_BASE.'/robots.txt' => Http::response("User-agent: *\nDisallow: /blocked-tools\nSitemap: ".CRAWL_BASE."/sitemap.xml\n"),
        CRAWL_BASE.'/sitemap.xml' => Http::response(
            '<?xml version="1.0"?><urlset>'.
            '<url><loc>'.CRAWL_BASE.'/</loc></url>'.
            '<url><loc>'.CRAWL_BASE.'/services</loc></url>'.
            '<url><loc>'.CRAWL_BASE.'/services/cmmc</loc></url>'.
            '<url><loc>'.CRAWL_BASE.'/orphan</loc></url>'.
            '</urlset>'
        ),
        CRAWL_BASE.'/services/cmmc' => $page('Managed IT Services | Northwind', 'MSP services for manufacturers.', 'CMMC compliance detail page.', []),
        CRAWL_BASE.'/services' => $page('Managed IT Services | Northwind', 'MSP services for manufacturers.', 'The services overview.', ['/', '/services/cmmc'], '<link rel="canonical" href="'.CRAWL_BASE.'/services">'),
        CRAWL_BASE.'/about' => $page('About Northwind', 'Who we are.', 'The team behind the MSP.', ['/'], '<meta name="robots" content="noindex,follow">'),
        CRAWL_BASE.'/old-page' => Http::response('', 301, ['Location' => '/services']),
        CRAWL_BASE.'/broken' => Http::response('Not here', 404),
        CRAWL_BASE.'/blocked-tools/secret' => $page('Internal tools', '', 'Internal tooling index.', ['/']),
        CRAWL_BASE.'/orphan' => $page('Careers at Northwind', 'Open roles.', 'Jobs page nobody links to.', ['/']),
        CRAWL_BASE.'/' => $page('Northwind IT — Managed IT', 'Philadelphia managed IT.', 'The home page.', ['/services', '/about', '/blocked-tools/secret', '/old-page', '/broken']),
    ]);
}

it('crawls same-host breadth-first within the page budget', function () {
    fakeSite();
    app(CurrentOrganization::class)->set($this->org);

    $crawl = app(SiteCrawler::class)->crawl(CRAWL_BASE.'/');

    expect($crawl->pages_crawled)->toBe(8)
        ->and($crawl->organization_id)->toBe($this->org->id);

    $pages = collect($crawl->report['pages']);
    expect($pages->every(fn (array $p) => str_starts_with($p['url'], CRAWL_BASE)))->toBeTrue()
        ->and($pages->firstWhere('url', CRAWL_BASE.'/')['depth'])->toBe(0)
        ->and($pages->firstWhere('url', CRAWL_BASE.'/services/cmmc')['depth'])->toBeLessThanOrEqual(2);

    // The budget is a hard cap, not a suggestion.
    $bounded = app(SiteCrawler::class)->crawl(CRAWL_BASE.'/', 3);
    expect($bounded->pages_crawled)->toBe(3);
});

it('finds each planted defect in its own report section', function () {
    fakeSite();
    app(CurrentOrganization::class)->set($this->org);

    $crawl = app(SiteCrawler::class)->crawl(CRAWL_BASE.'/');
    $sections = collect($crawl->report['sections'])->keyBy('key');

    // TSEO-003: exactly the noindex page, nothing invented.
    expect($sections['indexation']['items'])->toHaveCount(1)
        ->and($sections['indexation']['items'][0])->toContain('/about')->toContain('noindex');

    // TSEO-014: robots.txt exists, has a sitemap line, no blanket disallow.
    expect($sections['robots']['ok'])->toBeTrue();

    // TSEO-004: linked internally but robots-blocked.
    expect($sections['crawlability']['items'][0])->toContain('/blocked-tools/secret');

    // TSEO-013: crawlable page absent from the sitemap.
    expect($sections['sitemap']['items'])->toHaveCount(1)
        ->and($sections['sitemap']['items'][0])->toContain('/blocked-tools/secret');

    // TSEO-010: the sitemap-only orphan and the dead end.
    $links = implode(' ', $sections['links']['items']);
    expect($links)->toContain('/orphan')->toContain('orphan')
        ->and($links)->toContain('/services/cmmc')->toContain('dead end');

    // TSEO-016: duplicate titles and descriptions across the two service pages.
    expect($sections['duplicates']['items'])->toHaveCount(2)
        ->and(implode(' ', $sections['duplicates']['items']))->toContain('/services')->toContain('/services/cmmc');

    // TSEO-017: the broken link with its source.
    expect($sections['broken']['items'])->toHaveCount(1)
        ->and($sections['broken']['items'][0])->toContain('/broken')->toContain('404')->toContain('linked from');

    // TSEO-018: the redirect is mapped.
    expect($sections['redirects']['items'])->toHaveCount(1)
        ->and($sections['redirects']['items'][0])->toContain('/old-page')->toContain('redirects to');

    // TSEO-021/022: small shallow site — nothing to flag, and it says so.
    expect($sections['speed']['ok'])->toBeTrue()
        ->and($sections['architecture']['ok'])->toBeTrue();

    // 1 indexation + 1 crawlability + 1 sitemap + 2 links + 2 duplicates
    // + 1 broken + 1 redirect.
    expect($crawl->issues_count)->toBe(9);
});

it('refuses a private start URL before any request is made', function () {
    app(CurrentOrganization::class)->set($this->org);

    expect(fn () => app(SiteCrawler::class)->crawl('http://169.254.169.254/'))
        ->toThrow(RuntimeException::class);

    app(CurrentOrganization::class)->forget();

    // Over HTTP the refusal is a validation error, not a 500 — and no row.
    $this->actingAs($this->owner)
        ->from(route('seo.audits.index'))
        ->post(route('seo.audits.crawl'), ['url' => 'http://169.254.169.254/'])
        ->assertRedirect(route('seo.audits.index'))
        ->assertSessionHasErrors('url');

    expect(SiteCrawl::withoutGlobalScopes()->count())->toBe(0);
});

it('runs from the controller, renders the report, and writes the audit trail', function () {
    fakeSite();

    $response = $this->actingAs($this->owner)->post(route('seo.audits.crawl'), ['url' => CRAWL_BASE.'/']);

    $crawl = SiteCrawl::withoutGlobalScopes()->firstOrFail();
    $response->assertRedirect(route('seo.audits.crawl.show', $crawl->id));

    expect(AuditLog::withoutGlobalScope('tenant')->where('action', 'seo.crawl.run')->exists())->toBeTrue();

    $this->actingAs($this->owner)->get(route('seo.audits.crawl.show', $crawl->id))->assertInertia(
        fn (AssertableInertia $page) => $page->component('seo/audits/crawl')
            ->where('crawl.pages_crawled', 8)
            ->has('crawl.report.sections', 10),
    );

    $this->actingAs($this->owner)->get(route('seo.audits.index'))->assertInertia(
        fn (AssertableInertia $page) => $page->has('crawls', 1),
    );
});

it('keeps crawls tenant-scoped and permission-gated', function () {
    fakeSite();
    app(CurrentOrganization::class)->set($this->org);
    $crawl = app(SiteCrawler::class)->crawl(CRAWL_BASE.'/');
    app(CurrentOrganization::class)->forget();

    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)->get(route('seo.audits.crawl.show', $crawl->id))->assertOk();
    $this->actingAs($viewer)->post(route('seo.audits.crawl'), ['url' => CRAWL_BASE.'/'])->assertForbidden();

    [, $otherOwner] = makeOrganization('Rival Org');
    $this->actingAs($otherOwner)->get(route('seo.audits.crawl.show', $crawl->id))->assertNotFound();
});
