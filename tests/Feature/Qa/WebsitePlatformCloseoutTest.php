<?php

declare(strict_types=1);

/**
 * MSP Website Platform close-out (Phase 23 — WEB-007..010, 022, 033/034/035/037,
 * 038/041/042/043, 052/053).
 *
 * Experiments served on live pages (sticky variant, in-memory override,
 * impression once, conversion from the form cookie, org-checked), gated
 * lead-magnet delivery over signed URLs with download counting, publish-time +
 * scheduled technical audits of the pages themselves, the pinned performance
 * budget with real HTTP caching, and the buyer-journey template gallery.
 */

use App\Models\ExperimentVariant;
use App\Models\File;
use App\Models\Form;
use App\Models\PageSection;
use App\Models\SeoAudit;
use App\Models\SitePage;
use App\Services\Analytics\ExperimentService;
use App\Services\Web\PageExperiments;
use App\Services\Web\SiteBuilderService;
use App\Services\Web\SiteHealthService;
use App\Support\CurrentOrganization;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Web Platform Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
    $this->builder = app(SiteBuilderService::class);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

/** A published page with a CTA section, ready to serve. */
function publishedWebPage(SiteBuilderService $builder, string $title = 'Managed IT'): SitePage
{
    $page = $builder->createPage(['title' => $title, 'type' => 'service', 'headline' => 'Control headline']);
    $builder->addSection($page, ['type' => 'cta', 'heading' => 'Book the fit call']);

    return $builder->publish($page);
}

it('serves a bound experiment sticky per visitor, applies overrides in-memory and counts the impression once', function () {
    $page = publishedWebPage($this->builder);

    $experiment = app(ExperimentService::class)->create(
        ['name' => 'Hero headline', 'type' => 'headline', 'site_page_id' => $page->id],
        [['name' => 'Control', 'is_control' => true], ['name' => 'Challenger', 'content' => ['headline' => 'Challenger headline']]],
    );
    app(ExperimentService::class)->start($experiment);
    $challenger = $experiment->variants->firstWhere('is_control', false);
    app(CurrentOrganization::class)->forget();

    $cookieName = PageExperiments::COOKIE_PREFIX.'page'.$page->id;
    $impressions = fn (): int => (int) ExperimentVariant::withoutGlobalScope('tenant')
        ->where('experiment_id', $experiment->id)->sum('impressions');

    // First visit: a variant is assigned, cookied, and counted exactly once.
    // The page must not be shared-cacheable while the traffic split is live.
    $first = $this->get('/s/'.$page->slug)->assertOk();
    $first->assertCookie($cookieName);
    expect($impressions())->toBe(1)
        ->and((string) $first->headers->get('Cache-Control'))->toContain('no-cache');

    // Returning with the challenger's cookie: its headline overrides the page,
    // nothing is re-counted, and the stored page never mutated.
    $html = $this->withCookie($cookieName, (string) $challenger->id)
        ->get('/s/'.$page->slug)->assertOk()->getContent();

    expect($html)->toContain('Challenger headline')
        ->and($impressions())->toBe(1)
        ->and(SitePage::withoutGlobalScope('tenant')->find($page->id)->headline)->toBe('Control headline');
});

it('credits the conversion from the form submission cookie, org-checked', function () {
    $page = publishedWebPage($this->builder);

    $experiment = app(ExperimentService::class)->create(
        ['name' => 'Hero headline', 'type' => 'headline', 'site_page_id' => $page->id],
        [['name' => 'Control', 'is_control' => true], ['name' => 'Challenger', 'content' => ['headline' => 'Challenger headline']]],
    );
    app(ExperimentService::class)->start($experiment);
    $challenger = $experiment->variants->firstWhere('is_control', false);
    app(ExperimentService::class)->record($challenger, 1, 0); // the exposure

    $form = Form::create([
        'name' => 'Contact', 'slug' => 'web-conv-'.uniqid(), 'status' => 'published',
        'fields' => [['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
    ]);
    app(CurrentOrganization::class)->forget();

    // A rival tenant's experiment: its variant cookie riding OUR form's
    // submission must convert nothing.
    [$rivalOrg] = makeOrganization('Rival Org');
    app(CurrentOrganization::class)->set($rivalOrg);
    $rivalExperiment = app(ExperimentService::class)->create(
        ['name' => 'Rival test', 'type' => 'headline'],
        [['name' => 'Control', 'is_control' => true], ['name' => 'B']],
    );
    app(ExperimentService::class)->start($rivalExperiment);
    $rivalVariant = $rivalExperiment->variants->firstWhere('is_control', false);
    app(ExperimentService::class)->record($rivalVariant, 1, 0);
    app(CurrentOrganization::class)->forget();

    $this->withCookie(PageExperiments::COOKIE_PREFIX.'page'.$page->id, (string) $challenger->id)
        ->withCookie(PageExperiments::COOKIE_PREFIX.'page999', (string) $rivalVariant->id)
        ->post('/f/'.$form->slug, ['email' => 'prospect@example.com'])
        ->assertOk();

    expect(ExperimentVariant::withoutGlobalScope('tenant')->find($challenger->id)->conversions)->toBe(1)
        ->and(ExperimentVariant::withoutGlobalScope('tenant')->find($rivalVariant->id)->conversions)->toBe(0);

    // The current org must be restored for afterEach + the next test.
    app(CurrentOrganization::class)->set($this->org);
});

it('gates the lead magnet behind the submission: signed link, counted downloads, unsigned refused', function () {
    Storage::fake('local');
    Storage::disk('local')->put('magnets/guide.pdf', '%PDF-1.4 fixture');

    $file = File::create([
        'disk' => 'local', 'path' => 'magnets/guide.pdf',
        'name' => 'CMMC Readiness Checklist.pdf', 'mime' => 'application/pdf', 'size' => 16,
    ]);

    $form = Form::create([
        'name' => 'Checklist download', 'slug' => 'web-magnet-'.uniqid(), 'status' => 'published',
        'fields' => [['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
        'settings' => ['lead_magnet_file_id' => $file->id],
    ]);
    app(CurrentOrganization::class)->forget();

    $html = $this->post('/f/'.$form->slug, ['email' => 'reader@example.com'])->assertOk()->getContent();

    preg_match('/href="([^"]*f\/download[^"]*)"/', $html, $m);
    expect($m)->toHaveCount(2);
    $signedUrl = html_entity_decode($m[1]);
    expect($signedUrl)->toContain('signature=');

    // The signed link downloads and is counted; the bare URL is refused.
    $this->get($signedUrl)->assertOk();
    expect($file->refresh()->download_count)->toBe(1);
    $this->get('/f/download/'.$file->id)->assertForbidden();
    expect($file->refresh()->download_count)->toBe(1);
});

it('audits a page on publish when its URL is fetchable, skips gracefully otherwise, and re-audits daily', function () {
    Http::fake(['*' => Http::response('<html><head><title>ok</title><meta name="description" content="x"></head><body><h1>ok</h1></body></html>')]);

    // Fetchable app URL (literal public IP passes the SSRF guard without DNS).
    // forceRootUrl keeps the request's scheme, so it is forced separately.
    URL::forceScheme('https');
    URL::forceRootUrl('https://93.184.216.34');
    $audited = publishedWebPage($this->builder, 'Audited on publish');
    expect(SeoAudit::where('url', 'https://93.184.216.34/s/'.$audited->slug)->exists())->toBeTrue();

    // Unfetchable app URL (loopback): publish itself must still succeed.
    URL::forceRootUrl('http://localhost');
    $skipped = publishedWebPage($this->builder, 'Skipped audit');
    expect($skipped->status)->toBe(SitePage::STATUS_PUBLISHED)
        ->and(SeoAudit::where('url', 'like', '%'.$skipped->slug)->exists())->toBeFalse();

    // WEB-053: the daily command re-audits every published page in its own org.
    URL::forceRootUrl('https://93.184.216.34');
    app(CurrentOrganization::class)->forget();
    $this->artisan('web:audit-published')->expectsOutputToContain('Audited 2 published pages')->assertExitCode(0);

    app(CurrentOrganization::class)->set($this->org);
    expect(SeoAudit::where('url', 'https://93.184.216.34/s/'.$audited->slug)->count())->toBe(2)
        ->and(SeoAudit::where('url', 'https://93.184.216.34/s/'.$skipped->slug)->count())->toBe(1);

    $schedule = collect(app(Schedule::class)->events());
    expect($schedule->contains(fn ($event) => str_contains((string) $event->command, 'web:audit-published')))->toBeTrue();

    URL::forceRootUrl('http://localhost');
});

it('holds the performance budget and serves real HTTP caching with an ETag/304 round trip', function () {
    $page = publishedWebPage($this->builder, 'Budget page');
    $this->builder->addSection($page, ['type' => 'faq', 'heading' => 'FAQ', 'body' => 'Do you offer 24/7 support? Yes, staffed around the clock.']);
    app(CurrentOrganization::class)->forget();

    $response = $this->get('/s/'.$page->slug)->assertOk();
    $html = $response->getContent();

    // WEB-038/041: one document under the ceiling, one inline stylesheet, no
    // external CSS, and no executable JavaScript — a fresh org has neither the
    // opt-in chat widget nor a tracking key, so every script tag is JSON-LD.
    expect(strlen($html))->toBeLessThan(100 * 1024)
        ->and(substr_count($html, '<style'))->toBe(1)
        ->and($html)->not->toContain('rel="stylesheet"');

    preg_match_all('/<script[^>]*>/i', $html, $scripts);
    expect($scripts[0])->not->toBeEmpty();
    foreach ($scripts[0] as $tag) {
        expect($tag)->toContain('application/ld+json');
    }

    // WEB-042/043: shared cache headers plus ETag, and a correct 304.
    $cacheControl = (string) $response->headers->get('Cache-Control');
    $etag = (string) $response->headers->get('ETag');
    expect($cacheControl)->toContain('public')
        ->and($cacheControl)->toContain('max-age=300')
        ->and($cacheControl)->toContain('stale-while-revalidate=600')
        ->and($etag)->not->toBe('');

    $this->get('/s/'.$page->slug, ['If-None-Match' => $etag])->assertStatus(304);
});

it('drafts each gallery template as an ordered buyer-journey structure that passes the health checks it can', function () {
    $templates = SiteBuilderService::templates();
    expect(array_keys($templates))->toBe(['msp_home', 'service_page', 'vertical_page', 'lead_magnet_landing']);

    foreach ($templates as $key => $template) {
        $page = $this->builder->applyTemplate($key);
        $sections = PageSection::where('site_page_id', $page->id)->orderBy('sort_order')->get();

        expect($page->status)->toBe(SitePage::STATUS_DRAFT)
            ->and($page->type)->toBe($template['type'])
            ->and($sections->pluck('type')->all())->toBe(array_column($template['sections'], 'type'))
            ->and($sections->pluck('sort_order')->all())->toBe(range(0, count($template['sections']) - 1));

        // Every blueprint ends in a conversion path, so the draft already
        // passes the structural checks; content checks stay honest — the
        // guidance copy is a scaffold, not a finished page.
        $checks = collect(app(SiteHealthService::class)->score($page)['checks'])->keyBy('check');
        expect($checks['Has a call to action']['passed'])->toBeTrue()
            ->and($checks['Has visible sections']['passed'])->toBeTrue()
            ->and($checks['Published']['passed'])->toBeFalse();
    }

    // WEB-007: the manage endpoint drafts one too (unique slug via suffix).
    $this->actingAs($this->owner)->post(route('web.pages.template'), ['template' => 'msp_home'])
        ->assertRedirect()->assertSessionHas('status');
    expect(SitePage::where('type', 'home')->count())->toBe(2);
});
