<?php

declare(strict_types=1);

/**
 * Growth Command Center (Module 02). The dashboard's numbers must be honestly
 * windowed: rows land in the right 30-day period, deltas follow the math,
 * a zero previous period yields a null delta (rendered "new", never +∞), and
 * an empty tenant gets zeros — not invented performance.
 */

use App\Models\ChatConversation;
use App\Models\ChatWidget;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\GrowthScore;
use App\Models\Pipeline;
use App\Models\SalesAlert;
use App\Services\Chat\DefaultChatFlow;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Command Org');
    subscribeOrganization($this->org, 'enterprise');
});

function dashboardProps($test): array
{
    return $test->actingAs($test->owner)->get(route('dashboard'))->assertOk()->viewData('page')['props'];
}

it('windows lead and win KPIs against the previous 30 days', function () {
    app(CurrentOrganization::class)->set($this->org);
    // Current window: 2 leads. Previous window: 1. Outside both: 1.
    Contact::create(['first_name' => 'Now A', 'email' => 'a@w.test']);
    Contact::create(['first_name' => 'Now B', 'email' => 'b@w.test'])->forceFill(['created_at' => now()->subDays(10)])->save();
    Contact::create(['first_name' => 'Prev', 'email' => 'c@w.test'])->forceFill(['created_at' => now()->subDays(45)])->save();
    Contact::create(['first_name' => 'Old', 'email' => 'd@w.test'])->forceFill(['created_at' => now()->subDays(90)])->save();

    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $wonStage = $pipeline->stages()->where('is_won', true)->firstOrFail();
    $open = $pipeline->stages()->where('is_won', false)->where('is_lost', false)->firstOrFail();
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $wonStage->id, 'name' => 'Won now', 'value' => 100, 'mrr' => 40000, 'status' => 'won', 'closed_at' => now()->subDays(3)]);
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $wonStage->id, 'name' => 'Won prev', 'value' => 100, 'mrr' => 10000, 'status' => 'won', 'closed_at' => now()->subDays(40)]);
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $open->id, 'name' => 'Open', 'value' => 250000, 'status' => 'open']);
    app(CurrentOrganization::class)->forget();

    $kpis = dashboardProps($this)['kpis'];

    expect($kpis['new_leads']['value'])->toBe(2)
        ->and($kpis['new_leads']['previous'])->toBe(1)
        ->and($kpis['new_leads']['delta_pct'])->toBe(100.0)
        ->and($kpis['deals_won']['value'])->toBe(1)
        ->and($kpis['deals_won']['previous'])->toBe(1)
        ->and($kpis['deals_won']['delta_pct'])->toBe(0.0)
        ->and($kpis['new_mrr']['value'])->toBe(40000)
        ->and($kpis['new_mrr']['previous'])->toBe(10000)
        ->and($kpis['new_mrr']['delta_pct'])->toBe(300.0)
        ->and($kpis['qualified_pipeline'])->toBe(250000);
});

it('rescopes every windowed number to the selected range (DSGN-006)', function () {
    app(CurrentOrganization::class)->set($this->org);
    // Inside 30d, inside 60d only, and in the 60d previous window (61–120d ago).
    Contact::create(['first_name' => 'Recent', 'email' => 'r@w.test']);
    Contact::create(['first_name' => 'Sixty', 'email' => 's@w.test'])->forceFill(['created_at' => now()->subDays(45)])->save();
    Contact::create(['first_name' => 'PrevSixty', 'email' => 'p@w.test'])->forceFill(['created_at' => now()->subDays(90)])->save();
    app(CurrentOrganization::class)->forget();

    $props = $this->actingAs($this->owner)->get(route('dashboard', ['range' => 60]))->assertOk()->viewData('page')['props'];

    expect($props['range'])->toBe(60)
        ->and($props['ranges'])->toBe([30, 60, 90])
        ->and($props['kpis']['new_leads']['value'])->toBe(2)   // 45d-old lead now counts
        ->and($props['kpis']['new_leads']['previous'])->toBe(1) // the 90d-old lead is the previous window
        ->and($props['leadTrend'])->toHaveCount(60);

    // A nonsense range falls back to the default instead of erroring.
    $fallback = $this->actingAs($this->owner)->get(route('dashboard', ['range' => 7]))->assertOk()->viewData('page')['props'];
    expect($fallback['range'])->toBe(30)->and($fallback['leadTrend'])->toHaveCount(30);
});

it('reports null delta when the previous window is empty', function () {
    app(CurrentOrganization::class)->set($this->org);
    Contact::create(['first_name' => 'Only', 'email' => 'only@w.test']);
    app(CurrentOrganization::class)->forget();

    $kpis = dashboardProps($this)['kpis'];

    expect($kpis['new_leads']['value'])->toBe(1)
        ->and($kpis['new_leads']['delta_pct'])->toBeNull();
});

it('accumulates won MRR across the window and puts leads on their day', function () {
    app(CurrentOrganization::class)->set($this->org);
    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $wonStage = $pipeline->stages()->where('is_won', true)->firstOrFail();
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $wonStage->id, 'name' => 'W1', 'value' => 100, 'mrr' => 10000, 'status' => 'won', 'closed_at' => now()->subDays(5)]);
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $wonStage->id, 'name' => 'W2', 'value' => 100, 'mrr' => 5000, 'status' => 'won', 'closed_at' => now()->subDays(1)]);
    Contact::create(['first_name' => 'T', 'email' => 't@w.test']);
    app(CurrentOrganization::class)->forget();

    $props = dashboardProps($this);

    expect($props['leadTrend'])->toHaveCount(30)
        ->and(end($props['leadTrend'])['value'])->toBe(1)
        // Cumulative: ends at the window's total, and never decreases.
        ->and(end($props['mrrTrend'])['value'])->toBe(15000);
    $values = collect($props['mrrTrend'])->pluck('value')->all();
    expect($values)->toBe(collect($values)->sort()->values()->all());
});

it('surfaces alerts, waiting chats and hot leads as attention items', function () {
    app(CurrentOrganization::class)->set($this->org);
    $contact = Contact::create(['first_name' => 'Hot', 'email' => 'hot@w.test', 'lead_score' => 75]);
    SalesAlert::create(['contact_id' => $contact->id, 'type' => 'score_threshold', 'message' => 'Hot Lead crossed 60 points', 'is_read' => false]);
    $widget = ChatWidget::create(['name' => 'W', 'status' => 'active', 'flow' => DefaultChatFlow::definition(), 'theme' => [], 'consent' => ['required' => false], 'settings' => [], 'allowed_domains' => []]);
    ChatConversation::create(['chat_widget_id' => $widget->id, 'status' => 'waiting', 'visitor_id' => 'v1']);
    app(CurrentOrganization::class)->forget();

    $attention = dashboardProps($this)['attention'];

    expect($attention['waiting_chats'])->toBe(1)
        ->and($attention['hot_leads'])->toBe(1)
        ->and($attention['alerts'])->toHaveCount(1)
        ->and($attention['alerts'][0]['message'])->toContain('crossed 60')
        ->and($attention['alerts'][0]['contact'])->toBe('Hot');
});

it('lists the biggest open deals only, richest first', function () {
    app(CurrentOrganization::class)->set($this->org);
    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $open = $pipeline->stages()->where('is_won', false)->where('is_lost', false)->firstOrFail();
    $wonStage = $pipeline->stages()->where('is_won', true)->firstOrFail();
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $open->id, 'name' => 'Small', 'value' => 10000, 'status' => 'open']);
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $open->id, 'name' => 'Big', 'value' => 900000, 'status' => 'open']);
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $wonStage->id, 'name' => 'Closed', 'value' => 5000000, 'status' => 'won', 'closed_at' => now()]);
    app(CurrentOrganization::class)->forget();

    $deals = dashboardProps($this)['topDeals'];

    expect(collect($deals)->pluck('name')->all())->toBe(['Big', 'Small'])
        ->and($deals[0]['value'])->toBe(900000)
        ->and($deals[0]['stage'])->not->toBeNull();
});

it('wires the growth score with live value and snapshot history', function () {
    app(CurrentOrganization::class)->set($this->org);
    GrowthScore::create(['overall' => 40, 'breakdown' => [], 'recommendations' => [], 'computed_on' => now()->subDay()->startOfDay()]);
    app(CurrentOrganization::class)->forget();

    $score = dashboardProps($this)['growthScore'];

    expect($score['overall'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100)
        ->and($score['history'])->toHaveCount(1)
        ->and($score['history'][0]['value'])->toBe(40)
        // Recommendations are {area, score, action} objects — the page renders
        // the fields, never the object itself (caught live as React error #31).
        ->and($score['recommendations'])->not->toBeEmpty()
        ->and($score['recommendations'][0])->toHaveKeys(['area', 'score', 'action'])
        ->and($score['recommendations'][0]['action'])->toBeString();
});

it('shows an honest empty command center for a new tenant', function () {
    $props = dashboardProps($this);

    expect($props['kpis']['new_leads'])->toBe(['value' => 0, 'previous' => 0, 'delta_pct' => null])
        ->and($props['kpis']['qualified_pipeline'])->toBe(0)
        ->and(collect($props['leadTrend'])->sum('value'))->toBe(0)
        ->and(collect($props['mrrTrend'])->sum('value'))->toBe(0)
        ->and($props['channels'])->toBeEmpty()
        ->and($props['topDeals'])->toBeEmpty()
        ->and($props['attention']['alerts'])->toBeEmpty()
        ->and($props['attention']['waiting_chats'])->toBe(0)
        ->and($props['growthScore']['history'])->toBeEmpty();
});
