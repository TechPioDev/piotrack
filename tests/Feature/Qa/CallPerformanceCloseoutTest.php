<?php

declare(strict_types=1);

/**
 * Call Tracking + Lead Guarantee close-out (Phase 41 — CALL-003/004/005,
 * PERF-004/010/011).
 *
 * Recording attachment + provider-seam transcription + the P25 AI summary
 * chain; automatic promised-vs-delivered reconciliation; the SLA breach
 * notification through the alert sweep; and the stored ROI review artefact.
 */

use App\Ai\AiCompletion;
use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\Call;
use App\Models\Deal;
use App\Models\Deliverable;
use App\Models\PerformanceAgreement;
use App\Models\PerformanceReview;
use App\Models\Pipeline;
use App\Models\Project;
use App\Services\Ai\AiGateway;
use App\Services\AlertSweep;
use App\Services\Strategy\PerformanceService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('CallPerf Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('attaches recordings, transcribes through the provider seam, and summarizes what was said', function () {
    $call = Call::create(['from_number' => '+15550001', 'to_number' => '+15550002', 'direction' => 'inbound', 'duration_seconds' => 300, 'status' => 'completed', 'occurred_at' => now()]);

    // Transcription needs audio first.
    $this->actingAs($this->owner)->from(route('analytics.calls.index'))
        ->post(route('analytics.calls.transcribe', $call))
        ->assertRedirect()->assertSessionHasErrors('recording_url');

    // CALL-003: https recordings only.
    $this->actingAs($this->owner)->from(route('analytics.calls.index'))
        ->patch(route('analytics.calls.recording', $call), ['recording_url' => 'http://insecure.example/rec.mp3'])
        ->assertRedirect()->assertSessionHasErrors('recording_url');
    $this->actingAs($this->owner)
        ->patch(route('analytics.calls.recording', $call), ['recording_url' => 'https://recordings.example/rec-77.mp3'])
        ->assertRedirect();
    expect($call->refresh()->recording_url)->toBe('https://recordings.example/rec-77.mp3');

    // CALL-004: the fixture driver fills the transcript and states its provenance.
    $this->actingAs($this->owner)->post(route('analytics.calls.transcribe', $call))->assertRedirect();
    $call->refresh();
    expect($call->transcript)->toContain('[Fixture transcript')
        ->and($call->transcript)->toContain('rec-77.mp3')
        ->and($call->transcript)->toContain('switching IT providers');

    // CALL-005: the AI summary runs on the transcript through the gateway.
    $captured = [];
    $gateway = Mockery::mock(AiGateway::class);
    $gateway->shouldReceive('run')->andReturnUsing(function ($feature, $key, $vars) use (&$captured) {
        $captured[] = ['feature' => $feature, 'vars' => $vars];

        return new AiCompletion(text: 'Prospect is evaluating a provider switch this quarter.', promptTokens: 5, completionTokens: 5, model: 'fixture-1');
    });
    app()->instance(AiGateway::class, $gateway);

    $this->actingAs($this->owner)->post(route('analytics.calls.summarize', $call))->assertRedirect();
    expect($call->refresh()->summary)->toBe('Prospect is evaluating a provider switch this quarter.')
        ->and($captured[0]['vars']['transcript'])->toContain('switching IT providers');
});

it('reconciles promised deliverables against real project deliverables automatically', function () {
    $agreement = PerformanceAgreement::create([
        'name' => 'Growth guarantee', 'model' => 'guarantee', 'lead_target' => 10, 'sql_target' => 0, 'meeting_target' => 0,
        'deliverables' => ['SEO audit', 'Landing page'], 'sla_days' => 90, 'status' => 'active',
        'period_start' => now()->subDays(30), 'period_end' => now()->addDays(60),
    ]);
    $project = Project::create(['name' => 'Onboarding', 'status' => 'active']);

    // Approved counts as delivered even when its status label lags.
    Deliverable::create(['project_id' => $project->id, 'title' => 'SEO Audit', 'status' => 'submitted', 'approval_status' => 'approved']);

    $recon = app(PerformanceService::class)->reconcileDeliverables($agreement);
    expect($recon['promised'])->toBe(2)
        ->and($recon['delivered'])->toBe(1)
        ->and($recon['all_delivered'])->toBeFalse()
        ->and($recon['items'][0]['delivered'])->toBeTrue()
        ->and($recon['items'][0]['approved'])->toBeTrue()
        ->and($recon['items'][1]['matched'])->toBeNull();

    // A delivered match by fuzzy title closes the gap.
    Deliverable::create(['project_id' => $project->id, 'title' => 'Landing page v2 — co-managed IT', 'status' => 'delivered', 'approval_status' => 'not_required']);
    $recon = app(PerformanceService::class)->reconcileDeliverables($agreement);
    expect($recon['delivered'])->toBe(2)->and($recon['all_delivered'])->toBeTrue();

    // The performance page carries it.
    $row = collect($this->actingAs($this->owner)->get(route('strategy.performance'))
        ->assertOk()->viewData('page')['props']['agreements'])->firstWhere('name', 'Growth guarantee');
    expect($row['reconciliation']['delivered'])->toBe(2);
});

it('notifies owners of an SLA breach through the alert sweep, deduped per day', function () {
    PerformanceAgreement::create([
        'name' => 'Missed guarantee', 'model' => 'guarantee', 'lead_target' => 50, 'sql_target' => 0, 'meeting_target' => 0,
        'sla_days' => 30, 'status' => 'active',
        'period_start' => now()->subDays(40), 'period_end' => now()->subDays(2),
    ]);

    $result = app(AlertSweep::class)->run($this->org);
    expect($result['sla'])->toBe(1);

    $notification = $this->owner->notifications()->latest()->first();
    expect($notification->data['title'])->toBe('Performance SLA breached')
        ->and($notification->data['body'])->toContain('Missed guarantee')
        ->and($notification->data['body'])->toContain('leads 0/50');

    // Same day: deduped, not re-sent.
    expect(app(AlertSweep::class)->run($this->org)['sla'])->toBe(0);
});

it('stores the formal ROI review with period-bounded revenue and spend', function () {
    $agreement = PerformanceAgreement::create([
        'name' => 'Q3 performance', 'model' => 'performance_pricing', 'lead_target' => 5, 'sql_target' => 0, 'meeting_target' => 0,
        'sla_days' => 90, 'status' => 'active',
        'period_start' => now()->subDays(90), 'period_end' => now(),
    ]);

    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $won = $pipeline->stages()->where('is_won', true)->firstOrFail();
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $won->id, 'name' => 'In window', 'value' => 600000, 'status' => 'won', 'closed_at' => now()->subDays(10)]);
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $won->id, 'name' => 'Out of window', 'value' => 990000, 'status' => 'won', 'closed_at' => now()->subDays(120)]);

    $campaign = AdCampaign::create(['platform' => 'google_search', 'name' => 'Perf ads', 'objective' => 'leads', 'daily_budget' => 1000]);
    AdMetric::create(['ad_campaign_id' => $campaign->id, 'date' => now()->subDays(9)->toDateString(), 'impressions' => 100, 'clicks' => 10, 'spend' => 120000, 'conversions' => 2, 'revenue' => 0]);
    AdMetric::create(['ad_campaign_id' => $campaign->id, 'date' => now()->subDays(120)->toDateString(), 'impressions' => 100, 'clicks' => 10, 'spend' => 500000, 'conversions' => 0, 'revenue' => 0]);

    $this->actingAs($this->owner)->post(route('strategy.performance.roi-review', $agreement))->assertRedirect();

    $review = PerformanceReview::firstOrFail();
    expect($review->data['won_revenue'])->toBe(600000)   // out-of-window deal excluded
        ->and($review->data['ad_spend'])->toBe(120000)   // out-of-window spend excluded
        ->and($review->data['roi'])->toEqual(5) // json round-trip may int-ify 5.0
        ->and($review->data['attainment'])->toHaveKey('targets')
        ->and($review->period_end->toDateString())->toBe(now()->toDateString());

    // The artefact is listed on the performance page.
    $reviews = $this->actingAs($this->owner)->get(route('strategy.performance'))
        ->assertOk()->viewData('page')['props']['reviews'];
    expect($reviews[0]['roi'])->toEqual(5)->and($reviews[0]['agreement'])->toBe('Q3 performance');
});
