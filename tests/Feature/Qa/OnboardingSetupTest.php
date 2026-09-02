<?php

declare(strict_types=1);

/**
 * Customer Onboarding close-out (Phase 19 — ONBD-006..012).
 *
 * The setup wizard writes only the real records the platform runs on:
 * taxonomy activation, the brand website, KPI targets, LIVE firmographic
 * scoring rules, competitors — and finishing runs the first technical audit.
 * Progress derives from state, so it is resumable for free.
 */

use App\Authorization\Role;
use App\Models\BrandProfile;
use App\Models\Company;
use App\Models\Competitor;
use App\Models\Contact;
use App\Models\KpiTarget;
use App\Models\ScoringRule;
use App\Models\SeoAudit;
use App\Models\SeoLocation;
use App\Models\ServiceLine;
use App\Models\Vertical;
use App\Services\Sales\LeadScoringService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Setup Org');
    subscribeOrganization($this->org, 'enterprise');
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('activates exactly the chosen taxonomy and creates the home-market branch', function () {
    $this->actingAs($this->owner)->post(route('onboarding.business'), [
        'services' => ['cmmc', 'managed_it'],
        'verticals' => [],
        'city' => 'Philadelphia',
        'region' => 'PA',
    ])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    expect(ServiceLine::where('is_active', true)->pluck('key')->sort()->values()->all())->toBe(['cmmc', 'managed_it'])
        ->and(Vertical::where('is_active', true)->count())->toBe(0)
        ->and(SeoLocation::where('city', 'Philadelphia')->where('region', 'PA')->exists())->toBeTrue();

    // Running again with a different choice re-activates cleanly and never
    // duplicates the branch.
    $this->actingAs($this->owner)->post(route('onboarding.business'), [
        'services' => ['cybersecurity'], 'verticals' => [], 'city' => 'Philadelphia',
    ])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    expect(ServiceLine::where('is_active', true)->pluck('key')->all())->toBe(['cybersecurity'])
        ->and(SeoLocation::count())->toBe(1);
});

it('stores the website, goals, and an ICP that scores leads immediately', function () {
    $this->actingAs($this->owner)->post(route('onboarding.website'), ['website_url' => 'https://setup-org.test'])->assertRedirect();
    $this->actingAs($this->owner)->post(route('onboarding.goals'), ['leads' => 40, 'mrr' => 500000])->assertRedirect();
    $this->actingAs($this->owner)->post(route('onboarding.icp'), [
        'industry' => 'Manufacturing', 'company_size' => '100-250', 'region' => 'PA',
    ])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    expect(BrandProfile::first()->website_url)->toBe('https://setup-org.test')
        ->and(KpiTarget::pluck('metric')->sort()->values()->all())->toBe(['leads', 'mrr'])
        ->and(ScoringRule::where('name', 'like', 'ICP:%')->count())->toBe(3);

    // The ICP is not a note — it scores. A matching lead earns 15+10+5.
    $company = Company::create(['name' => 'Fit Co', 'size' => '100-250', 'region' => 'PA', 'industry' => 'Manufacturing']);
    $lead = Contact::create(['first_name' => 'Fit', 'email' => 'fit@x.test', 'company_id' => $company->id, 'lifecycle_stage' => 'lead']);
    expect(app(LeadScoringService::class)->apply($lead)->lead_score)->toBe(30);

    // Re-submitting the ICP updates in place, never duplicates.
    $this->actingAs($this->owner)->post(route('onboarding.icp'), ['industry' => 'Healthcare'])->assertRedirect();
    app(CurrentOrganization::class)->set($this->org);
    expect(ScoringRule::where('name', 'ICP: industry')->count())->toBe(1)
        ->and(ScoringRule::where('name', 'ICP: industry')->value('value'))->toBe('Healthcare');
});

it('adds competitors deduped by domain', function () {
    $this->actingAs($this->owner)->post(route('onboarding.competitors'), [
        'competitors' => [
            ['name' => 'Rival MSP', 'domain' => 'rivalmsp.com'],
            ['name' => 'Rival again', 'domain' => 'rivalmsp.com'],
            ['name' => 'Other IT', 'domain' => null],
        ],
    ])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    expect(Competitor::count())->toBe(2)
        ->and(Competitor::where('domain', 'rivalmsp.com')->count())->toBe(1);
});

it('finishing runs the first audit against the captured site, or completes quietly without one', function () {
    // No website yet: completes, no audit, no error.
    $this->actingAs($this->owner)->post(route('onboarding.complete'))->assertRedirect(route('dashboard'));
    app(CurrentOrganization::class)->set($this->org);
    expect(SeoAudit::count())->toBe(0);
    app(CurrentOrganization::class)->forget();

    // With a website: the finish step runs the technical audit (ONBD-012).
    Http::fake(['*' => Http::response('<html><head><title>Setup Org — Managed IT Services Philadelphia</title><meta name="description" content="Managed IT and CMMC compliance for Philadelphia manufacturers, with 24/7 support."><meta name="viewport" content="width=device-width"></head><body><main><h1>Managed IT</h1><h2>Why us</h2><p>'.str_repeat('Real content. ', 200).'</p></main></body></html>')]);
    $this->actingAs($this->owner)->post(route('onboarding.website'), ['website_url' => 'https://93.184.216.34/'])->assertRedirect();
    $this->actingAs($this->owner)->post(route('onboarding.complete'))->assertRedirect(route('dashboard'));

    app(CurrentOrganization::class)->set($this->org);
    $audit = SeoAudit::firstOrFail();
    expect($audit->url)->toBe('https://93.184.216.34/')
        ->and($audit->score)->toBeGreaterThan(0);
});

it('derives the new checklist steps from wizard state and gates the wizard', function () {
    // Before setup: the new steps exist and are honest about being undone.
    $this->actingAs($this->owner)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('onboarding.steps', fn ($steps) => collect($steps)->firstWhere('key', 'website')['done'] === false
            && collect($steps)->firstWhere('key', 'icp')['done'] === false
            && collect($steps)->firstWhere('key', 'competitors')['done'] === false),
    );

    $this->actingAs($this->owner)->post(route('onboarding.website'), ['website_url' => 'https://setup-org.test']);
    $this->actingAs($this->owner)->post(route('onboarding.icp'), ['industry' => 'Manufacturing']);

    $this->actingAs($this->owner)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('onboarding.steps', fn ($steps) => collect($steps)->firstWhere('key', 'website')['done'] === true
            && collect($steps)->firstWhere('key', 'icp')['done'] === true),
    );

    // Setup shapes the whole tenant, so it is an admin act.
    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)->get(route('onboarding.setup'))->assertForbidden();
    $this->actingAs($viewer)->post(route('onboarding.icp'), ['industry' => 'X'])->assertForbidden();
});
