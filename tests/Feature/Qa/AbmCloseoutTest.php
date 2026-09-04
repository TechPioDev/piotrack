<?php

declare(strict_types=1);

/**
 * ABM close-out (Phase 26 — ABM-007/011/012/014/016/019).
 *
 * Org charts from rep-captured reporting lines, content targeted at one
 * account, the LinkedIn company-list export, account-based retargeting wired
 * through the tested tier-list + audience machinery, and the orchestration
 * play-runner whose steps are honest and never reach a third party.
 */

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\OutboundMessage;
use App\Models\RetargetingAudience;
use App\Models\TargetAccount;
use App\Services\Advertising\RetargetingService;
use App\Services\Sales\AbmPlayRunner;
use App\Services\Sales\AccountService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('ABM Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);

    $this->company = Company::create(['name' => 'Mfg Co', 'website' => 'https://mfg.example']);
    $this->account = TargetAccount::create(['company_id' => $this->company->id, 'tier' => 1, 'status' => 'targeted']);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('builds the reporting forest cycle-safe and guards the manager endpoint', function () {
    $ceo = Contact::create(['first_name' => 'Ada', 'email' => 'ada@mfg.example', 'company_id' => $this->company->id, 'title' => 'CEO']);
    $it = Contact::create(['first_name' => 'Bo', 'email' => 'bo@mfg.example', 'company_id' => $this->company->id, 'title' => 'IT Manager', 'reports_to_contact_id' => $ceo->id]);
    Contact::create(['first_name' => 'Cy', 'email' => 'cy@mfg.example', 'company_id' => $this->company->id, 'reports_to_contact_id' => $it->id]);

    $chart = app(AccountService::class)->orgChart($this->account);
    expect($chart)->toHaveCount(1)
        ->and($chart[0]['name'])->toBe('Ada')
        ->and($chart[0]['is_decision_maker'])->toBeTrue()
        ->and($chart[0]['reports'][0]['name'])->toBe('Bo')
        ->and($chart[0]['reports'][0]['reports'][0]['name'])->toBe('Cy');

    // A cycle degrades to roots instead of hanging.
    $d = Contact::create(['first_name' => 'Dee', 'email' => 'dee@mfg.example', 'company_id' => $this->company->id]);
    $e = Contact::create(['first_name' => 'Eli', 'email' => 'eli@mfg.example', 'company_id' => $this->company->id, 'reports_to_contact_id' => $d->id]);
    $d->update(['reports_to_contact_id' => $e->id]);
    $names = collect(app(AccountService::class)->orgChart($this->account))->pluck('name');
    expect($names)->toContain('Ada')->toContain('Dee');

    // Endpoint: valid set works; self and cross-company are refused.
    $stranger = Company::create(['name' => 'Other Co']);
    $foreign = Contact::create(['first_name' => 'Fay', 'email' => 'fay@other.example', 'company_id' => $stranger->id]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->patch(route('sales.accounts.manager', $it->id), ['reports_to_contact_id' => $ceo->id])
        ->assertRedirect()->assertSessionHas('status');
    $this->actingAs($this->owner)->patch(route('sales.accounts.manager', $it->id), ['reports_to_contact_id' => $it->id])
        ->assertSessionHasErrors('reports_to_contact_id');
    $this->actingAs($this->owner)->patch(route('sales.accounts.manager', $it->id), ['reports_to_contact_id' => $foreign->id])
        ->assertSessionHasErrors('reports_to_contact_id');

    app(CurrentOrganization::class)->set($this->org);
});

it('targets content at one account and lists it on the report', function () {
    $piece = ContentPiece::create(['title' => 'CMMC guide for Mfg Co', 'slug' => 'cmmc-mfg', 'content_type' => 'guide', 'status' => 'published']);
    ContentPiece::create(['title' => 'Generic article', 'slug' => 'generic-a', 'content_type' => 'article', 'status' => 'published']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->post(route('sales.accounts.content.attach', $this->account->id), ['content_piece_id' => $piece->id])
        ->assertRedirect()->assertSessionHas('status');

    $props = $this->actingAs($this->owner)->get(route('sales.accounts.report', $this->account->id))
        ->assertOk()->viewData('page')['props'];

    expect(collect($props['content'])->pluck('title')->all())->toBe(['CMMC guide for Mfg Co'])
        ->and(collect($props['available_content'])->pluck('title'))->toContain('Generic article')
        ->and(collect($props['available_content'])->pluck('title'))->not->toContain('CMMC guide for Mfg Co');

    app(CurrentOrganization::class)->set($this->org);
    expect($piece->refresh()->company_id)->toBe($this->company->id);
});

it('exports the LinkedIn company-targeting CSV for active accounts', function () {
    $archived = Company::create(['name' => 'Gone LLC', 'domain' => 'gone.example']);
    TargetAccount::create(['company_id' => $archived->id, 'tier' => 1, 'status' => 'archived']);
    $t2 = Company::create(['name' => 'Second "Best" Co', 'domain' => 'second.example']);
    TargetAccount::create(['company_id' => $t2->id, 'tier' => 2, 'status' => 'targeted']);
    app(CurrentOrganization::class)->forget();

    $csv = $this->actingAs($this->owner)->get(route('sales.accounts.linkedin-export'))
        ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();

    expect($csv)->toStartWith("companyname,companywebsite\n")
        ->and($csv)->toContain('"Mfg Co","https://mfg.example"')
        ->and($csv)->toContain('"Second ""Best"" Co","second.example"') // quotes escaped, domain fallback
        ->and($csv)->not->toContain('Gone LLC'); // archived never exported

    $tierOnly = $this->actingAs($this->owner)->get(route('sales.accounts.linkedin-export').'?tier=1')->assertOk()->getContent();
    expect($tierOnly)->toContain('Mfg Co')->and($tierOnly)->not->toContain('Second');

    app(CurrentOrganization::class)->set($this->org);
});

it('runs the account-retargeting play: tier committee to rebuilt audience, customers excluded, idempotent', function () {
    Contact::create(['first_name' => 'Ada', 'email' => 'ada@mfg.example', 'company_id' => $this->company->id, 'lifecycle_stage' => 'mql']);
    Contact::create(['first_name' => 'Bo', 'email' => 'bo@mfg.example', 'company_id' => $this->company->id, 'lifecycle_stage' => 'customer']);

    $result = app(AbmPlayRunner::class)->run($this->account, 'account_retargeting');
    expect(collect($result['steps'])->pluck('step')->all())->toBe(['list_synced', 'audience_ready']);

    $audience = RetargetingAudience::where('name', 'ABM Tier 1 committee')->firstOrFail();
    expect($audience->source)->toBe('list')
        ->and($audience->member_count)->toBe(1) // the customer is excluded
        ->and($audience->exclude_converted)->toBeTrue();

    // Re-running refreshes the same audience instead of stacking duplicates,
    // and the existing platform export machinery works on it unchanged.
    app(AbmPlayRunner::class)->run($this->account, 'account_retargeting');
    expect(RetargetingAudience::where('name', 'ABM Tier 1 committee')->count())->toBe(1);

    $csv = app(RetargetingService::class)->exportCsv($audience->refresh(), 'linkedin');
    expect($csv)->toStartWith("email\n")
        ->and($csv)->toContain(hash('sha256', 'ada@mfg.example'));
});

it('runs executive outreach as queued tasks with drafts, honestly empty without decision makers, sending nothing', function () {
    // No decision-makers yet: the play says so and queues nothing.
    Contact::create(['first_name' => 'Uma', 'email' => 'uma@mfg.example', 'company_id' => $this->company->id, 'buying_role' => 'user']);
    $empty = app(AbmPlayRunner::class)->run($this->account, 'executive_outreach', $this->owner->id);
    expect($empty['steps'][0]['step'])->toBe('no_decision_makers')
        ->and(Activity::where('type', 'task')->count())->toBe(0);

    Contact::create(['first_name' => 'Ada', 'email' => 'ada@mfg.example', 'company_id' => $this->company->id, 'buying_role' => 'decision_maker']);
    Contact::create(['first_name' => 'Cy', 'email' => 'cy@mfg.example', 'company_id' => $this->company->id, 'title' => 'CFO']);

    $result = app(AbmPlayRunner::class)->run($this->account, 'executive_outreach', $this->owner->id);
    expect($result['steps'])->toHaveCount(2);

    $tasks = Activity::where('type', 'task')->orderBy('id')->get();
    expect($tasks)->toHaveCount(2)
        ->and($tasks[0]->title)->toContain('Executive outreach')
        ->and((string) $tasks[0]->body)->not->toBe('')
        ->and($tasks[0]->user_id)->toBe($this->owner->id)
        ->and($tasks[0]->due_at)->not->toBeNull();

    // Orchestration proposes; it never reaches a third party.
    expect(OutboundMessage::count())->toBe(0);

    // Unknown plays are refused at the endpoint.
    app(CurrentOrganization::class)->forget();
    $this->actingAs($this->owner)->post(route('sales.accounts.play', $this->account->id), ['play' => 'spam_everyone'])
        ->assertSessionHasErrors('play');
    app(CurrentOrganization::class)->set($this->org);
});
