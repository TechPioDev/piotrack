<?php

declare(strict_types=1);

/**
 * Import/Export + Files close-out (Phase 21 — IMEX-003/004, FILE-002).
 *
 * Native .xlsx from the same guarded rows as the CSVs, a native one-page PDF
 * growth report, and documents attaching to CRM records, tickets and projects
 * with tenant-scoped target checks — all without a single new dependency.
 */

use App\Models\Company;
use App\Models\Contact;
use App\Models\File;
use App\Models\Ticket;
use App\Support\CurrentOrganization;
use App\Support\Xlsx;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Export Org');
    subscribeOrganization($this->org, 'enterprise');
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('exports a valid native .xlsx with guarded cells from the same rows as the CSV', function () {
    app(CurrentOrganization::class)->set($this->org);
    Company::create(['name' => 'Precision Manufacturing', 'industry' => 'Manufacturing']);
    Company::create(['name' => '=HYPERLINK("https://evil.example")', 'industry' => 'Injection']);
    app(CurrentOrganization::class)->forget();

    $response = $this->actingAs($this->owner)
        ->get(route('crm.companies.export').'?format=xlsx')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $binary = $response->streamedContent();
    expect(substr($binary, 0, 2))->toBe('PK'); // a real zip container

    // Crack the archive open and read the sheet.
    $tmp = tempnam(sys_get_temp_dir(), 'xt');
    file_put_contents($tmp, $binary);
    $zip = new ZipArchive;
    expect($zip->open($tmp))->toBeTrue();
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    @unlink($tmp);

    expect($sheet)->toContain('Precision Manufacturing')
        // The formula cell is defused with the OWASP apostrophe, same as CSV.
        ->and($sheet)->toContain("'=HYPERLINK");

    // The CSV path still serves the identical rows.
    $csv = $this->actingAs($this->owner)->get(route('crm.companies.export'))->streamedContent();
    expect($csv)->toContain('Precision Manufacturing')->and($csv)->toContain("'=HYPERLINK");
});

it('exports the growth report as a valid native PDF with real numbers', function () {
    app(CurrentOrganization::class)->set($this->org);
    Contact::create(['first_name' => 'Lead', 'email' => 'lead@x.test']);
    app(CurrentOrganization::class)->forget();

    $response = $this->actingAs($this->owner)
        ->get(route('analytics.report.pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $pdf = $response->streamedContent();
    expect(substr($pdf, 0, 8))->toBe('%PDF-1.4')
        ->and($pdf)->toContain('Growth Report')
        ->and($pdf)->toContain('New leads: 1')
        ->and($pdf)->toContain('Growth score:')
        ->and($pdf)->toContain('%%EOF');
});

it('attaches uploaded documents to CRM records and tickets, tenant-checked', function () {
    Storage::fake('local');

    app(CurrentOrganization::class)->set($this->org);
    $contact = Contact::create(['first_name' => 'Doc', 'email' => 'doc@x.test']);
    $ticket = Ticket::create(['subject' => 'Printer down', 'body' => 'It will not print.', 'status' => 'open', 'priority' => 'normal']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->post(route('files.store'), [
        'file' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
        'attachable_type' => 'contact',
        'attachable_id' => $contact->id,
    ])->assertRedirect();

    $this->actingAs($this->owner)->post(route('files.store'), [
        'file' => UploadedFile::fake()->create('screenshot.png', 50, 'image/png'),
        'attachable_type' => 'ticket',
        'attachable_id' => $ticket->id,
    ])->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    expect(File::where('attachable_type', Contact::class)->where('attachable_id', $contact->id)->exists())->toBeTrue()
        ->and(File::where('attachable_type', Ticket::class)->where('attachable_id', $ticket->id)->exists())->toBeTrue();
    app(CurrentOrganization::class)->forget();

    // A target in ANOTHER tenant is refused — never silently attached.
    [$otherOrg, $otherOwner] = makeOrganization('Other Org');
    app(CurrentOrganization::class)->set($otherOrg);
    $foreign = Contact::create(['first_name' => 'Foreign', 'email' => 'foreign@x.test']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->post(route('files.store'), [
        'file' => UploadedFile::fake()->create('sneaky.pdf', 10, 'application/pdf'),
        'attachable_type' => 'contact',
        'attachable_id' => $foreign->id,
    ])->assertStatus(422);
});

it('rejects unknown attachable types', function () {
    Storage::fake('local');

    $this->actingAs($this->owner)->post(route('files.store'), [
        'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        'attachable_type' => 'invoice',
        'attachable_id' => 1,
    ])->assertSessionHasErrors('attachable_type');
});

it('writes well-formed xlsx XML even for awkward values', function () {
    $binary = Xlsx::write([
        ['Name', 'Note'],
        ['A & B <Ltd>', "line\nbreak"],
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'xt');
    file_put_contents($tmp, $binary);
    $zip = new ZipArchive;
    $zip->open($tmp);
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    @unlink($tmp);

    expect($sheet)->toContain('A &amp; B &lt;Ltd&gt;');
    // The XML must actually parse.
    $doc = new DOMDocument;
    expect($doc->loadXML($sheet))->toBeTrue();
});
