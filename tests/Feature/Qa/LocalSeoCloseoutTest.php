<?php

declare(strict_types=1);

/**
 * Local SEO close-out (Phase 43 — LSEO-001/009/010/013/015/016).
 *
 * The per-location Map-Pack readiness checklist computed from first-party
 * records (profile, citations, local page, keywords, REP review strength),
 * location-bound outreach placements as local backlinks, and the local
 * authority rollup with guarded recommendations. Live GBP API work is never
 * claimed.
 */

use App\Models\Citation;
use App\Models\Keyword;
use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use App\Models\Review;
use App\Models\SeoLocation;
use App\Models\SitePage;
use App\Services\Seo\LocalAuthorityService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('LocalSEO Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('scores Map-Pack readiness from first-party records, citing what is missing', function () {
    $bare = SeoLocation::create(['name' => 'Plano branch', 'city' => 'Plano']);

    $readiness = app(LocalAuthorityService::class)->gbpReadiness($bare);
    $byKey = collect($readiness['checks'])->keyBy('key');

    expect($readiness['score'])->toBeLessThan(50)
        ->and($byKey['profile']['ok'])->toBeFalse()
        ->and($byKey['profile']['detail'])->toContain('street')
        ->and($byKey['place_id']['ok'])->toBeFalse()
        ->and($byKey['citations']['detail'])->toContain('No citations')
        ->and($byKey['reviews']['detail'])->toContain('No reviews');

    // Fully built out: every local ranking factor the platform holds.
    $full = SeoLocation::create([
        'name' => 'Dallas HQ', 'street' => '100 Main St', 'city' => 'Dallas', 'region' => 'TX',
        'postal_code' => '75201', 'phone' => '+1-214-555-0100', 'website' => 'https://acme.example',
        'gbp_place_id' => 'ChIJdallas123',
    ]);
    foreach (['google', 'yelp', 'bing'] as $source) {
        Citation::create(['seo_location_id' => $full->id, 'source' => $source, 'listed_name' => 'Dallas HQ', 'listed_address' => '100 Main St', 'listed_phone' => '+1-214-555-0100', 'status' => 'consistent', 'checked_at' => now()]);
    }
    SitePage::create(['type' => 'location', 'slug' => 'dallas', 'title' => 'Managed IT in Dallas', 'status' => SitePage::STATUS_PUBLISHED, 'seo_location_id' => $full->id]);
    Keyword::create(['phrase' => 'managed it services dallas', 'intent' => 'commercial']);
    for ($i = 0; $i < 10; $i++) {
        Review::create(['source' => 'google', 'author_name' => 'R'.$i, 'rating' => 5, 'sentiment' => 'positive', 'body' => 'Great.']);
    }

    $readiness = app(LocalAuthorityService::class)->gbpReadiness($full);
    $byKey = collect($readiness['checks'])->keyBy('key');

    expect($readiness['score'])->toBe(100)
        ->and($byKey['citations']['detail'])->toContain('3 of 3')
        ->and($byKey['keywords']['detail'])->toContain('Dallas')
        // LSEO-013: review strength (REP machinery) feeds the Map Pack check.
        ->and($byKey['reviews']['detail'])->toContain('10 reviews averaging 5');

    // The local page carries both reports per location.
    $row = collect($this->actingAs($this->owner)->get(route('seo.local.index'))
        ->assertOk()->viewData('page')['props']['locations'])->firstWhere('name', 'Dallas HQ');
    expect($row['gbp']['score'])->toBe(100)->and($row['authority']['citations'])->toBe(3);
});

it('binds outreach placements to a branch as its local backlinks', function () {
    $location = SeoLocation::create(['name' => 'Plano branch', 'city' => 'Plano']);
    $campaign = OutreachCampaign::create(['name' => 'Plano links', 'type' => 'link_building']);

    $this->actingAs($this->owner)->post(route('content.outreach.prospects.store', $campaign), [
        'name' => 'Plano Chamber of Commerce', 'domain' => 'planochamber.example',
        'domain_authority' => 40, 'seo_location_id' => $location->id,
    ])->assertRedirect();

    $prospect = OutreachProspect::firstOrFail();
    expect($prospect->seo_location_id)->toBe($location->id);

    // A placement bound to the branch shows up as its local backlink.
    $prospect->update(['status' => 'placed', 'placement_url' => 'https://planochamber.example/members/acme']);
    $authority = app(LocalAuthorityService::class)->authority($location);
    expect($authority['placements'])->toHaveCount(1)
        ->and($authority['placements'][0]['domain_authority'])->toBe(40)
        ->and($authority['avg_da'])->toBe(40);

    // Another tenant's location can never be bound.
    [$otherOrg] = makeOrganization('Other Local Org');
    app(CurrentOrganization::class)->set($otherOrg);
    $foreign = SeoLocation::create(['name' => 'Foreign branch']);
    app(CurrentOrganization::class)->set($this->org);

    $this->actingAs($this->owner)->from(route('content.outreach.index'))
        ->post(route('content.outreach.prospects.store', $campaign), [
            'name' => 'X', 'seo_location_id' => $foreign->id,
        ])->assertRedirect()->assertSessionHasErrors('seo_location_id');
});

it('rolls up local authority with recommendations citing their numbers', function () {
    $location = SeoLocation::create(['name' => 'Frisco branch', 'city' => 'Frisco']);

    $authority = app(LocalAuthorityService::class)->authority($location);
    $recs = implode(' ', $authority['recommendations']);

    expect($authority['citations'])->toBe(0)
        ->and($recs)->toContain('Only 0 citations')
        ->and($recs)->toContain('No placements are bound');

    // An inconsistent citation gets its own named problem.
    Citation::create(['seo_location_id' => $location->id, 'source' => 'yelp', 'listed_name' => 'Frisco Branch LLC', 'listed_address' => 'Wrong St', 'listed_phone' => '000', 'status' => 'inconsistent', 'mismatches' => ['name', 'address'], 'checked_at' => now()]);
    $recs = implode(' ', app(LocalAuthorityService::class)->authority($location)['recommendations']);
    expect($recs)->toContain('1 citations are inconsistent');
});
