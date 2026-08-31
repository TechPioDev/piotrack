<?php

declare(strict_types=1);

/**
 * Import/export breadth (Module 09): companies, leads and deals import through
 * the same pipeline contract as contacts (mapping, dedupe, preview, history,
 * error report), and every entity exports as streamed CSV with the
 * formula-injection guard on the way out.
 */

use App\Authorization\Role;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\ImportJob;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Support\CurrentOrganization;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Imex Org');
    subscribeOrganization($this->org, 'enterprise');
});

function csvUpload(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('import.csv', $content);
}

it('imports companies with dedupe against existing names', function () {
    app(CurrentOrganization::class)->set($this->org);
    Company::create(['name' => 'Existing Corp']);
    app(CurrentOrganization::class)->forget();

    $csv = "Company,Domain,Industry\nExisting Corp,existing.test,IT\nNew Manufacturing,newmfg.test,Manufacturing\n,missing.test,Nothing";

    $this->actingAs($this->owner)
        ->post(route('crm.entity.import.store', 'companies'), ['file' => csvUpload($csv)])
        ->assertRedirect(route('crm.companies.index'));

    $job = ImportJob::withoutGlobalScope('tenant')->where('resource', 'companies')->firstOrFail();
    expect($job->imported)->toBe(1)->and($job->skipped)->toBe(1)->and($job->failed)->toBe(1)
        ->and($job->errors[0]['error'])->toBe('Missing company name');
    expect(Company::withoutGlobalScope('tenant')->where('name', 'New Manufacturing')->value('industry'))->toBe('Manufacturing');
});

it('imports leads and previews before committing', function () {
    $csv = "First name,Email,Company,Source\nMichael,michael@precisionmfg.test,Precision Manufacturing,Website\nBadEmail,not-an-email,X,Referral";

    $preview = $this->actingAs($this->owner)
        ->post(route('crm.entity.import.preview', 'leads'), ['file' => csvUpload($csv)])
        ->assertRedirect()->assertSessionHas('import_preview');
    $data = session('import_preview');
    expect($data['valid'])->toBe(1)->and($data['invalid'])->toBe(1)
        ->and($data['sample'][0]['label'])->toBe('Michael');

    $this->actingAs($this->owner)->post(route('crm.entity.import.store', 'leads'), ['file' => csvUpload($csv)]);
    $lead = Lead::withoutGlobalScope('tenant')->where('email', 'michael@precisionmfg.test')->firstOrFail();
    expect($lead->status)->toBe('new')->and($lead->source)->toBe('Website');
});

it('imports deals with money in cents, contact linking and stage by name', function () {
    app(CurrentOrganization::class)->set($this->org);
    $contact = Contact::create(['first_name' => 'Dana', 'email' => 'dana@clinic.test']);
    app(CurrentOrganization::class)->forget();

    $csv = "Deal name,Value,MRR,Contact email,Stage\nManaged Cyber + CMMC,54000,4500,dana@clinic.test,Proposal\nNo Numbers,abc,,,";

    $this->actingAs($this->owner)->post(route('crm.entity.import.store', 'deals'), ['file' => csvUpload($csv)]);

    $deal = Deal::withoutGlobalScope('tenant')->where('name', 'Managed Cyber + CMMC')->firstOrFail();
    expect((int) $deal->value)->toBe(5400000)
        ->and((int) $deal->mrr)->toBe(450000)
        ->and((int) $deal->contact_id)->toBe((int) $contact->id)
        ->and($deal->stage?->name)->not->toBeNull()
        ->and($deal->status)->toBe('open');

    $job = ImportJob::withoutGlobalScope('tenant')->where('resource', 'deals')->firstOrFail();
    expect($job->failed)->toBe(1)->and($job->errors[0]['error'])->toBe('Deal value is not a number');
});

it('exports companies, leads and deals as guarded CSV', function () {
    app(CurrentOrganization::class)->set($this->org);
    Company::create(['name' => '=SUM(A1:A9)', 'domain' => 'evil.test']);
    Lead::create(['first_name' => 'Grace', 'email' => 'grace@x.test', 'source' => 'Referral']);
    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $pipeline->stages()->first()->id, 'name' => 'Big Deal', 'value' => 123400, 'status' => 'open']);
    app(CurrentOrganization::class)->forget();

    $companies = $this->actingAs($this->owner)->get(route('crm.companies.export'))->assertOk();
    // The formula-injection guard neutralises the leading "=".
    expect($companies->streamedContent())->toContain("'=SUM(A1:A9)")->toContain('evil.test');

    $leads = $this->actingAs($this->owner)->get(route('crm.leads.export'))->assertOk();
    expect($leads->streamedContent())->toContain('Grace')->toContain('Referral');

    $deals = $this->actingAs($this->owner)->get(route('crm.deals.export'))->assertOk();
    expect($deals->streamedContent())->toContain('Big Deal')->toContain('1234.00');
});

it('keeps imports permission-gated and tenant-scoped', function () {
    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)
        ->post(route('crm.entity.import.store', 'companies'), ['file' => csvUpload("Company\nX")])
        ->assertForbidden();

    // An unknown entity is not a route.
    $this->actingAs($this->owner)
        ->post('/crm/widgets/import', ['file' => csvUpload("Name\nX")])
        ->assertNotFound();

    $this->actingAs($this->owner)->post(route('crm.entity.import.store', 'companies'), ['file' => csvUpload("Company\nMine Inc")]);
    expect((int) Company::withoutGlobalScope('tenant')->where('name', 'Mine Inc')->value('organization_id'))->toBe((int) $this->org->id);
});
