<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Competitor;
use App\Models\CompetitorSnapshot;
use App\Services\Analytics\CompetitiveService;
use App\Services\Analytics\CompetitorContentMonitor;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompetitorController extends Controller
{
    public function index(CompetitiveService $competitive): Response
    {
        $latestSnapshots = CompetitorSnapshot::whereIn(
            'id',
            CompetitorSnapshot::selectRaw('max(id)')->groupBy('competitor_id'),
        )->get()->keyBy('competitor_id');

        return Inertia::render('analytics/competitors', [
            'competitors' => Competitor::latest('id')->get()->map(function (Competitor $c) use ($latestSnapshots) {
                $snapshot = $latestSnapshots->get($c->id);

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'domain' => $c->domain,
                    'notes' => $c->notes,
                    'is_tracked' => $c->is_tracked,
                    // CINT-005: the latest content capture and what changed in it.
                    'content' => $snapshot === null ? null : [
                        'pages' => $snapshot->pages_count,
                        'new' => $snapshot->new_pages ?? [],
                        'changed' => $snapshot->changed_pages ?? [],
                        'removed' => $snapshot->removed_pages ?? [],
                        'checked_at' => $snapshot->created_at?->toIso8601String(),
                    ],
                ];
            }),
            'share_of_voice' => $competitive->shareOfVoice(),
            // CINT-001/011: keyword head-to-head and AI recommendation share.
            'headToHead' => $competitive->keywordHeadToHead(),
            'aiShare' => $competitive->aiRecommendationShare(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $competitor = Competitor::create($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'domain' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_tracked' => ['boolean'],
        ]));

        $audit->log('analytics.competitor.created', context: ['domain' => $competitor->domain], resourceType: 'competitor', resourceId: (string) $competitor->id);

        return back()->with('status', __('Competitor added.'));
    }

    public function update(Request $request, Competitor $competitor): RedirectResponse
    {
        $competitor->update($request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'domain' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_tracked' => ['boolean'],
        ]));

        return back()->with('status', __('Competitor updated.'));
    }

    /**
     * CINT-005: capture the competitor's public site content and diff it
     * against the previous capture. Guard refusals surface as validation
     * errors, never 500s.
     */
    public function checkContent(Competitor $competitor, CompetitorContentMonitor $monitor): RedirectResponse
    {
        try {
            $snapshot = $monitor->check($competitor);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['domain' => $e->getMessage()]);
        }

        return back()->with('status', __(':pages pages captured — :new new, :changed changed.', [
            'pages' => $snapshot->pages_count,
            'new' => count($snapshot->new_pages ?? []),
            'changed' => count($snapshot->changed_pages ?? []),
        ]));
    }

    public function destroy(Competitor $competitor, AuditLogger $audit): RedirectResponse
    {
        $competitor->delete();
        $audit->log('analytics.competitor.deleted', resourceType: 'competitor', resourceId: (string) $competitor->id);

        return back()->with('status', __('Competitor removed.'));
    }
}
