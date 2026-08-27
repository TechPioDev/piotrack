<?php

declare(strict_types=1);

/**
 * Funnel Builder (Module 05). Conversion must be cumulative (a rate can never
 * exceed 100%), every attachable type must round-trip, and asset references
 * must be tenant-sealed.
 */

use App\Authorization\Role;
use App\Models\AdCampaign;
use App\Models\BookingPage;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Form;
use App\Models\Funnel;
use App\Models\FunnelAsset;
use App\Models\LandingPage;
use App\Models\RetargetingAudience;
use App\Models\SitePage;
use App\Models\SocialPost;
use App\Models\Workflow;
use App\Services\Marketing\FunnelService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Funnel Org');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->funnel = Funnel::create(['name' => 'MSP Growth Funnel']);
    $this->tof = $this->funnel->stages()->create(['name' => 'Interest', 'position' => 1, 'category' => 'tof', 'lifecycle_stage' => 'lead']);
    $this->mof = $this->funnel->stages()->create(['name' => 'Evaluation', 'position' => 2, 'category' => 'mof', 'lifecycle_stage' => 'mql']);
    $this->bof = $this->funnel->stages()->create(['name' => 'Sales-ready', 'position' => 3, 'category' => 'bof', 'lifecycle_stage' => 'sql']);
    app(CurrentOrganization::class)->forget();
});

it('measures cumulative conversion that can never exceed 100%', function () {
    app(CurrentOrganization::class)->set($this->org);
    // 4 leads, 1 mql, 3 sql: naive per-stage math would say mql->sql = 300%.
    foreach (range(1, 4) as $i) {
        Contact::create(['first_name' => "L{$i}", 'email' => "l{$i}@f.test", 'lifecycle_stage' => 'lead']);
    }
    Contact::create(['first_name' => 'M', 'email' => 'm@f.test', 'lifecycle_stage' => 'mql']);
    foreach (range(1, 3) as $i) {
        Contact::create(['first_name' => "S{$i}", 'email' => "s{$i}@f.test", 'lifecycle_stage' => 'sql']);
    }

    $detail = app(FunnelService::class)->detail($this->funnel);
    app(CurrentOrganization::class)->forget();

    // at-or-beyond: lead 8, mql 4, sql 3.
    expect($detail[0]['at_or_beyond'])->toBe(8)
        ->and($detail[1]['at_or_beyond'])->toBe(4)
        ->and($detail[1]['conversion_pct'])->toBe(50.0)
        ->and($detail[2]['conversion_pct'])->toBe(75.0);
    foreach ($detail as $row) {
        expect($row['conversion_pct'] === null || $row['conversion_pct'] <= 100)->toBeTrue();
    }
});

it('attaches and detaches every supported asset type', function () {
    app(CurrentOrganization::class)->set($this->org);
    $records = [
        'content' => ContentPiece::create(['title' => 'CMMC guide', 'content_type' => 'guide', 'status' => 'published', 'slug' => 'cmmc-guide-'.uniqid(), 'body' => 'x']),
        'social' => SocialPost::create(['channel' => 'linkedin', 'type' => 'post', 'body' => 'Why CMMC matters', 'status' => 'published']),
        'ad_campaign' => AdCampaign::create(['platform' => 'google', 'name' => 'Awareness', 'status' => 'active']),
        'retargeting' => RetargetingAudience::create(['name' => 'Site visitors', 'source' => 'all']),
        'email_campaign' => Campaign::create(['name' => 'Nurture', 'channel' => 'email', 'status' => 'draft']),
        'workflow' => Workflow::create(['name' => 'Drip', 'trigger_type' => 'manual', 'status' => 'active']),
        'landing_page' => LandingPage::create(['name' => 'CMMC LP', 'slug' => 'cmmc-lp-'.uniqid(), 'headline' => 'x', 'status' => 'draft']),
        'form' => Form::create(['name' => 'Assessment', 'slug' => 'assess-'.uniqid(), 'fields' => [], 'status' => 'draft']),
        'booking_page' => BookingPage::create(['name' => 'Consult', 'slug' => 'consult-'.uniqid(), 'meeting_type' => 'consultation', 'duration_minutes' => 30]),
        'site_page' => SitePage::create(['type' => 'service', 'slug' => 'managed-it-'.uniqid(), 'title' => 'Managed IT', 'template' => 'default', 'status' => 'draft']),
    ];
    app(CurrentOrganization::class)->forget();

    foreach ($records as $type => $record) {
        $this->actingAs($this->owner)
            ->post(route('marketing.funnels.assets.attach', [$this->funnel->id, $this->tof->id]), [
                'asset_type' => $type,
                'asset_id' => $record->id,
            ])->assertRedirect()->assertSessionHasNoErrors();
    }

    $props = $this->actingAs($this->owner)
        ->get(route('marketing.funnels.show', $this->funnel->id))->assertOk()
        ->viewData('page')['props'];

    $assets = collect($props['stages'])->firstWhere('id', $this->tof->id)['assets'];
    expect($assets)->toHaveCount(count($records))
        ->and(collect($assets)->pluck('type')->sort()->values()->all())->toBe(collect(array_keys($records))->sort()->values()->all())
        ->and(collect($assets)->firstWhere('type', 'content')['name'])->toBe('CMMC guide')
        ->and(collect($assets)->firstWhere('type', 'ad_campaign')['url'])->toContain('/ads/campaigns/');

    // Detach one and it disappears.
    $assetRow = FunnelAsset::withoutGlobalScope('tenant')->where('asset_type', 'content')->firstOrFail();
    $this->actingAs($this->owner)
        ->delete(route('marketing.funnels.assets.detach', [$this->funnel->id, $this->tof->id, $assetRow->id]))
        ->assertRedirect();
    expect(FunnelAsset::withoutGlobalScope('tenant')->whereKey($assetRow->id)->exists())->toBeFalse();
});

it('flags stages without assets as gaps', function () {
    app(CurrentOrganization::class)->set($this->org);
    $piece = ContentPiece::create(['title' => 'Guide', 'content_type' => 'guide', 'status' => 'published', 'slug' => 'g-'.uniqid(), 'body' => 'x']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->post(route('marketing.funnels.assets.attach', [$this->funnel->id, $this->tof->id]), [
        'asset_type' => 'content', 'asset_id' => $piece->id,
    ]);

    $stages = collect($this->actingAs($this->owner)
        ->get(route('marketing.funnels.show', $this->funnel->id))
        ->viewData('page')['props']['stages']);

    expect($stages->firstWhere('id', $this->tof->id)['gap'])->toBeFalse()
        ->and($stages->firstWhere('id', $this->mof->id)['gap'])->toBeTrue();
});

it('rejects unknown types and other tenants\' assets', function () {
    [$orgB] = makeOrganization('Other Funnel Org');
    app(CurrentOrganization::class)->set($orgB);
    $foreign = ContentPiece::create(['title' => 'Foreign', 'content_type' => 'guide', 'status' => 'published', 'slug' => 'f-'.uniqid(), 'body' => 'x']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->post(route('marketing.funnels.assets.attach', [$this->funnel->id, $this->tof->id]), [
        'asset_type' => 'spreadsheet', 'asset_id' => 1,
    ])->assertSessionHasErrors('asset_type');

    $this->actingAs($this->owner)->post(route('marketing.funnels.assets.attach', [$this->funnel->id, $this->tof->id]), [
        'asset_type' => 'content', 'asset_id' => $foreign->id,
    ])->assertSessionHasErrors('asset_id');

    expect(FunnelAsset::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('404s when the stage does not belong to the funnel', function () {
    app(CurrentOrganization::class)->set($this->org);
    $other = Funnel::create(['name' => 'Other']);
    $otherStage = $other->stages()->create(['name' => 'X', 'position' => 1, 'category' => 'tof']);
    $piece = ContentPiece::create(['title' => 'P', 'content_type' => 'guide', 'status' => 'published', 'slug' => 'p-'.uniqid(), 'body' => 'x']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->post(route('marketing.funnels.assets.attach', [$this->funnel->id, $otherStage->id]), [
        'asset_type' => 'content', 'asset_id' => $piece->id,
    ])->assertNotFound();
});

it('lets read-only roles see the funnel but not compose it', function () {
    // The Analyst holds marketing.funnels.view but not campaigns.manage
    // (plain Viewers cannot open funnels at all - existing gating).
    $viewer = addMember($this->org, Role::Analyst);
    app(CurrentOrganization::class)->set($this->org);
    $piece = ContentPiece::create(['title' => 'P', 'content_type' => 'guide', 'status' => 'published', 'slug' => 'v-'.uniqid(), 'body' => 'x']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($viewer)->get(route('marketing.funnels.show', $this->funnel->id))->assertOk();
    $this->actingAs($viewer)->post(route('marketing.funnels.assets.attach', [$this->funnel->id, $this->tof->id]), [
        'asset_type' => 'content', 'asset_id' => $piece->id,
    ])->assertForbidden();
});

it('scopes attachable options to the tenant', function () {
    [$orgB] = makeOrganization('Other Options Org');
    app(CurrentOrganization::class)->set($orgB);
    ContentPiece::create(['title' => 'Foreign piece', 'content_type' => 'guide', 'status' => 'published', 'slug' => 'fo-'.uniqid(), 'body' => 'x']);
    app(CurrentOrganization::class)->forget();

    $props = $this->actingAs($this->owner)
        ->get(route('marketing.funnels.show', $this->funnel->id))
        ->viewData('page')['props'];

    expect(collect($props['attachable']['content']['options'])->pluck('name'))->not->toContain('Foreign piece')
        ->and(array_keys($props['attachable']))->toContain('content', 'form', 'booking_page', 'ad_campaign');
});
