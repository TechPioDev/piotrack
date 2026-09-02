<?php

declare(strict_types=1);

/**
 * Multi-Location close-out (Phase 12 — MLOC-005/006/008/009/010/011).
 *
 * Per-branch marketing scoping (local SEO footprint, branch-bound campaigns
 * and content), the regional calendar with a Central group, deterministic
 * brand-compliance checks, and the franchise hierarchy: linking demands one
 * owner of both organizations, roll-ups read only explicitly linked children,
 * and brand push copies the franchisor profile down.
 */

use App\Authorization\Role;
use App\Models\AdCampaign;
use App\Models\BrandProfile;
use App\Models\Citation;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Keyword;
use App\Models\Organization;
use App\Models\PageSection;
use App\Models\SeoLocation;
use App\Models\SitePage;
use App\Services\OrganizationService;
use App\Services\Web\FranchiseService;
use App\Services\Web\LocationService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Franchisor MSP');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);

    $this->philly = SeoLocation::create([
        'name' => 'Philadelphia HQ', 'street' => '1 Market St', 'city' => 'Philadelphia',
        'region' => 'PA', 'phone' => '+1-215-555-0100', 'territory' => 'Southeast PA', 'is_active' => true,
    ]);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('rolls the branch marketing footprint up from real records', function () {
    Citation::create(['seo_location_id' => $this->philly->id, 'source' => 'yelp', 'status' => 'consistent']);
    Citation::create(['seo_location_id' => $this->philly->id, 'source' => 'yellowpages', 'status' => 'mismatch']);
    Keyword::create(['phrase' => 'managed it services philadelphia', 'intent' => 'bottom_funnel', 'location' => 'Philadelphia']);
    AdCampaign::create(['platform' => 'google_search', 'name' => 'Philly search', 'status' => 'active', 'seo_location_id' => $this->philly->id]);
    ContentPiece::create(['title' => 'IT support in Philadelphia', 'slug' => 'it-philly', 'content_type' => 'article', 'seo_location_id' => $this->philly->id]);

    $row = collect(app(LocationService::class)->report())->firstWhere('id', $this->philly->id);

    expect($row['citations'])->toBe(2)
        ->and($row['consistent_citations'])->toBe(1)
        ->and($row['geo_keywords'])->toBe(1)
        ->and($row['campaigns'])->toBe(1)
        ->and($row['active_campaigns'])->toBe(1)
        ->and($row['content_pieces'])->toBe(1);
});

it('groups the regional calendar by branch with unscoped work under Central', function () {
    ContentPiece::create(['title' => 'Central pillar guide', 'slug' => 'central-guide', 'content_type' => 'guide']);
    ContentPiece::create(['title' => 'Philly case study', 'slug' => 'philly-case', 'content_type' => 'case_study', 'seo_location_id' => $this->philly->id]);
    AdCampaign::create(['platform' => 'google_search', 'name' => 'Brand national', 'status' => 'active', 'start_date' => '2026-09-01']);

    $calendar = app(LocationService::class)->regionalCalendar();

    expect($calendar[0]['location'])->toBe('Central')
        ->and(collect($calendar[0]['entries'])->pluck('title'))->toContain('Central pillar guide')->toContain('Brand national');

    $philly = collect($calendar)->firstWhere('location', 'Philadelphia HQ');
    expect($philly['entries'])->toHaveCount(1)
        ->and($philly['entries'][0]['title'])->toBe('Philly case study')
        ->and($philly['entries'][0]['kind'])->toBe('content');
});

it('checks brand consistency per branch and names each fix', function () {
    BrandProfile::create(['tagline' => 'IT that passes the audit.']);

    // A compliant branch: published page naming the city, description, tagline.
    $goodPage = SitePage::create([
        'type' => 'location', 'slug' => 'philadelphia-msp', 'title' => 'Managed IT Services in Philadelphia',
        'meta_description' => 'Philadelphia managed IT.', 'status' => SitePage::STATUS_PUBLISHED,
        'seo_location_id' => $this->philly->id,
    ]);
    PageSection::create(['site_page_id' => $goodPage->id, 'type' => 'hero', 'heading' => 'IT that passes the audit.', 'sort_order' => 1]);

    // A deviating branch: published page that names no market, no description,
    // no tagline — and an incomplete NAP.
    $pittsburgh = SeoLocation::create(['name' => 'Pittsburgh', 'city' => 'Pittsburgh', 'is_active' => true]);
    SitePage::create([
        'type' => 'location', 'slug' => 'second-office', 'title' => 'Our second office',
        'status' => SitePage::STATUS_PUBLISHED, 'seo_location_id' => $pittsburgh->id,
    ]);

    $compliance = collect(app(LocationService::class)->brandCompliance())->keyBy('location');

    expect($compliance['Philadelphia HQ']['ok'])->toBeTrue();

    $bad = collect($compliance['Pittsburgh']['checks'])->keyBy('key');
    expect($compliance['Pittsburgh']['ok'])->toBeFalse()
        ->and($bad['nap']['ok'])->toBeFalse()
        ->and($bad['title']['ok'])->toBeFalse()
        ->and($bad['title']['detail'])->toContain('Pittsburgh')
        ->and($bad['description']['ok'])->toBeFalse()
        ->and($bad['tagline']['ok'])->toBeFalse();
});

it('links, rolls up and pushes brand across the franchise', function () {
    // A second organization owned by the same person, with its own records.
    $child = app(OrganizationService::class)->create($this->owner, 'Franchisee South');
    app(CurrentOrganization::class)->set($child);
    Contact::create(['first_name' => 'South', 'email' => 'lead@south.test', 'lifecycle_stage' => 'sql']);
    app(CurrentOrganization::class)->set($this->org);

    // Creating an organization switches the user into it; the franchisor must
    // be the active organization for the link request.
    $this->owner->forceFill(['current_organization_id' => $this->org->id])->save();
    $this->owner->refresh();

    BrandProfile::create(['tagline' => 'IT that passes the audit.', 'positioning_statement' => 'For regulated manufacturers.']);

    $this->actingAs($this->owner)
        ->post(route('franchise.link'), ['organization_id' => $child->id])
        ->assertRedirect();

    expect($child->fresh()->parent_organization_id)->toBe($this->org->id);

    // Roll-up reflects the child's real records.
    app(CurrentOrganization::class)->set($this->org);
    $rollup = app(FranchiseService::class)->rollup($this->org->fresh());
    expect($rollup)->toHaveCount(1)
        ->and($rollup[0]['name'])->toBe('Franchisee South')
        ->and($rollup[0]['contacts'])->toBe(1)
        ->and($rollup[0]['sqls'])->toBe(1);

    // Brand push lands in the child's profile.
    $this->actingAs($this->owner)
        ->post(route('franchise.push', $child->id))
        ->assertRedirect();

    $childBrand = BrandProfile::withoutGlobalScope('tenant')->where('organization_id', $child->id)->first();
    expect($childBrand->tagline)->toBe('IT that passes the audit.')
        ->and($childBrand->positioning_statement)->toBe('For regulated manufacturers.');

    // Unlink restores independence.
    $this->actingAs($this->owner)->delete(route('franchise.unlink', $child->id))->assertRedirect();
    expect($child->fresh()->parent_organization_id)->toBeNull();
});

it('refuses links the acting user does not own both sides of, and gates the rest', function () {
    // An organization owned by someone ELSE cannot be pulled under this one.
    [$foreign] = makeOrganization('Foreign MSP');

    $this->actingAs($this->owner)
        ->from(route('franchise.index'))
        ->post(route('franchise.link'), ['organization_id' => $foreign->id])
        ->assertRedirect(route('franchise.index'))
        ->assertSessionHasErrors('organization_id');

    expect($foreign->fresh()->parent_organization_id)->toBeNull();

    // A viewer can read the page but not manage links.
    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)->get(route('franchise.index'))->assertOk();
    $this->actingAs($viewer)->post(route('franchise.link'), ['organization_id' => $foreign->id])->assertForbidden();

    // Unlinking an organization that is not a franchisee 404s.
    $this->actingAs($this->owner)->delete(route('franchise.unlink', $foreign->id))->assertNotFound();

    // A franchisee cannot take franchisees of its own (no chains).
    $child = app(OrganizationService::class)->create($this->owner, 'Chain Child');
    $grandchild = app(OrganizationService::class)->create($this->owner, 'Chain Grandchild');
    app(FranchiseService::class)->linkChild($this->org, $child);

    expect(fn () => app(FranchiseService::class)->linkChild($child, $grandchild))
        ->toThrow(RuntimeException::class, 'A franchisee cannot take franchisees of its own.');
});
