<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Services\EntityCsvImporter;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CSV import wizard for companies, leads and deals (IMEX-001) — the same
 * upload → preview → commit flow contacts already have, on the shared page.
 * {entity} is constrained in the route to the importer's known set.
 */
class EntityImportController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private EntityCsvImporter $importer,
    ) {}

    public function create(string $entity): Response
    {
        return Inertia::render('crm/import-entity', [
            'entity' => $entity,
            'history' => ImportJob::where('resource', $entity)
                ->latest('id')->limit(10)->get(['id', 'filename', 'imported', 'skipped', 'failed', 'created_at']),
            'preview' => session('import_preview'),
        ]);
    }

    public function preview(Request $request, string $entity): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $parsed = $this->importer->parse($entity, $request->file('file')->getRealPath());
        $analysis = $this->importer->analyze($entity, $this->currentOrganization->get(), $parsed['rows']);

        return back()->with('import_preview', [
            'mapping' => $parsed['mapping'],
            'filename' => $request->file('file')->getClientOriginalName(),
            ...$analysis,
        ]);
    }

    public function store(Request $request, string $entity): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $parsed = $this->importer->parse($entity, $request->file('file')->getRealPath());
        $job = $this->importer->import(
            $entity,
            $this->currentOrganization->get(),
            $request->user(),
            $request->file('file')->getClientOriginalName(),
            $parsed['rows'],
        );

        return redirect()->route($this->indexRoute($entity))
            ->with('status', __(':imported imported, :skipped skipped, :failed failed.', [
                'imported' => $job->imported,
                'skipped' => $job->skipped,
                'failed' => $job->failed,
            ]));
    }

    /** Where each entity's list lives — keywords and competitors sit outside CRM. */
    private function indexRoute(string $entity): string
    {
        return match ($entity) {
            'keywords' => 'seo.keywords.index',
            'competitors' => 'analytics.competitors.index',
            default => "crm.{$entity}.index",
        };
    }
}
