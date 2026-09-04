<?php

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\File;
use App\Support\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads a valid file scoped to the organization', function () {
    Storage::fake('local');
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)
        ->post(route('files.store'), ['file' => UploadedFile::fake()->createWithContent('brand.pdf', '%PDF-1.4 '.str_repeat('a', 2048))])
        ->assertRedirect();

    $file = File::withoutGlobalScope('tenant')->first();
    expect($file)->not->toBeNull()
        ->and($file->organization_id)->toBe($org->id)
        ->and($file->name)->toBe('brand.pdf');
    Storage::disk('local')->assertExists($file->path);
    expect(AuditLog::where('action', 'file.uploaded')->exists())->toBeTrue();
});

it('rejects a disallowed file type', function () {
    Storage::fake('local');
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)
        ->post(route('files.store'), ['file' => UploadedFile::fake()->create('malware.exe', 10)])
        ->assertSessionHasErrors('file');

    expect(File::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('rejects an oversize file', function () {
    Storage::fake('local');
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)
        ->post(route('files.store'), ['file' => UploadedFile::fake()->create('huge.pdf', 20000, 'application/pdf')])
        ->assertSessionHasErrors('file');
});

it('isolates files across tenants on download', function () {
    Storage::fake('local');
    [$orgA, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');

    app(CurrentOrganization::class)->set($orgB);
    $fileB = File::create([
        'organization_id' => $orgB->id, 'disk' => 'local', 'path' => 'org-b/x', 'name' => 'b.pdf', 'size' => 10,
    ]);
    app(CurrentOrganization::class)->forget();

    // Owner of A cannot resolve B's file (tenant-scoped binding → 404).
    $this->actingAs($ownerA)->get(route('files.download', $fileB->id))->assertNotFound();
});

it('deletes a file', function () {
    Storage::fake('local');
    [$org, $owner] = makeOrganization();
    $this->actingAs($owner)->post(route('files.store'), ['file' => UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4 '.str_repeat('b', 1024))]);
    $file = File::withoutGlobalScope('tenant')->first();

    $this->actingAs($owner)->delete(route('files.destroy', $file->id))->assertRedirect();

    expect(File::withoutGlobalScope('tenant')->find($file->id))->toBeNull();
    expect(AuditLog::where('action', 'file.deleted')->exists())->toBeTrue();
});

it('forbids a viewer from uploading files', function () {
    Storage::fake('local');
    [$org] = makeOrganization();
    $viewer = addMember($org, Role::Viewer); // files.view only, not files.manage

    $this->actingAs($viewer)
        ->post(route('files.store'), ['file' => UploadedFile::fake()->createWithContent('x.pdf', '%PDF-1.4 tiny')])
        ->assertForbidden();
});
