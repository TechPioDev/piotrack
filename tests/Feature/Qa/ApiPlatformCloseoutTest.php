<?php

declare(strict_types=1);

/**
 * API Platform close-out (Phase 28 — API-001/005).
 *
 * Filtering + sorting parity on every list (whitelisted, 422 on unknown sort),
 * and write coverage: update for contacts, create + update for companies and
 * deals — permission-gated, tenant-checked, enveloped. Deletes stay out of the
 * API deliberately.
 */

use App\Authorization\Role;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Support\CurrentOrganization;
use Laravel\Sanctum\Sanctum;

function apiCloseoutOrg(): array
{
    [$org, $owner] = makeOrganization('API Closeout Org');
    subscribeOrganization($org, 'professional');

    return [$org, $owner];
}

it('sorts and filters contact lists from the whitelist and refuses unknown sort fields', function () {
    [$org, $owner] = apiCloseoutOrg();
    app(CurrentOrganization::class)->set($org);
    Contact::create(['first_name' => 'Low', 'email' => 'low@x.com', 'lead_score' => 5, 'lifecycle_stage' => 'lead', 'lead_source' => 'organic']);
    Contact::create(['first_name' => 'High', 'email' => 'high@x.com', 'lead_score' => 90, 'lifecycle_stage' => 'mql', 'lead_source' => 'paid']);
    app(CurrentOrganization::class)->forget();

    Sanctum::actingAs($owner);
    $headers = ['X-Organization-Id' => (string) $org->id];

    $this->getJson('/api/v1/contacts?sort=-lead_score', $headers)
        ->assertOk()->assertJsonPath('data.0.first_name', 'High');
    $this->getJson('/api/v1/contacts?sort=lead_score', $headers)
        ->assertOk()->assertJsonPath('data.0.first_name', 'Low');
    $this->getJson('/api/v1/contacts?lifecycle_stage=mql', $headers)
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.lifecycle_stage', 'mql');
    $this->getJson('/api/v1/contacts?lead_source=organic', $headers)
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.first_name', 'Low');

    // Unknown sort fields fail validation — never leaked into ORDER BY.
    $this->getJson('/api/v1/contacts?sort=email;drop', $headers)->assertStatus(422);
    $this->getJson('/api/v1/contacts?sort=organization_id', $headers)->assertStatus(422);
});

it('updates a contact with duplicate-email protection, permission-gated', function () {
    [$org, $owner] = apiCloseoutOrg();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'Ada', 'email' => 'ada@x.com']);
    Contact::create(['first_name' => 'Taken', 'email' => 'taken@x.com']);
    app(CurrentOrganization::class)->forget();

    Sanctum::actingAs($owner);
    $headers = ['X-Organization-Id' => (string) $org->id];

    $this->patchJson("/api/v1/contacts/{$contact->id}", ['title' => 'CTO', 'lifecycle_stage' => 'sql'], $headers)
        ->assertOk()
        ->assertJsonPath('data.title', 'CTO')
        ->assertJsonPath('data.lifecycle_stage', 'sql');

    $this->patchJson("/api/v1/contacts/{$contact->id}", ['email' => 'taken@x.com'], $headers)->assertStatus(422);

    // A viewer token can read but never write.
    [$orgB] = makeOrganization('API Viewer Org');
    subscribeOrganization($orgB, 'professional');
    $viewer = addMember($orgB, Role::Viewer);
    app(CurrentOrganization::class)->set($orgB);
    $foreign = Contact::create(['first_name' => 'B', 'email' => 'b@other.com']);
    app(CurrentOrganization::class)->forget();

    Sanctum::actingAs($viewer);
    $this->patchJson("/api/v1/contacts/{$foreign->id}", ['title' => 'X'], ['X-Organization-Id' => (string) $orgB->id])
        ->assertForbidden();

    // Cross-tenant: org B's token cannot see org A's contact at all.
    $this->getJson("/api/v1/contacts/{$contact->id}", ['X-Organization-Id' => (string) $orgB->id])->assertNotFound();
});

it('creates and updates companies through the API', function () {
    [$org, $owner] = apiCloseoutOrg();
    Sanctum::actingAs($owner);
    $headers = ['X-Organization-Id' => (string) $org->id];

    $created = $this->postJson('/api/v1/companies', [
        'name' => 'Mfg Co', 'domain' => 'mfg.example', 'industry' => 'Manufacturing',
    ], $headers)->assertCreated()->assertJsonPath('data.name', 'Mfg Co')->json('data');

    $this->patchJson("/api/v1/companies/{$created['id']}", ['industry' => 'Industrial'], $headers)
        ->assertOk()->assertJsonPath('data.industry', 'Industrial');

    // Filter + sort on the list.
    $this->postJson('/api/v1/companies', ['name' => 'Alpha LLC', 'industry' => 'Legal'], $headers)->assertCreated();
    $this->getJson('/api/v1/companies?industry=Industrial', $headers)
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Mfg Co');
    $this->getJson('/api/v1/companies?sort=name', $headers)
        ->assertOk()->assertJsonPath('data.0.name', 'Alpha LLC');

    app(CurrentOrganization::class)->set($org);
    expect(Company::count())->toBe(2);
    app(CurrentOrganization::class)->forget();
});

it('creates and updates deals with pipeline-safe stages', function () {
    [$org, $owner] = apiCloseoutOrg();
    Sanctum::actingAs($owner);
    $headers = ['X-Organization-Id' => (string) $org->id];

    $created = $this->postJson('/api/v1/deals', ['name' => 'MSP switch', 'value' => 2400], $headers)
        ->assertCreated()->assertJsonPath('data.name', 'MSP switch')->json('data');
    expect($created['stage'])->not->toBeNull(); // defaulted to the first open stage

    app(CurrentOrganization::class)->set($org);
    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    // An open stage that is NOT the default the API just picked, so the
    // stage filter below separates the two deals deterministically.
    $defaultStageId = Deal::findOrFail($created['id'])->stage_id;
    $nextStage = $pipeline->stages()->where('is_won', false)->whereKeyNot($defaultStageId)->firstOrFail();

    // A stage from a different pipeline is refused.
    $rogue = Pipeline::create(['name' => 'Rogue', 'is_default' => false]);
    $rogueStage = $rogue->stages()->create(['name' => 'Elsewhere', 'sort_order' => 0]);
    app(CurrentOrganization::class)->forget();

    $this->patchJson("/api/v1/deals/{$created['id']}", ['stage_id' => $rogueStage->id], $headers)->assertStatus(422);
    $this->patchJson("/api/v1/deals/{$created['id']}", ['stage_id' => $nextStage->id, 'value' => 3600], $headers)
        ->assertOk()->assertJsonPath('data.value', 3600)->assertJsonPath('data.stage', $nextStage->name);

    // List filters + sort.
    $this->postJson('/api/v1/deals', ['name' => 'Small', 'value' => 100], $headers)->assertCreated();
    $this->getJson('/api/v1/deals?sort=-value', $headers)->assertOk()->assertJsonPath('data.0.name', 'MSP switch');
    $this->getJson("/api/v1/deals?stage_id={$nextStage->id}", $headers)
        ->assertOk()->assertJsonPath('meta.total', 1);

    app(CurrentOrganization::class)->set($org);
    expect(Deal::count())->toBe(2);
    app(CurrentOrganization::class)->forget();
});
