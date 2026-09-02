<?php

declare(strict_types=1);

/**
 * Competitive Intelligence close-out (Phase 14 — CINT-005).
 *
 * Competitor content monitoring without a data vendor: the monitor fetches the
 * competitor's public site (sitemap first, homepage links as fallback),
 * captures url + title + content hash per page, and stores the diff against
 * the previous capture. First capture is the baseline; later captures name
 * exactly what appeared, changed and disappeared — nothing invented.
 */

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\Competitor;
use App\Models\CompetitorSnapshot;
use App\Services\Analytics\CompetitorContentMonitor;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Http;

const RIVAL = 'https://93.184.216.34';

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Watcher MSP');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
    $this->rival = Competitor::create(['name' => 'Rival MSP', 'domain' => '93.184.216.34', 'is_tracked' => true]);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function rivalSite(array $overrides = []): void
{
    $page = fn (string $title, string $body) => Http::response("<html><head><title>{$title}</title></head><body><p>{$body}</p></body></html>");

    Http::fake(array_merge([
        RIVAL.'/sitemap.xml' => Http::response(
            '<urlset><url><loc>'.RIVAL.'/</loc></url><url><loc>'.RIVAL.'/services</loc></url><url><loc>'.RIVAL.'/pricing</loc></url></urlset>'
        ),
        RIVAL.'/' => $page('Rival MSP', 'Managed IT for Philadelphia.'),
        RIVAL.'/services' => $page('Services | Rival MSP', 'Cybersecurity and cloud.'),
        RIVAL.'/pricing' => $page('Pricing | Rival MSP', 'From $99 per seat.'),
    ], $overrides));
}

it('captures the baseline from their sitemap, everything new exactly once', function () {
    rivalSite();

    $snapshot = app(CompetitorContentMonitor::class)->check($this->rival);

    expect($snapshot->pages_count)->toBe(3)
        ->and($snapshot->new_pages)->toHaveCount(3)
        ->and($snapshot->changed_pages)->toBe([])
        ->and($snapshot->removed_pages)->toBe([])
        ->and(collect($snapshot->pages)->firstWhere('url', RIVAL.'/pricing')['title'])->toBe('Pricing | Rival MSP');
});

it('diffs a later capture: what appeared, what changed, what disappeared', function () {
    // One fake covering both runs: sequences pop per request, so the second
    // check sees the updated sitemap (/pricing gone, /cmmc new) and the
    // rewritten services page.
    $html = fn (string $title, string $body) => "<html><head><title>{$title}</title></head><body><p>{$body}</p></body></html>";
    Http::fake([
        RIVAL.'/sitemap.xml' => Http::sequence()
            ->push('<urlset><url><loc>'.RIVAL.'/</loc></url><url><loc>'.RIVAL.'/services</loc></url><url><loc>'.RIVAL.'/pricing</loc></url></urlset>')
            ->push('<urlset><url><loc>'.RIVAL.'/</loc></url><url><loc>'.RIVAL.'/services</loc></url><url><loc>'.RIVAL.'/cmmc</loc></url></urlset>'),
        RIVAL.'/services' => Http::sequence()
            ->push($html('Services | Rival MSP', 'Cybersecurity and cloud.'))
            ->push($html('Services | Rival MSP', 'Cybersecurity, cloud AND NEW co-managed IT.')),
        RIVAL.'/pricing' => Http::response($html('Pricing | Rival MSP', 'From $99 per seat.')),
        RIVAL.'/cmmc' => Http::response($html('CMMC | Rival MSP', 'New compliance practice.')),
        RIVAL.'/' => Http::response($html('Rival MSP', 'Managed IT for Philadelphia.')),
    ]);

    app(CompetitorContentMonitor::class)->check($this->rival);
    $second = app(CompetitorContentMonitor::class)->check($this->rival);

    expect($second->new_pages)->toBe([RIVAL.'/cmmc'])
        ->and($second->changed_pages)->toBe([RIVAL.'/services'])
        ->and($second->removed_pages)->toBe([RIVAL.'/pricing'])
        ->and(CompetitorSnapshot::where('competitor_id', $this->rival->id)->count())->toBe(2);
});

it('falls back to homepage links when they publish no sitemap', function () {
    $page = fn (string $title, string $body, string $extra = '') => Http::response("<html><head><title>{$title}</title></head><body><p>{$body}</p>{$extra}</body></html>");
    Http::fake([
        RIVAL.'/sitemap.xml' => Http::response('', 404),
        RIVAL.'/about' => $page('About | Rival', 'The team.'),
        RIVAL.'/' => $page('Rival MSP', 'Home.', '<a href="/about">About</a><a href="https://elsewhere.example/x">ext</a>'),
    ]);

    $snapshot = app(CompetitorContentMonitor::class)->check($this->rival);

    expect(collect($snapshot->pages)->pluck('url')->all())->toBe([RIVAL.'/', RIVAL.'/about']);
});

it('refuses unfetchable domains through the SSRF guard, as a validation error over HTTP', function () {
    app(CurrentOrganization::class)->set($this->org);
    $internal = Competitor::create(['name' => 'Sneaky', 'domain' => '169.254.169.254', 'is_tracked' => true]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)
        ->from(route('analytics.competitors.index'))
        ->post(route('analytics.competitors.check', $internal->id))
        ->assertRedirect(route('analytics.competitors.index'))
        ->assertSessionHasErrors('domain');

    expect(CompetitorSnapshot::withoutGlobalScopes()->count())->toBe(0);
});

it('runs over HTTP with audit trail, permission gating and tenant isolation', function () {
    rivalSite();

    $this->actingAs($this->owner)->post(route('analytics.competitors.check', $this->rival->id))->assertRedirect();

    expect(CompetitorSnapshot::withoutGlobalScopes()->count())->toBe(1)
        ->and(AuditLog::withoutGlobalScope('tenant')->where('action', 'analytics.competitor.content_checked')->exists())->toBeTrue();

    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)->post(route('analytics.competitors.check', $this->rival->id))->assertForbidden();

    [, $otherOwner] = makeOrganization('Other Org');
    $this->actingAs($otherOwner)->post(route('analytics.competitors.check', $this->rival->id))->assertNotFound();
});
