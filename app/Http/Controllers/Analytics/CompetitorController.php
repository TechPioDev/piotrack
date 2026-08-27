<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Competitor;
use App\Services\Analytics\CompetitiveService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompetitorController extends Controller
{
    public function index(CompetitiveService $competitive): Response
    {
        return Inertia::render('analytics/competitors', [
            'competitors' => Competitor::latest('id')->get()->map(fn (Competitor $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'domain' => $c->domain,
                'notes' => $c->notes,
                'is_tracked' => $c->is_tracked,
            ]),
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

    public function destroy(Competitor $competitor, AuditLogger $audit): RedirectResponse
    {
        $competitor->delete();
        $audit->log('analytics.competitor.deleted', resourceType: 'competitor', resourceId: (string) $competitor->id);

        return back()->with('status', __('Competitor removed.'));
    }
}
