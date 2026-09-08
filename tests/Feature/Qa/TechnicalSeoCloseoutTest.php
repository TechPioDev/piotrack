<?php

declare(strict_types=1);

/**
 * Technical SEO close-out (Phase 48 — TSEO-019/023/024/025/026).
 *
 * Search Console monitoring through a new provider seam, Core Web Vitals as
 * first-party lab checks plus a field-data seam, the penalty audit whose five
 * signals each cite their numbers, the recovery plan derived only from
 * triggered findings, and the backlink-profile health audit on the P45
 * link-data foundation.
 */

use App\Models\BrandProfile;
use App\Models\ContentPiece;
use App\Models\Keyword;
use App\Models\KeywordRanking;
use App\Models\SitePage;
use App\Seo\Contracts\SearchConsoleProvider;
use App\Seo\Contracts\WebVitalsProvider;
use App\Services\Seo\BacklinkAuditService;
use App\Services\Seo\CwvLabAuditor;
use App\Services\Seo\PenaltyAuditService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('TechSeo Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function setResearchDomain(string $url): void
{
    BrandProfile::first()?->update(['website_url' => $url])
        ?? BrandProfile::create(['legal_name' => 'Acme Managed IT', 'website_url' => $url]);
}

it('monitors Search Console through the seam: deterministic, provider-labeled, on the page', function () {
    setResearchDomain('https://acme-msp.example');

    $provider = app(SearchConsoleProvider::class);
    $first = $provider->searchPerformance('acme-msp.example');

    expect($provider->name())->toBe('fixture')
        ->and($first)->not->toBeEmpty()
        ->and($first[0])->toHaveKeys(['query', 'clicks', 'impressions', 'ctr', 'position'])
        // Deterministic: the same site always yields the same rows.
        ->and($provider->searchPerformance('acme-msp.example'))->toBe($first)
        ->and($provider->indexCoverage('acme-msp.example')['indexed'])->toBeGreaterThan(0)
        // No site is ever implied to be penalized by default.
        ->and($provider->manualActions('acme-msp.example'))->toBe([]);

    $props = $this->actingAs($this->owner)->get(route('seo.health'))->assertOk()->viewData('page')['props'];
    expect($props['search_console']['provider'])->toBe('fixture')
        ->and($props['search_console']['performance'])->not->toBeEmpty()
        ->and($props['search_console']['manual_actions'])->toBe([])
        ->and($props['penalty']['findings'])->toHaveCount(5);
});

it('audits penalties from real signals, each finding citing its numbers', function () {
    // The self-describing fixture marker puts a manual action on this host.
    setResearchDomain('https://penalized-msp.example');

    // A real ranking collapse: best position 3, latest 25 (-22).
    $keyword = Keyword::create(['phrase' => 'managed it services philadelphia', 'intent' => 'commercial', 'is_tracked' => true]);
    KeywordRanking::create(['keyword_id' => $keyword->id, 'engine' => 'google', 'position' => 3, 'is_competitor' => false, 'provider' => 'fixture', 'checked_at' => now()->subDays(30)]);
    KeywordRanking::create(['keyword_id' => $keyword->id, 'engine' => 'google', 'position' => 25, 'is_competitor' => false, 'provider' => 'fixture', 'checked_at' => now()]);

    // Thin published content and duplicated titles across published pages.
    ContentPiece::create(['title' => 'Stub post', 'slug' => 'stub', 'content_type' => 'article', 'status' => 'published', 'published_at' => now(), 'body' => 'Too short to rank.']);
    SitePage::create(['type' => 'service', 'slug' => 'a', 'title' => 'IT Support', 'status' => SitePage::STATUS_PUBLISHED]);
    SitePage::create(['type' => 'service', 'slug' => 'b', 'title' => 'IT Support', 'status' => SitePage::STATUS_PUBLISHED]);

    $audit = app(PenaltyAuditService::class)->audit();
    $findings = collect($audit['findings'])->keyBy('key');

    expect($findings['manual_actions']['status'])->toBe('risk')
        ->and($findings['manual_actions']['evidence'])->toContain('Unnatural links')
        ->and($findings['manual_actions']['source'])->toBe('fixture')
        ->and($findings['ranking_drops']['status'])->toBe('risk')
        ->and($findings['ranking_drops']['evidence'])->toContain('1 of 1')
        ->and($findings['ranking_drops']['source'])->toBe('rankings')
        ->and($findings['thin_content']['status'])->toBe('warning')
        ->and($findings['thin_content']['evidence'])->toContain('Stub post')
        ->and($findings['duplicates']['status'])->toBe('warning')
        ->and($findings['toxic_links']['evidence'])->toContain(' of ')
        ->and($audit['risk'])->toBeGreaterThanOrEqual(4);
});

it('derives the recovery plan only from triggered findings, and refuses to invent work when clean', function () {
    // Clean tenant: no domain, no rankings, no content — nothing to recover from.
    $clean = app(PenaltyAuditService::class)->recoveryPlan();
    expect($clean['needed'])->toBeFalse()
        ->and($clean['steps'])->toBe([]);

    // Now the penalized state from the audit test.
    setResearchDomain('https://penalized-msp.example');
    ContentPiece::create(['title' => 'Stub post', 'slug' => 'stub2', 'content_type' => 'article', 'status' => 'published', 'published_at' => now(), 'body' => 'Too short.']);

    $plan = app(PenaltyAuditService::class)->recoveryPlan();
    $tools = collect($plan['steps'])->pluck('tool')->implode(' ');

    expect($plan['needed'])->toBeTrue()
        ->and($tools)->toContain('reconsideration')
        ->and($tools)->toContain('Content editor')
        // Every step carries the evidence that triggered it.
        ->and(collect($plan['steps'])->every(fn (array $s) => $s['reason'] !== ''))->toBeTrue();
});

it('runs the CWV lab audit as a pure function and the field seam behind the guarded endpoint', function () {
    $auditor = app(CwvLabAuditor::class);

    $bad = $auditor->analyze(<<<'HTML'
<html><head>
<link rel="stylesheet" href="/a.css"><link rel="stylesheet" href="/b.css"><link rel="stylesheet" href="/c.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter">
<script src="/blocking.js"></script>
</head><body>
<img src="/hero.jpg" loading="lazy">
<img src="/second.jpg">
<iframe src="https://maps.example"></iframe>
</body></html>
HTML, 'https://site.example/');

    $byKey = collect($bad['checks'])->keyBy('key');
    expect($byKey['sync_scripts']['status'])->toBe('fail')
        ->and($byKey['sync_scripts']['metric'])->toBe('LCP')
        ->and($byKey['blocking_css']['status'])->toBe('warn')
        ->and($byKey['font_display']['status'])->toBe('warn')
        ->and($byKey['hero_image']['status'])->toBe('warn')
        ->and($byKey['unsized_images']['status'])->toBe('warn')
        ->and($byKey['unsized_images']['metric'])->toBe('CLS')
        ->and($byKey['unsized_iframes']['status'])->toBe('warn')
        ->and($bad['by_metric']['LCP'])->toBeGreaterThanOrEqual(4)
        ->and($byKey['sync_scripts']['fix'])->toContain('defer');

    $good = $auditor->analyze('<html><head><link rel="stylesheet" href="/one.css"></head><body><img src="/x.jpg" width="800" height="400"><p>Fast page.</p></body></html>', 'https://site.example/');
    expect($good['issues'])->toBe(0);

    // Field data through the seam: deterministic, thresholds applied.
    $vitals = app(WebVitalsProvider::class);
    $field = $vitals->fieldData('https://site.example/');
    expect($vitals->name())->toBe('fixture')
        ->and($field['verdicts'])->toHaveKeys(['lcp', 'cls', 'inp'])
        ->and($vitals->fieldData('https://site.example/'))->toBe($field);

    // The endpoint: SSRF-guarded fetch (public literal IP dodges DNS), lab + field inline.
    Http::fake(['*' => Http::response('<html><head></head><body><p>ok</p></body></html>')]);
    $props = $this->actingAs($this->owner)
        ->get(route('seo.health', ['cwv_url' => 'https://93.184.216.34/']))
        ->assertOk()->viewData('page')['props'];

    expect($props['cwv']['error'])->toBeNull()
        ->and($props['cwv']['lab']['checks'])->not->toBeEmpty()
        ->and($props['cwv']['field']['verdicts'])->toHaveKeys(['lcp', 'cls', 'inp'])
        ->and($props['vitals_provider'])->toBe('fixture');

    // A private address is refused, never fetched.
    $refused = $this->actingAs($this->owner)
        ->get(route('seo.health', ['cwv_url' => 'https://169.254.169.254/latest/meta-data/']))
        ->assertOk()->viewData('page')['props'];
    expect($refused['cwv']['error'])->not->toBeNull();
});

it('audits the backlink profile: anchors bucketed transparently, toxic share, top sources', function () {
    setResearchDomain('https://acme-msp.example');

    $profile = app(BacklinkAuditService::class)->profile();

    expect($profile['total'])->toBeGreaterThan(0)
        ->and($profile['anchors']['branded'] + $profile['anchors']['commercial'] + $profile['anchors']['other'])->toBe($profile['total'])
        // The fixture always plants exact-match money anchors for the heuristics to catch.
        ->and($profile['anchors']['commercial'])->toBeGreaterThanOrEqual(1)
        ->and($profile['toxic_share_pct'])->not->toBeNull()
        ->and(count($profile['top_sources']))->toBeLessThanOrEqual(5)
        ->and($profile['top_sources'][0]['domain_authority'])->toBeGreaterThanOrEqual($profile['top_sources'][1]['domain_authority']);

    // The Links page carries the profile card.
    $props = $this->actingAs($this->owner)->get(route('seo.links.index'))->assertOk()->viewData('page')['props'];
    expect($props['profile']['anchors'])->toHaveKeys(['branded', 'commercial', 'other']);
});
