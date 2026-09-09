<?php

declare(strict_types=1);

/**
 * Ops hardening close-out (Phase 55 — JOBS-002/003, OBS-004, DEVX-005).
 *
 * Retry/dead-letter behavior proven through the real database queue, campaign
 * job uniqueness and idempotency pinned, platform-admin health alerting on
 * state transitions, and the release script's guarantees locked by contract.
 */

use App\Authorization\Role;
use App\Jobs\SendCampaignJob;
use App\Models\Campaign;
use App\Models\MarketingList;
use App\Models\User;
use App\Notifications\SystemHealthAlertNotification;
use App\Services\Marketing\CampaignService;
use App\Services\Ops\HealthAlerter;
use App\Services\Ops\HealthCheckService;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/** A deliberately failing job so the dead-letter path runs for real. */
class AlwaysFailingProbeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        throw new RuntimeException('probe: this job always fails');
    }
}

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('OpsHard Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('declares retries on every queued job and dead-letters real failures into failed_jobs (JOBS-002)', function () {
    // Every job class ships a retry budget — a new job without one fails this sweep.
    foreach (glob(app_path('Jobs/*.php')) as $file) {
        $class = 'App\\Jobs\\'.basename($file, '.php');
        $instance = new ReflectionClass($class);
        expect($instance->hasProperty('tries'))->toBeTrue("{$class} must declare \$tries")
            ->and($instance->getProperty('tries')->getDefaultValue())->toBeGreaterThanOrEqual(1, "{$class} retry budget");
    }

    // The REAL dead-letter path: a failing job through the actual database queue.
    config(['queue.default' => 'database']);
    dispatch(new AlwaysFailingProbeJob);
    expect(DB::table('jobs')->count())->toBe(1);

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1]);

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and((string) DB::table('failed_jobs')->value('exception'))->toContain('probe: this job always fails');
});

it('collapses duplicate campaign sends and no-ops on an already-sent campaign (JOBS-003)', function () {
    $list = MarketingList::create(['name' => 'L', 'type' => 'static']);
    $campaign = Campaign::create(['name' => 'Unique test', 'channel' => 'email', 'subject' => 'S', 'body_html' => '<p>x</p>', 'marketing_list_id' => $list->id, 'status' => 'draft']);

    // ShouldBeUnique: two dispatches, one queued job.
    Queue::fake();
    dispatch(new SendCampaignJob($campaign->id, $this->org->id));
    dispatch(new SendCampaignJob($campaign->id, $this->org->id));
    Queue::assertPushed(SendCampaignJob::class, 1);

    // Idempotent re-run: an already-sent campaign is left alone, not re-sent.
    $campaign->forceFill(['status' => 'sent', 'sent_at' => now(), 'stat_sent' => 7])->save();
    (new SendCampaignJob($campaign->id, $this->org->id))->handle(app(CampaignService::class), app(CurrentOrganization::class));
    expect($campaign->refresh()->stat_sent)->toBe(7)
        ->and($campaign->status)->toBe('sent');
});

it('alerts platform admins on failure TRANSITIONS only, with a single all-clear (OBS-004)', function () {
    Notification::fake();
    $admin = User::factory()->create(['platform_role' => Role::PlatformSuperAdmin->value]);
    $alerter = app(HealthAlerter::class);

    // Healthy steady state: silence.
    expect($alerter->evaluate(['database' => true, 'queue' => true]))->toBe('healthy');
    Notification::assertNothingSent();

    // A new failure alerts once, naming the check; the same state repeats silently.
    expect($alerter->evaluate(['database' => true, 'queue' => false]))->toBe('alerted');
    Notification::assertSentTo($admin, SystemHealthAlertNotification::class, fn ($n) => $n->failing === ['queue']);
    expect($alerter->evaluate(['database' => true, 'queue' => false]))->toBe('unchanged');
    Notification::assertSentTimes(SystemHealthAlertNotification::class, 1);

    // A DIFFERENT failing set is a new state: alert again.
    expect($alerter->evaluate(['database' => false, 'queue' => false]))->toBe('alerted');
    Notification::assertSentTimes(SystemHealthAlertNotification::class, 2);

    // Recovery sends exactly one all-clear, then silence resumes.
    expect($alerter->evaluate(['database' => true, 'queue' => true]))->toBe('recovered');
    Notification::assertSentTimes(SystemHealthAlertNotification::class, 3);
    expect($alerter->evaluate(['database' => true, 'queue' => true]))->toBe('healthy');
    Notification::assertSentTimes(SystemHealthAlertNotification::class, 3);

    // The tenant owner is not platform staff and is never alerted.
    Notification::assertNotSentTo($this->owner, SystemHealthAlertNotification::class);

    // /health and the alerter share one definition of healthy.
    $checks = app(HealthCheckService::class)->checks();
    expect($checks)->toHaveKeys(['database', 'cache', 'queue', 'storage'])
        ->and(in_array(false, $checks, true))->toBeFalse();
    $this->get('/health')->assertOk()->assertJsonPath('status', 'ok');
});

it('locks the release script contract: snapshot, health gate, auto-rollback, history (DEVX-005)', function () {
    // Proven live: ~20 production releases and two REAL automatic rollbacks
    // (8 Sep MySQL incident) held /health at 200. This test pins the script's
    // guarantees so a future edit cannot silently drop one.
    $script = file_get_contents(base_path('scripts/release.sh'));

    expect($script)->toContain('set -Eeuo pipefail')           // fail-fast, no silent errors
        ->and($script)->toContain('2/5 snapshot')              // pre-extract snapshot stage
        ->and($script)->toContain('HEALTH_URL')                // deploy gated on /health
        ->and($script)->toContain('HEALTH CHECK FAILED — rolling back automatically')
        ->and($script)->toContain('--rollback')                // manual rollback mode
        ->and($script)->toContain('--history')                 // release history mode
        ->and($script)->toContain('sha256sum')                 // versioned, checksummed releases
        ->and($script)->toContain('history.log');              // every action recorded

    expect(file_exists(base_path('docs/engineering/deploy-runbook.md')))->toBeTrue();
});
