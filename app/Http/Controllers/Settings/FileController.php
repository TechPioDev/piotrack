<?php

namespace App\Http\Controllers\Settings;

use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\File;
use App\Models\Project;
use App\Models\Ticket;
use App\Security\UploadScanner;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tenant-scoped file storage (FILE-001). File records use BelongsToTenant, so
 * listing and route-model binding are automatically scoped to the current
 * organization. Uploads are validated (mime allowlist + size cap) and stored
 * under a tenant-prefixed path.
 */
class FileController extends Controller
{
    private const MAX_KB = 10240; // 10 MB

    private const ALLOWED_MIMES = 'pdf,jpg,jpeg,png,gif,webp,svg,csv,txt,doc,docx,xls,xlsx,ppt,pptx';

    /** FILE-002: records a document can attach to, by short key. */
    public const ATTACHABLES = [
        'contact' => Contact::class,
        'deal' => Deal::class,
        'ticket' => Ticket::class,
        'project' => Project::class,
    ];

    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('settings/files', [
            'files' => File::with('uploader:id,name')
                ->latest('id')
                ->get()
                ->map(fn (File $f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'mime' => $f->mime,
                    'size' => $f->size,
                    'uploaded_by' => $f->uploader?->name,
                    'created_at' => $f->created_at,
                    // FILE-002: what this document is attached to, if anything.
                    'attached_to' => $f->attachable_type !== null
                        ? array_search($f->attachable_type, self::ATTACHABLES, true).' #'.$f->attachable_id
                        : null,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.self::MAX_KB, 'mimes:'.self::ALLOWED_MIMES],
            // FILE-002: optionally attach the document to a CRM record, ticket
            // or project on upload.
            'attachable_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::ATTACHABLES))],
            'attachable_id' => ['required_with:attachable_type', 'nullable', 'integer'],
        ]);

        $attachableClass = null;
        if (! empty($data['attachable_type'])) {
            $attachableClass = self::ATTACHABLES[$data['attachable_type']];
            // Tenant-scoped existence check: the target must be OUR record.
            abort_unless($attachableClass::whereKey($data['attachable_id'])->exists(), 422, __('That record does not exist.'));
        }

        $organizationId = $this->currentOrganization->id();
        $upload = $request->file('file');

        // ENTL-004: plan storage limit, size-aware (MB, rounded up).
        app(UsageMeter::class)->assertWithin(
            $this->currentOrganization->get(), Limit::StorageMb,
            additional: max(1, (int) ceil($upload->getSize() / 1_048_576)), errorKey: 'file',
        );

        // SEC-003: content scanning before anything touches storage.
        app(UploadScanner::class)->scan($upload);

        $path = $upload->store("org-{$organizationId}/files", 'local');

        $file = File::create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'name' => $upload->getClientOriginalName(),
            'mime' => $upload->getClientMimeType(),
            'size' => $upload->getSize(),
            'attachable_type' => $attachableClass,
            'attachable_id' => $attachableClass !== null ? (int) $data['attachable_id'] : null,
        ]);

        $this->audit->log(
            'file.uploaded',
            context: ['name' => $file->name, 'size' => $file->size],
            resourceType: 'file',
            resourceId: (string) $file->id,
            organizationId: $organizationId,
        );

        return back()->with('status', __('File uploaded.'));
    }

    public function download(File $file): StreamedResponse
    {
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }

    public function destroy(File $file): RedirectResponse
    {
        Storage::disk($file->disk)->delete($file->path);

        $this->audit->log(
            'file.deleted',
            context: ['name' => $file->name],
            resourceType: 'file',
            resourceId: (string) $file->id,
            organizationId: $file->organization_id,
        );

        $file->delete();

        return back();
    }
}
