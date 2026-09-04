<?php

declare(strict_types=1);

/**
 * CRO close-out (Phase 27 — CRO-010/011/012/013/014/015/016).
 *
 * First-party heatmaps, behavior analysis and bounce rates from the pixel's
 * own click/scroll/pageview events; step-level funnel drop-off; aggregate
 * conversion paths; and optimization recommendations that cite the number that
 * triggered them — all guarded when data is too thin, session replay honestly
 * left to providers.
 */

use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\SitePage;
use App\Models\Visitor;
use App\Models\VisitorEvent;
use App\Services\Analytics\BehaviorAnalytics;
use App\Services\Analytics\FunnelInsights;
use App\Support\CurrentOrganization;
use Illuminate\Support\Carbon;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('CRO Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function croVisitor(string $key): Visitor
{
    return Visitor::create(['visitor_key' => $key, 'first_seen_at' => now(), 'last_seen_at' => now(), 'visits' => 1]);
}

function croEvent(Visitor $visitor, string $type, string $path, Carbon $at, ?int $x = null, ?int $y = null, ?string $title = null): void
{
    VisitorEvent::forceCreate([
        'organization_id' => $visitor->organization_id, 'visitor_id' => $visitor->id,
        'type' => $type, 'path' => $path, 'title' => $title,
        'x_pct' => $x, 'y_pct' => $y,
        'created_at' => $at, 'updated_at' => $at,
    ]);
}

it('serves click and scroll capture in the pixel and ingests both, rejecting out-of-range values', function () {
    $this->org->forceFill(['tracking_key' => 'tk_crotest0001'])->save();
    app(CurrentOrganization::class)->forget();

    $js = $this->get(route('public.track.script', 'tk_crotest0001'))->assertOk()->getContent();
    expect($js)->toContain("type: 'click'")
        ->and($js)->toContain("type: 'scroll'")
        ->and($js)->toContain('x_pct')
        ->and($js)->toContain('pagehide');

    $this->postJson(route('public.track.event', 'tk_crotest0001'), [
        'vid' => 'crovisitor01', 'type' => 'pageview', 'path' => '/pricing',
    ])->assertOk();
    $this->postJson(route('public.track.event', 'tk_crotest0001'), [
        'vid' => 'crovisitor01', 'type' => 'click', 'path' => '/pricing', 'title' => 'a Book a call', 'x_pct' => 55, 'y_pct' => 12,
    ])->assertOk();
    $this->postJson(route('public.track.event', 'tk_crotest0001'), [
        'vid' => 'crovisitor01', 'type' => 'scroll', 'path' => '/pricing', 'y_pct' => 80,
    ])->assertOk();
    $this->postJson(route('public.track.event', 'tk_crotest0001'), [
        'vid' => 'crovisitor01', 'type' => 'click', 'path' => '/pricing', 'x_pct' => 140, 'y_pct' => 10,
    ])->assertStatus(422);

    app(CurrentOrganization::class)->set($this->org);
    $click = VisitorEvent::where('type', 'click')->firstOrFail();
    expect($click->x_pct)->toBe(55)->and($click->y_pct)->toBe(12)->and($click->title)->toBe('a Book a call')
        ->and(VisitorEvent::where('type', 'scroll')->firstOrFail()->y_pct)->toBe(80);

    // Clicks never count as pageviews.
    expect(Visitor::where('visitor_key', 'crovisitor01')->firstOrFail()->page_views)->toBe(1);
});

it('builds the click grid, top targets and scroll buckets per page', function () {
    $visitor = croVisitor('heatmapvis01');
    croEvent($visitor, 'click', '/pricing', now(), 5, 5, 'a Book a call');
    croEvent($visitor, 'click', '/pricing', now(), 8, 9, 'a Book a call');
    croEvent($visitor, 'click', '/pricing', now(), 95, 95, 'button Submit');
    croEvent($visitor, 'click', '/other', now(), 50, 50, 'a Elsewhere');
    croEvent($visitor, 'scroll', '/pricing', now(), null, 30);
    croEvent($visitor, 'scroll', '/pricing', now(), null, 90);

    $heatmap = app(BehaviorAnalytics::class)->heatmap('/pricing');

    expect($heatmap['clicks'])->toBe(3)
        ->and($heatmap['grid'][0][0])->toBe(2)   // both top-left clicks share the first bucket
        ->and($heatmap['grid'][9][9])->toBe(1)
        ->and($heatmap['targets'][0])->toBe(['label' => 'a Book a call', 'clicks' => 2])
        ->and($heatmap['scroll']['samples'])->toBe(2)
        ->and($heatmap['scroll']['avg_depth'])->toBe(60)
        ->and($heatmap['scroll']['buckets']['26-50'])->toBe(1)
        ->and($heatmap['scroll']['buckets']['76-100'])->toBe(1);

    $pages = collect(app(BehaviorAnalytics::class)->pages());
    expect($pages->firstWhere('path', '/pricing'))->toBeNull(); // no pageviews -> not a page row yet
});

it('rebuilds sessions for bounce rates with the 30-minute window and the small-sample guard', function () {
    $base = now()->subDay();

    // Five visitors land on /landing: three bounce, two go deeper.
    foreach (range(1, 5) as $i) {
        $visitor = croVisitor("bouncevis0{$i}");
        croEvent($visitor, 'pageview', '/landing', $base->copy()->addMinutes($i));
        if ($i > 3) {
            croEvent($visitor, 'pageview', '/pricing', $base->copy()->addMinutes($i + 5));
        }
    }

    // One of them returns hours later straight to /pricing: a separate
    // single-page session that bounces — attributed to /pricing, not /landing.
    croEvent(Visitor::where('visitor_key', 'bouncevis01')->firstOrFail(), 'pageview', '/pricing', $base->copy()->addHours(6));

    $rows = collect(app(BehaviorAnalytics::class)->bounceRates())->keyBy('landing_path');

    expect($rows['/landing']['sessions'])->toBe(5)
        ->and($rows['/landing']['bounces'])->toBe(3)
        ->and($rows['/landing']['bounce_rate'])->toBe(60)
        ->and($rows['/landing']['insufficient'])->toBeFalse()
        // /pricing has one landing session: guarded, no rate.
        ->and($rows['/pricing']['sessions'])->toBe(1)
        ->and($rows['/pricing']['bounce_rate'])->toBeNull()
        ->and($rows['/pricing']['insufficient'])->toBeTrue();
});

it('computes cumulative step drop-off with the weakest step flagged, guarded under ten leads', function () {
    expect(app(FunnelInsights::class)->dropOff()['insufficient_data'])->toBeTrue();

    foreach (range(1, 6) as $i) {
        Contact::create(['first_name' => "L{$i}", 'email' => "l{$i}@x.com", 'lifecycle_stage' => 'lead']);
    }
    foreach (range(1, 3) as $i) {
        Contact::create(['first_name' => "M{$i}", 'email' => "m{$i}@x.com", 'lifecycle_stage' => 'mql']);
    }
    $sql = Contact::create(['first_name' => 'S1', 'email' => 's1@x.com', 'lifecycle_stage' => 'sql']);
    $customer = Contact::create(['first_name' => 'C1', 'email' => 'c1@x.com', 'lifecycle_stage' => 'customer']);
    $page = BookingPage::create(['name' => 'Fit call', 'slug' => 'cro-fit-call', 'meeting_type' => 'consultation', 'duration_minutes' => 30, 'assignment' => 'fixed', 'is_active' => true]);
    Booking::create(['booking_page_id' => $page->id, 'contact_id' => $sql->id, 'name' => 'Fit call', 'email' => 's1@x.com', 'status' => 'confirmed', 'scheduled_at' => now()->addDay()]);

    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $won = $pipeline->stages()->where('is_won', true)->firstOrFail();
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $won->id, 'name' => 'Won', 'value' => 100, 'status' => 'won', 'contact_id' => $customer->id]);

    $report = app(FunnelInsights::class)->dropOff();
    $steps = collect($report['steps'])->keyBy('step');

    expect($report['insufficient_data'])->toBeFalse()
        ->and($steps['leads']['count'])->toBe(11)
        ->and($steps['mql']['count'])->toBe(5)   // mql + sql + customer reached MQL
        ->and($steps['sql']['count'])->toBe(2)   // sql + customer reached SQL
        ->and($steps['meetings']['count'])->toBe(1)
        ->and($steps['won']['count'])->toBe(1)
        ->and($steps['mql']['conversion_from_previous'])->toBe(45)  // 5/11
        ->and($steps['sql']['conversion_from_previous'])->toBe(40)  // 2/5 — the weakest
        ->and($steps['meetings']['conversion_from_previous'])->toBe(50)
        ->and($report['weakest_step'])->toBe('mql → sql');
});

it('aggregates first-touch to last-touch paths for won deals only', function () {
    $winner = Contact::create(['first_name' => 'Win', 'email' => 'win@x.com', 'lead_source' => 'organic', 'lifecycle_stage' => 'customer']);
    $loser = Contact::create(['first_name' => 'Lose', 'email' => 'lose@x.com', 'lead_source' => 'paid', 'lifecycle_stage' => 'sql']);

    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $won = $pipeline->stages()->where('is_won', true)->firstOrFail();
    $open = $pipeline->stages()->orderBy('sort_order')->firstOrFail();
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $won->id, 'name' => 'W', 'value' => 100, 'status' => 'won', 'contact_id' => $winner->id]);
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $open->id, 'name' => 'O', 'value' => 100, 'status' => 'open', 'contact_id' => $loser->id]);

    $paths = app(FunnelInsights::class)->conversionPaths();
    expect($paths)->toHaveCount(1)
        ->and($paths[0]['wins'])->toBe(1)
        ->and($paths[0]['path'])->toContain('organic');
});

it('recommends from the numbers and stays honest on an empty org', function () {
    $empty = app(FunnelInsights::class)->recommendations();
    expect($empty)->toHaveCount(1)
        ->and($empty[0]['area'])->toBe('data')
        ->and($empty[0]['evidence'])->toContain('Fewer than 10 leads');

    // A funnel with published pages but no experiments, and a high-bounce
    // landing page, produces recommendations that cite those exact numbers.
    foreach (range(1, 12) as $i) {
        Contact::create(['first_name' => "R{$i}", 'email' => "r{$i}@x.com", 'lifecycle_stage' => $i <= 6 ? 'lead' : 'mql']);
    }
    SitePage::create(['type' => 'landing', 'slug' => 'cro-live', 'title' => 'Live', 'status' => SitePage::STATUS_PUBLISHED]);

    $base = now()->subDay();
    foreach (range(1, 5) as $i) {
        $visitor = croVisitor("recvis0{$i}");
        croEvent($visitor, 'pageview', '/s/cro-live', $base->copy()->addMinutes($i));
        if ($i === 5) {
            croEvent($visitor, 'pageview', '/pricing', $base->copy()->addMinutes($i + 2));
        }
    }

    $recommendations = collect(app(FunnelInsights::class)->recommendations());
    $areas = $recommendations->pluck('area');

    expect($areas)->toContain('bounce')->toContain('experiments');
    $bounce = $recommendations->firstWhere('area', 'bounce');
    expect($bounce['evidence'])->toContain('/s/cro-live')->toContain('80%');
    $experiments = $recommendations->firstWhere('area', 'experiments');
    expect($experiments['evidence'])->toContain('1 published pages and 0 running experiments');

    // The behavior page renders it all for the analyst.
    app(CurrentOrganization::class)->forget();
    $props = $this->actingAs($this->owner)->get(route('analytics.behavior.index'))->assertOk()->viewData('page')['props'];
    expect(collect($props['bounces'])->firstWhere('landing_path', '/s/cro-live')['bounce_rate'])->toBe(80);
    app(CurrentOrganization::class)->set($this->org);
});
