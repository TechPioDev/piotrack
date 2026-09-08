<?php

declare(strict_types=1);

/**
 * ABM + Lead Scoring + Retargeting + Multi-Location close-out
 * (Phase 53 — ABM-004/017, LSCR-014/015, RETG-006/007, MLOC-002).
 *
 * Company enrichment and the video-outreach play, the empirical predictive
 * model behind its floor and the AI advisory surface, video/YouTube
 * retargeting on the P42 draft machinery, and the GBP push write-seam.
 */

use App\Ai\AiCompletion;
use App\Models\Activity;
use App\Models\AdCampaign;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\RetargetingAudience;
use App\Models\SeoLocation;
use App\Models\TargetAccount;
use App\Services\Ai\AiGateway;
use App\Services\Sales\AbmPlayRunner;
use App\Services\Sales\PredictiveScoringService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('AbmTail Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function tailDeal(array $attributes): Deal
{
    $pipeline = Pipeline::where('is_default', true)->with('stages')->firstOrFail();
    $stage = ($attributes['status'] ?? 'open') === 'won'
        ? $pipeline->stages->firstWhere('is_won', true)
        : $pipeline->stages->firstWhere('is_lost', true);

    return Deal::create($attributes + ['pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'name' => 'D'.uniqid(), 'closed_at' => now()]);
}

it('enriches the target account company through the seam, set-once and audited (ABM-004)', function () {
    $company = Company::create(['name' => 'Precision Mfg', 'domain' => 'precision-mfg.example']);
    $account = TargetAccount::create(['company_id' => $company->id, 'tier' => 'a', 'status' => 'active']);

    $this->actingAs($this->owner)->post(route('sales.accounts.enrich', $account))->assertRedirect();
    expect($company->refresh()->industry)->not->toBeNull()
        ->and($company->size)->not->toBeNull()
        ->and(AuditLog::where('action', 'abm.account.enriched')->first()?->context['provider'] ?? null)->toBe('fixture');

    // Set-once: operator data survives a second enrichment.
    $company->update(['industry' => 'Aerospace (operator-set)']);
    $this->actingAs($this->owner)->post(route('sales.accounts.enrich', $account))->assertRedirect();
    expect($company->refresh()->industry)->toBe('Aerospace (operator-set)');

    // No domain to look up: an explicit error, not an invented company.
    $bare = TargetAccount::create(['company_id' => Company::create(['name' => 'No Domain LLC'])->id, 'tier' => 'b', 'status' => 'active']);
    $this->actingAs($this->owner)->post(route('sales.accounts.enrich', $bare))->assertSessionHasErrors('company');
});

it('runs the video-outreach play: one task per decision-maker pointing at the tested send flow (ABM-017)', function () {
    $company = Company::create(['name' => 'Committee Co']);
    $account = TargetAccount::create(['company_id' => $company->id, 'tier' => 'a', 'status' => 'active']);
    Contact::create(['first_name' => 'Dee', 'email' => 'dee@cc.test', 'company_id' => $company->id, 'buying_role' => 'decision_maker']);
    Contact::create(['first_name' => 'Inf', 'email' => 'inf@cc.test', 'company_id' => $company->id, 'buying_role' => 'influencer']);

    expect(AbmPlayRunner::PLAYS)->toHaveKey('video_outreach');

    $result = app(AbmPlayRunner::class)->run($account, 'video_outreach', $this->owner->id);
    expect($result['steps'])->toHaveCount(1); // decision-makers only

    $task = Activity::where('type', 'task')->where('title', 'like', 'Video outreach:%')->firstOrFail();
    expect($task->body)->toContain('Video message')
        ->and($task->body)->toContain('any host');

    // No decision-makers: the play says so instead of inventing targets.
    $empty = TargetAccount::create(['company_id' => Company::create(['name' => 'Ghost Co'])->id, 'tier' => 'c', 'status' => 'active']);
    expect(app(AbmPlayRunner::class)->run($empty, 'video_outreach')['steps'][0]['step'])->toBe('no_decision_makers');
});

it('refuses predictive scoring below the floor and computes honest empirics above it (LSCR-014)', function () {
    $contact = Contact::create(['first_name' => 'P', 'email' => 'p@x.test', 'lead_source' => 'referral', 'lead_score' => 70]);

    // Thin history: the model refuses with counts.
    $service = app(PredictiveScoringService::class);
    expect($service->status()['active'])->toBeFalse()
        ->and($service->predict($contact)['insufficient_data'])->toBeTrue();

    // 30 closed deals: 10 referral (8 won), 20 other (4 won).
    foreach (range(1, 10) as $i) {
        $c = Contact::create(['first_name' => "R{$i}", 'email' => "r{$i}@x.test", 'lead_source' => 'referral', 'lead_score' => 70]);
        tailDeal(['status' => $i <= 8 ? 'won' : 'lost', 'contact_id' => $c->id]);
    }
    foreach (range(1, 20) as $i) {
        $c = Contact::create(['first_name' => "O{$i}", 'email' => "o{$i}@x.test", 'lead_source' => 'cold', 'lead_score' => 10]);
        tailDeal(['status' => $i <= 4 ? 'won' : 'lost', 'contact_id' => $c->id]);
    }

    $prediction = $service->predict($contact);
    $factors = collect($prediction['factors'])->keyBy(fn (array $f) => explode(' ', $f['factor'])[0]);

    expect($prediction['insufficient_data'])->toBeFalse()
        // referral bucket: 8/10 = 80%; hot band (score 70): 8 won of 10 hot = 80%.
        ->and($factors['lead']['rate'])->toBe(80.0)
        ->and($factors['lead']['sample'])->toBe(10)
        ->and($prediction['probability'])->toBe(80.0);

    $props = $this->actingAs($this->owner)->get(route('sales.scoring.index'))->assertOk()->viewData('page')['props'];
    expect($props['predictive']['active'])->toBeTrue()
        ->and(collect($props['contacts'])->firstWhere('name', 'P')['win_probability'])->toBe(80.0);
});

it('surfaces the tested AI advisory score without touching the deterministic one (LSCR-015)', function () {
    $gateway = Mockery::mock(AiGateway::class);
    $gateway->shouldReceive('run')->andReturn(new AiCompletion(text: "SCORE: 85\nREASON: strong title and intent", promptTokens: 5, completionTokens: 5, model: 'fixture-1'));
    app()->instance(AiGateway::class, $gateway);

    $contact = Contact::create(['first_name' => 'Ai', 'email' => 'ai@x.test', 'lead_score' => 40]);

    $this->actingAs($this->owner)->post(route('sales.scoring.ai', $contact))
        ->assertRedirect()->assertSessionHas('status');
    expect(session('status'))->toContain('85/100')
        ->and(session('status'))->toContain('Advisory only')
        ->and($contact->refresh()->lead_score)->toBe(40); // untouched
});

it('builds video/YouTube retargeting on the P42 draft machinery, idempotently (RETG-006/007)', function () {
    $audience = RetargetingAudience::create(['name' => 'Warm MSP buyers', 'source' => 'all_contacts']);
    $piece = ContentPiece::create(['title' => 'Ransomware webinar', 'slug' => 'rw1', 'content_type' => 'webinar', 'status' => 'published', 'url' => 'https://example.com/webinar']);

    $this->actingAs($this->owner)->post(route('ads.retargeting.video', $audience), ['content_piece_id' => $piece->id])
        ->assertRedirect();

    $campaign = AdCampaign::where('platform', 'youtube')->firstOrFail();
    expect($campaign->status)->toBe('draft')
        ->and($campaign->targeting['audience_id'])->toBe($audience->id)
        ->and($campaign->targeting['content_piece_id'])->toBe($piece->id);

    // Idempotent: the same piece reuses the draft rather than duplicating it.
    $this->actingAs($this->owner)->post(route('ads.retargeting.video', $audience), ['content_piece_id' => $piece->id])->assertRedirect();
    expect(AdCampaign::where('platform', 'youtube')->count())->toBe(1);
});

it('pushes a branch profile through the GBP write-seam, place-id-gated and labeled (MLOC-002)', function () {
    $branch = SeoLocation::create(['name' => 'Philly HQ', 'street' => '1 Market St', 'city' => 'Philadelphia', 'region' => 'PA', 'postal_code' => '19106', 'country' => 'US', 'phone' => '215-555-0100']);

    // No place id: no push, an explicit error.
    $this->actingAs($this->owner)->post(route('seo.local.gbp.push', $branch))->assertSessionHasErrors('gbp');

    $branch->update(['gbp_place_id' => 'ChIJtestplace123']);
    $this->actingAs($this->owner)->post(route('seo.local.gbp.push', $branch))
        ->assertRedirect()->assertSessionHas('status');
    expect(session('status'))->toContain('SIMULATED');

    $entry = AuditLog::where('action', 'seo.gbp.pushed')->firstOrFail();
    expect($entry->context['provider'])->toBe('fixture')
        ->and($entry->context['fields'])->toContain('city')
        ->and($entry->context['fields'])->toContain('phone');
});
