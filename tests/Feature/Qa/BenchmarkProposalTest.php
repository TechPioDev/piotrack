<?php

declare(strict_types=1);

/**
 * Proprietary Data Layer close-out (Phase 20 — BENCH-008/009).
 *
 * The proposal-stage convention: an is_proposal stage stamps
 * deals.proposal_sent_at on first entry (never rewritten), which is what makes
 * meeting→proposal and proposal→win rates honest across tenants — through the
 * same k-anonymity machinery as every other benchmark.
 */

use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Deal;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Services\Analytics\BenchmarkService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Str;

afterEach(fn () => app(CurrentOrganization::class)->forget());

/** An org with $meetings bookings, $proposals proposal-stamped deals, $wins of them won. */
function seedBenchOrg(string $name, int $meetings, int $proposals, int $wins): void
{
    [$org, $owner] = makeOrganization($name);
    app(CurrentOrganization::class)->set($org);

    $page = BookingPage::create(['name' => 'Call', 'slug' => 'call-'.Str::lower(Str::random(6)), 'meeting_type' => 'call', 'duration_minutes' => 30, 'assignment' => 'fixed', 'is_active' => true]);
    for ($i = 0; $i < $meetings; $i++) {
        Booking::create(['booking_page_id' => $page->id, 'name' => "M{$i}", 'email' => "m{$i}@{$org->id}.test", 'scheduled_at' => now()->addDay(), 'status' => 'booked']);
    }

    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $won = $pipeline->stages()->where('is_won', true)->firstOrFail();
    $proposalStage = $pipeline->stages()->where('is_proposal', true)->firstOrFail();

    for ($i = 0; $i < $proposals; $i++) {
        Deal::create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $i < $wins ? $won->id : $proposalStage->id,
            'name' => "D{$i}", 'value' => 1000,
            'status' => $i < $wins ? 'won' : 'open',
            'closed_at' => $i < $wins ? now() : null,
            'proposal_sent_at' => now()->subDays(5),
        ]);
    }

    app(CurrentOrganization::class)->forget();
}

it('stamps proposal_sent_at on first entry into the proposal stage, never rewriting it', function () {
    [$org, $owner] = makeOrganization('Stamp Org');
    subscribeOrganization($org, 'enterprise');
    app(CurrentOrganization::class)->set($org);

    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $new = $pipeline->stages()->orderBy('sort_order')->firstOrFail();
    $proposal = $pipeline->stages()->where('is_proposal', true)->firstOrFail();
    $deal = Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $new->id, 'name' => 'Convention', 'value' => 5000, 'status' => 'open']);
    app(CurrentOrganization::class)->forget();

    expect($proposal->name)->toBe('Proposal'); // the default pipeline carries the convention

    $this->actingAs($owner)->patch(route('crm.deals.stage', $deal->id), ['stage_id' => $proposal->id])->assertRedirect();
    $stamped = $deal->fresh()->proposal_sent_at;
    expect($stamped)->not->toBeNull();

    // Back out and in again: the original date survives.
    $this->travel(2)->days();
    $this->actingAs($owner)->patch(route('crm.deals.stage', $deal->id), ['stage_id' => $new->id])->assertRedirect();
    $this->actingAs($owner)->patch(route('crm.deals.stage', $deal->id), ['stage_id' => $proposal->id])->assertRedirect();

    expect($deal->fresh()->proposal_sent_at->equalTo($stamped))->toBeTrue();
});

it('emits both proposal rates with correct math across a qualifying cohort', function () {
    config(['analytics.benchmark_min_cohort' => 3]);

    seedBenchOrg('Alpha MSP', 10, 5, 2);   // meeting→proposal 50%, proposal→win 40%
    seedBenchOrg('Beta MSP', 4, 3, 3);     // 75%, 100%
    seedBenchOrg('Gamma MSP', 5, 1, 0);    // 20%, 0%

    // Percentiles are computed against the caller's own org too — stand as Alpha.
    $alpha = Organization::where('name', 'Alpha MSP')->firstOrFail();
    app(CurrentOrganization::class)->set($alpha);

    $m2p = app(BenchmarkService::class)->benchmark('meeting_to_proposal');
    expect($m2p)->not->toBeNull()
        ->and($m2p['cohort'])->toBe(3)
        ->and($m2p['peer_median'])->toBe(50.0)
        ->and($m2p['your_value'])->toBe(50.0);

    $p2w = app(BenchmarkService::class)->benchmark('proposal_to_win');
    expect($p2w['cohort'])->toBe(3)
        ->and($p2w['peer_median'])->toBe(40.0)
        ->and($p2w['your_value'])->toBe(40.0)
        ->and($p2w['top_quartile'])->toBe(70.0); // midpoint interpolation between 40 and 100
});

it('suppresses both rates below the k-anonymity floor like every other metric', function () {
    config(['analytics.benchmark_min_cohort' => 3]);

    seedBenchOrg('Lonely MSP', 5, 3, 1);
    seedBenchOrg('Second MSP', 5, 2, 1);

    $org = Organization::where('name', 'Lonely MSP')->firstOrFail();
    app(CurrentOrganization::class)->set($org);

    expect(app(BenchmarkService::class)->benchmark('meeting_to_proposal'))->toBeNull()
        ->and(app(BenchmarkService::class)->benchmark('proposal_to_win'))->toBeNull();
});
