<?php

declare(strict_types=1);

/**
 * AI Sales Agent close-out (Phase 25 — AISA-002/005/007/012/013/014).
 *
 * Research grounded in the prospect's own public website (SSRF-guarded,
 * provenance-labeled, CRM-only stated honestly when unreadable); advisory
 * scores persisted as history and calibrated against real deal outcomes
 * (guarded); real call transcripts attached by hand and summarized. AISA-002's
 * public website chatbot is already covered end-to-end by ChatBookingAndAiTest
 * over the public /wc endpoints.
 */

use App\Ai\AiCompletion;
use App\Models\AiScore;
use App\Models\Call;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiSalesAgent;
use App\Services\Ai\ProspectSiteReader;
use App\Services\Ai\ScoreCalibration;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Agent Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function fakeCompletion(): AiCompletion
{
    return new AiCompletion(text: 'SCORE: 80'."\n".'REASON: fixture', promptTokens: 10, completionTokens: 10, model: 'fixture-1');
}

it('reads a prospect site through the SSRF guard and refuses private hosts', function () {
    Http::fake(['*' => Http::response('<html><head><title>Mfg Co — CMMC-ready plant IT</title><meta name="description" content="Precision manufacturing IT."></head><body><h1>Plant-floor IT</h1><h2>CMMC compliance</h2><p>We keep lines running.</p><script>evil()</script></body></html>')]);

    $reader = app(ProspectSiteReader::class);

    $page = $reader->read('93.184.216.34');
    expect($page['url'])->toBe('https://93.184.216.34')
        ->and($page['title'])->toBe('Mfg Co — CMMC-ready plant IT')
        ->and($page['description'])->toBe('Precision manufacturing IT.')
        ->and($page['headings'])->toBe(['Plant-floor IT', 'CMMC compliance'])
        ->and($page['excerpt'])->toContain('We keep lines running.')
        ->and($page['excerpt'])->not->toContain('evil'); // scripts stripped

    // Private space is refused before any request is made; blanks read as null.
    expect($reader->read('http://192.168.1.10'))->toBeNull()
        ->and($reader->read('http://127.0.0.1/admin'))->toBeNull()
        ->and($reader->read(null))->toBeNull()
        ->and($reader->read(''))->toBeNull();

    // The guard refused those without fetching: only the public URL was hit.
    Http::assertSentCount(1);
});

it('grounds research in the prospect site with provenance, and states CRM-only honestly without one', function () {
    Http::fake(['*' => Http::response('<html><head><title>Mfg Co — plant IT</title></head><body><h1>Uptime for manufacturers</h1></body></html>')]);

    $captured = [];
    $gateway = Mockery::mock(AiGateway::class);
    $gateway->shouldReceive('run')->andReturnUsing(function ($feature, $key, $vars) use (&$captured) {
        $captured[] = $vars;

        return fakeCompletion();
    });
    app()->instance(AiGateway::class, $gateway);

    $withSite = Company::create(['name' => 'Mfg Co', 'website' => 'https://93.184.216.34']);
    $without = Company::create(['name' => 'Mystery LLC']);
    $agent = app(AiSalesAgent::class);

    $agent->researchAccount($withSite);
    expect($captured[0]['profile'])->toContain('Observed on their public website (https://93.184.216.34')
        ->and($captured[0]['profile'])->toContain('Mfg Co — plant IT')
        ->and($captured[0]['profile'])->toContain('Uptime for manufacturers');

    $agent->researchAccount($without);
    expect($captured[1]['profile'])->toContain('No public website could be read; this summary uses only the CRM records');

    // Lead research rides the contact's company site the same way.
    $contact = Contact::create(['first_name' => 'Ada', 'email' => 'ada@mfg.example', 'company_id' => $withSite->id, 'lifecycle_stage' => 'mql']);
    $agent->researchLead($contact);
    expect($captured[2]['profile'])->toContain('Contact: Ada')
        ->and($captured[2]['profile'])->toContain('Observed on their public website');
});

it('persists advisory score history and never overwrites the deterministic lead score', function () {
    $contact = Contact::create(['first_name' => 'Bo', 'email' => 'bo@x.com', 'lifecycle_stage' => 'sql', 'lead_score' => 42]);
    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $deal = Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $pipeline->stages()->orderBy('sort_order')->firstOrFail()->id, 'name' => 'MSP switch', 'value' => 2400, 'status' => 'open']);

    $agent = app(AiSalesAgent::class);
    $leadScore = $agent->scoreLead($contact);
    $dealScore = $agent->scoreOpportunity($deal);

    expect($leadScore['score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100)
        ->and($contact->refresh()->lead_score)->toBe(42); // advisory, never written

    $rows = AiScore::orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->scoreable_type)->toBe('contact')->and($rows[0]->scoreable_id)->toBe($contact->id)
        ->and($rows[0]->score)->toBe($leadScore['score'])
        ->and($rows[1]->scoreable_type)->toBe('deal')->and($rows[1]->scoreable_id)->toBe($deal->id)
        ->and($rows[1]->score)->toBe($dealScore['score']);
});

it('calibrates advisory scores against outcomes, guarded until there is enough evidence', function () {
    $calibration = app(ScoreCalibration::class);
    expect($calibration->report()['insufficient_data'])->toBeTrue();

    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $stage = $pipeline->stages()->orderBy('sort_order')->firstOrFail();

    $seed = function (string $status, int $score, int $i) use ($pipeline, $stage) {
        $deal = Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'name' => "{$status} {$i}", 'value' => 100, 'status' => $status]);
        AiScore::create(['scoreable_type' => 'deal', 'scoreable_id' => $deal->id, 'score' => $score, 'reason' => 'seeded']);
    };

    // Two per bucket: still not enough — the guard holds.
    foreach ([80, 75] as $i => $s) {
        $seed('won', $s, $i);
    }
    foreach ([55, 50] as $i => $s) {
        $seed('lost', $s, $i);
    }
    expect($calibration->report()['insufficient_data'])->toBeTrue();

    $seed('won', 85, 2);
    $seed('lost', 45, 2);

    $report = $calibration->report();
    expect($report['insufficient_data'])->toBeFalse()
        ->and($report['won'])->toBe(['count' => 3, 'avg_score' => 80])   // 80,75,85
        ->and($report['lost'])->toBe(['count' => 3, 'avg_score' => 50])  // 55,50,45
        ->and($report['separation'])->toBe(30);

    // The dashboard carries the tenant's own measured number.
    $props = $this->actingAs($this->owner)->get(route('ai.dashboard'))->assertOk()
        ->viewData('page')['props'];
    expect($props['calibration']['separation'])->toBe(30);
});

it('attaches a pasted transcript and summarizes from what was actually said', function () {
    $call = Call::create(['direction' => 'inbound', 'duration_seconds' => 480, 'status' => 'completed', 'score' => 40, 'is_qualified' => true, 'occurred_at' => now()]);
    app(CurrentOrganization::class)->forget();

    $transcript = "Rep: Thanks for the time.\nProspect: Our current MSP takes days to respond and our CMMC audit is in March.\nRep: We guarantee 15-minute response and run CMMC readiness.\nProspect: Send the proposal by Friday.";

    $this->actingAs($this->owner)
        ->patch(route('analytics.calls.transcript', $call->id), ['transcript' => $transcript])
        ->assertRedirect()->assertSessionHas('status');
    expect($call->refresh()->transcript)->toBe($transcript);

    $this->actingAs($this->owner)->post(route('analytics.calls.summarize', $call->id))
        ->assertRedirect()->assertSessionHas('status');
    expect((string) $call->refresh()->summary)->not->toBe('');

    // Oversized paste is refused, not truncated silently.
    $this->actingAs($this->owner)
        ->patch(route('analytics.calls.transcript', $call->id), ['transcript' => str_repeat('a', 20001)])
        ->assertSessionHasErrors('transcript');
});
