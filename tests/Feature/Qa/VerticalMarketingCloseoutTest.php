<?php

declare(strict_types=1);

/**
 * Vertical Marketing close-out (Phase 39 — VERT-014/015/016/017/018/019).
 *
 * The explicit vertical bindings the coverage report now joins on (content,
 * ads, email campaigns, workflows, target accounts), the per-vertical
 * messaging framework, and the upgraded coverage report — hard joins with
 * name matching kept only as a fallback.
 */

use App\Authorization\Role;
use App\Models\AdCampaign;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\ContentPiece;
use App\Models\TargetAccount;
use App\Models\Vertical;
use App\Models\Workflow;
use App\Services\Web\TaxonomyService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('VERT Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
    // The taxonomy is provisioned with every organization (VERT-001).
    $this->vertical = Vertical::where('key', 'healthcare')->firstOrFail();
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('binds content, ads, email campaigns, workflows and accounts to a vertical through their endpoints', function () {
    // VERT-014/017: content (a case study, for the case-study count too).
    $this->actingAs($this->owner)->post(route('content.pieces.store'), [
        'title' => 'HIPAA-ready IT for clinics', 'content_type' => 'case_study', 'vertical_id' => $this->vertical->id,
    ])->assertRedirect();
    expect(ContentPiece::firstOrFail()->vertical_id)->toBe($this->vertical->id);

    // VERT-015: ad campaign.
    $this->actingAs($this->owner)->post(route('ads.campaigns.store'), [
        'name' => 'Healthcare search', 'platform' => 'google_search', 'objective' => 'leads',
        'daily_budget' => 1000, 'vertical_id' => $this->vertical->id,
    ])->assertRedirect();
    expect(AdCampaign::firstOrFail()->vertical_id)->toBe($this->vertical->id);

    // VERT-018: email campaign + workflow sequence.
    $this->actingAs($this->owner)->post(route('marketing.campaigns.store'), [
        'name' => 'Healthcare nurture', 'channel' => 'email', 'vertical_id' => $this->vertical->id,
    ])->assertRedirect();
    expect(Campaign::firstOrFail()->vertical_id)->toBe($this->vertical->id);

    $this->actingAs($this->owner)->post(route('marketing.automation.store'), [
        'name' => 'Healthcare onboarding drip', 'trigger_type' => 'list_added', 'vertical_id' => $this->vertical->id,
    ])->assertRedirect();
    expect(Workflow::firstOrFail()->vertical_id)->toBe($this->vertical->id);

    // VERT-019: target account.
    $company = Company::create(['name' => 'Clinic Group']);
    $this->actingAs($this->owner)->post(route('sales.accounts.store'), [
        'company_id' => $company->id, 'tier' => 1, 'vertical_id' => $this->vertical->id,
    ])->assertRedirect();
    expect(TargetAccount::firstOrFail()->vertical_id)->toBe($this->vertical->id);

    // A foreign vertical can never be bound (tenant-checked rule).
    [$otherOrg] = makeOrganization('Other VERT Org');
    app(CurrentOrganization::class)->set($otherOrg);
    $foreign = Vertical::where('key', 'legal')->firstOrFail();
    app(CurrentOrganization::class)->set($this->org);

    $this->actingAs($this->owner)->from(route('content.pieces.index'))->post(route('content.pieces.store'), [
        'title' => 'X', 'content_type' => 'article', 'vertical_id' => $foreign->id,
    ])->assertRedirect()->assertSessionHasErrors('vertical_id');
});

it('reports coverage from hard joins, with name matching only as the fallback', function () {
    // Bound records that do NOT carry the vertical's name — joins must find them.
    ContentPiece::create(['title' => 'Uptime for clinics', 'slug' => 'v-cs', 'content_type' => 'case_study', 'status' => 'published', 'vertical_id' => $this->vertical->id]);
    ContentPiece::create(['title' => 'Ransomware guide', 'slug' => 'v-guide', 'content_type' => 'guide', 'status' => 'published', 'vertical_id' => $this->vertical->id]);
    AdCampaign::create(['name' => 'Clinics search', 'platform' => 'google_search', 'objective' => 'leads', 'daily_budget' => 500, 'vertical_id' => $this->vertical->id]);
    Campaign::create(['name' => 'Clinic nurture', 'channel' => 'email', 'status' => 'draft', 'vertical_id' => $this->vertical->id]);
    Workflow::create(['name' => 'Clinic drip', 'trigger_type' => 'list_added', 'status' => 'draft', 'vertical_id' => $this->vertical->id]);
    TargetAccount::create(['company_id' => Company::create(['name' => 'MedCo'])->id, 'tier' => 2, 'vertical_id' => $this->vertical->id]);

    // Unbound but name-matched — the fallback still counts them.
    ContentPiece::create(['title' => 'Healthcare compliance checklist', 'slug' => 'v-name', 'content_type' => 'checklist', 'status' => 'draft']);
    Campaign::create(['name' => 'Healthcare webinar invite', 'channel' => 'email', 'status' => 'draft']);

    $coverage = app(TaxonomyService::class)->verticalCoverage($this->vertical);

    expect($coverage['content'])->toBe(3)       // 2 bound + 1 name-matched
        ->and($coverage['campaigns'])->toBe(2)  // 1 bound + 1 name-matched
        ->and($coverage['ads'])->toBe(1)
        ->and($coverage['case_studies'])->toBe(1)
        ->and($coverage['sequences'])->toBe(2)  // workflow + bound email campaign
        ->and($coverage['accounts'])->toBe(1);

    // The taxonomy page carries the new columns.
    $row = collect($this->actingAs($this->owner)->get(route('web.taxonomy.index'))
        ->assertOk()->viewData('page')['props']['verticals'])
        ->firstWhere('key', 'healthcare');
    expect($row['ads'])->toBe(1)->and($row['accounts'])->toBe(1);
});

it('stores the per-vertical messaging framework behind the taxonomy permission', function () {
    $this->actingAs($this->owner)->patch(route('web.taxonomy.vertical', $this->vertical), [
        'value_proposition' => 'IT that keeps clinics compliant and online.',
        'pain_points' => 'HIPAA audits; EHR downtime; ransomware.',
        'differentiators' => 'Healthcare-only support pods.',
        'compliance_notes' => 'No outcome guarantees; HIPAA language reviewed by counsel.',
    ])->assertRedirect();

    $this->vertical->refresh();
    expect($this->vertical->messaging['value_proposition'])->toBe('IT that keeps clinics compliant and online.')
        ->and($this->vertical->messaging['pain_points'])->toBe('HIPAA audits; EHR downtime; ransomware.')
        ->and($this->vertical->compliance_notes)->toContain('HIPAA language');

    // The coverage report carries the messaging for the page editor.
    $row = collect(app(TaxonomyService::class)->verticalGaps())->firstWhere('key', 'healthcare');
    expect($row['messaging']['differentiators'])->toBe('Healthcare-only support pods.');

    // A member without web.taxonomy.manage cannot write messaging.
    $member = addMember($this->org, Role::SalesRepresentative);
    $this->actingAs($member)->patch(route('web.taxonomy.vertical', $this->vertical), [
        'value_proposition' => 'overwritten',
    ])->assertForbidden();
    expect($this->vertical->refresh()->messaging['value_proposition'])->toBe('IT that keeps clinics compliant and online.');
});
