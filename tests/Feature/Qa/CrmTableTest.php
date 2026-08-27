<?php

declare(strict_types=1);

/**
 * CRM data tables (Module 03): filters and sorting are allow-listed, bulk
 * actions respect permissions and tenancy, and saved views stay personal.
 */

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\SavedView;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Tables Org');
    subscribeOrganization($this->org, 'enterprise');
});

function seedContacts($test): void
{
    app(CurrentOrganization::class)->set($test->org);
    Contact::create(['first_name' => 'Alice', 'email' => 'alice@x.test', 'lifecycle_stage' => 'sql', 'lead_score' => 80, 'lead_source' => 'chat']);
    Contact::create(['first_name' => 'Bob', 'email' => 'bob@x.test', 'lifecycle_stage' => 'lead', 'lead_score' => 10, 'lead_source' => 'organic']);
    Contact::create(['first_name' => 'Cara', 'email' => 'cara@x.test', 'lifecycle_stage' => 'sql', 'lead_score' => 45, 'lead_source' => 'organic']);
    app(CurrentOrganization::class)->forget();
}

it('filters by lifecycle stage and source, and sorts by allow-listed columns only', function () {
    seedContacts($this);

    $names = fn (array $query) => collect($this->actingAs($this->owner)
        ->get(route('crm.contacts.index', $query))->assertOk()
        ->viewData('page')['props']['contacts']['data'])->pluck('name');

    expect($names(['lifecycle' => 'sql'])->all())->toContain('Alice', 'Cara')->not->toContain('Bob')
        ->and($names(['source' => 'organic'])->all())->toContain('Bob', 'Cara')->not->toContain('Alice')
        ->and($names(['sort' => 'lead_score', 'dir' => 'desc'])->first())->toBe('Alice')
        ->and($names(['sort' => 'lead_score', 'dir' => 'asc'])->first())->toBe('Bob');

    // A column outside the allow-list is rejected, not passed to orderBy.
    $this->actingAs($this->owner)
        ->get(route('crm.contacts.index', ['sort' => 'organization_id']))
        ->assertSessionHasErrors('sort');

    // Rows carry the pill payload.
    $row = collect($this->actingAs($this->owner)->get(route('crm.contacts.index'))
        ->viewData('page')['props']['contacts']['data'])->firstWhere('name', 'Alice');
    expect($row['lifecycle_stage'])->toBe('sql')
        ->and($row['lead_score'])->toBe(80)
        ->and($row['temperature'])->toBe('hot');
});

it('bulk assigns, restages and deletes with one audit entry each', function () {
    seedContacts($this);
    $ids = Contact::withoutGlobalScope('tenant')->where('organization_id', $this->org->id)->pluck('id')->all();

    $this->actingAs($this->owner)
        ->post(route('crm.contacts.bulk'), ['action' => 'stage', 'ids' => $ids, 'lifecycle_stage' => 'mql'])
        ->assertRedirect();
    expect(Contact::withoutGlobalScope('tenant')->where('organization_id', $this->org->id)->where('lifecycle_stage', 'mql')->count())->toBe(3);

    $this->actingAs($this->owner)
        ->post(route('crm.contacts.bulk'), ['action' => 'assign', 'ids' => $ids, 'owner_id' => $this->owner->id])
        ->assertRedirect();
    expect(Contact::withoutGlobalScope('tenant')->whereIn('id', $ids)->where('owner_id', $this->owner->id)->count())->toBe(3);

    $this->actingAs($this->owner)
        ->post(route('crm.contacts.bulk'), ['action' => 'delete', 'ids' => [$ids[0]]])
        ->assertRedirect();
    expect(Contact::withoutGlobalScope('tenant')->whereIn('id', $ids)->count())->toBe(2);

    expect(AuditLog::where('action', 'crm.contact.bulk_stage')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'crm.contact.bulk_delete')->exists())->toBeTrue();
});

it('refuses bulk actions to viewers, and bulk delete without the delete permission', function () {
    seedContacts($this);
    $viewer = addMember($this->org, Role::Viewer);
    $analyst = addMember($this->org, Role::Analyst);
    $id = Contact::withoutGlobalScope('tenant')->where('organization_id', $this->org->id)->value('id');

    $this->actingAs($viewer)
        ->post(route('crm.contacts.bulk'), ['action' => 'stage', 'ids' => [$id], 'lifecycle_stage' => 'mql'])
        ->assertForbidden();

    // The analyst can read but not update - the route gate stops it too.
    $this->actingAs($analyst)
        ->post(route('crm.contacts.bulk'), ['action' => 'delete', 'ids' => [$id]])
        ->assertForbidden();

    expect(Contact::withoutGlobalScope('tenant')->whereKey($id)->exists())->toBeTrue();
});

it('rejects bulk ids belonging to another tenant and changes nothing', function () {
    [$orgB] = makeOrganization('Other Org');
    app(CurrentOrganization::class)->set($orgB);
    $foreign = Contact::create(['first_name' => 'Foreign', 'email' => 'f@other.test']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)
        ->post(route('crm.contacts.bulk'), ['action' => 'delete', 'ids' => [$foreign->id]])
        ->assertSessionHasErrors('ids.0');

    expect(Contact::withoutGlobalScope('tenant')->whereKey($foreign->id)->exists())->toBeTrue();
});

it('saves, lists, applies and deletes personal views only', function () {
    $sarah = addMember($this->org, Role::SalesRepresentative);

    $this->actingAs($this->owner)
        ->post(route('crm.contacts.views.store'), ['name' => 'Hot SQLs', 'filters' => ['lifecycle' => 'sql', 'sort' => 'lead_score', 'dir' => 'desc']])
        ->assertRedirect();

    $view = SavedView::withoutGlobalScope('tenant')->firstWhere('name', 'Hot SQLs');
    expect($view)->not->toBeNull()
        ->and((int) $view->user_id)->toBe((int) $this->owner->id)
        ->and($view->filters['lifecycle'])->toBe('sql');

    // The saver sees it in the index props; a teammate does not (personal views).
    $mine = $this->actingAs($this->owner)->get(route('crm.contacts.index'))->viewData('page')['props']['views'];
    $theirs = $this->actingAs($sarah)->get(route('crm.contacts.index'))->viewData('page')['props']['views'];
    expect(collect($mine)->pluck('name'))->toContain('Hot SQLs')
        ->and(collect($theirs)->pluck('name'))->not->toContain('Hot SQLs');

    // A teammate cannot delete it; the owner can.
    $this->actingAs($sarah)->delete(route('crm.contacts.views.destroy', $view->id))->assertForbidden();
    $this->actingAs($this->owner)->delete(route('crm.contacts.views.destroy', $view->id))->assertRedirect();
    expect(SavedView::withoutGlobalScope('tenant')->whereKey($view->id)->exists())->toBeFalse();
});

it('keeps saved views inside the tenant', function () {
    app(CurrentOrganization::class)->set($this->org);
    $view = SavedView::create(['user_id' => $this->owner->id, 'resource' => 'contacts', 'name' => 'Mine', 'filters' => []]);
    app(CurrentOrganization::class)->forget();

    [, $ownerB] = makeOrganization('Other Org');
    $this->actingAs($ownerB)->delete(route('crm.contacts.views.destroy', $view->id))->assertNotFound();
    expect(SavedView::withoutGlobalScope('tenant')->whereKey($view->id)->exists())->toBeTrue();
});

it('sorts leads and companies by allow-listed columns', function () {
    app(CurrentOrganization::class)->set($this->org);
    Company::create(['name' => 'Zeta Corp']);
    Company::create(['name' => 'Alpha Inc']);
    app(CurrentOrganization::class)->forget();

    $companies = collect($this->actingAs($this->owner)
        ->get(route('crm.companies.index', ['sort' => 'name', 'dir' => 'asc']))->assertOk()
        ->viewData('page')['props']['companies']['data'])->pluck('name');
    expect($companies->first())->toBe('Alpha Inc');

    $this->actingAs($this->owner)
        ->get(route('crm.leads.index', ['sort' => 'password']))
        ->assertSessionHasErrors('sort');
});
