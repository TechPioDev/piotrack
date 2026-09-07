<?php

namespace App\Http\Controllers\Seo;

use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Seo\SeoProviderManager;
use App\Services\Analytics\CompetitiveService;
use App\Services\Seo\KeywordService;
use App\Services\Seo\RankTracker;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KeywordController extends Controller
{
    public function __construct(
        private KeywordService $keywords,
        private RankTracker $ranks,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('seo/keywords/index', [
            // Stated plainly so fixture positions are never mistaken for real
            // rankings: the fixture driver derives a position from a hash.
            'rankSource' => [
                'name' => app(SeoProviderManager::class)->rankProviderName(),
                'live' => app(SeoProviderManager::class)->isRankLive(),
            ],

            'keywords' => Keyword::latest('id')->get()->map(fn (Keyword $k) => [
                'id' => $k->id,
                'phrase' => $k->phrase,
                'intent' => $k->intent,
                'type' => $k->type,
                'search_volume' => $k->search_volume,
                'difficulty' => $k->difficulty,
                'mapped_url' => $k->mapped_url,
                'cluster' => $k->cluster,
                'location' => $k->location,
                'is_tracked' => $k->is_tracked,
                'current_position' => $k->current_position,
                'page_one' => RankTracker::isPageOne($k->current_position),
                'top_three' => RankTracker::isTopThree($k->current_position),
            ]),
            'gap' => $this->keywords->contentGap()->map(fn (Keyword $k) => ['id' => $k->id, 'phrase' => $k->phrase]),
            // KSEO-008: the steal list — keywords where a tracked competitor
            // currently outranks us (or we do not rank at all).
            'steal' => collect(app(CompetitiveService::class)->keywordHeadToHead())
                ->filter(fn (array $row) => $row['leading'] === false)
                ->map(fn (array $row) => [
                    'keyword' => $row['keyword'],
                    'our_position' => $row['our_position'],
                    'best_competitor' => collect($row['competitors'])->filter()->sort()->keys()->first(),
                    'best_position' => collect($row['competitors'])->filter()->min(),
                ])->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        if (Keyword::where('phrase', $data['phrase'])->exists()) {
            return back()->withErrors(['phrase' => __('That keyword is already tracked.')]);
        }

        // ENTL-004: plan keyword limit.
        app(UsageMeter::class)->assertWithin(
            app(CurrentOrganization::class)->get(), Limit::Keywords, errorKey: 'phrase',
        );

        $keyword = Keyword::create($data);
        $this->audit->log('seo.keyword.created', context: ['phrase' => $keyword->phrase], resourceType: 'keyword', resourceId: (string) $keyword->id, organizationId: $keyword->organization_id);

        return back()->with('status', __('Keyword added.'));
    }

    public function update(Request $request, Keyword $keyword): RedirectResponse
    {
        $keyword->update($this->validateData($request, $keyword));

        return back()->with('status', __('Keyword updated.'));
    }

    /**
     * Seed the curated MSP research library (KSEO-001..012). Idempotent;
     * everything arrives untracked for review.
     */
    public function seed(Request $request): RedirectResponse
    {
        $data = $request->validate(['with_geo' => ['sometimes', 'boolean']]);

        $result = $this->keywords->seedMspLibrary((bool) ($data['with_geo'] ?? false));

        $this->audit->log('seo.keywords.library_seeded', context: $result);

        return back()->with('status', __(':created research keywords added (:skipped already present). Review and enable tracking per keyword.', $result));
    }

    public function rank(Request $request, Keyword $keyword): RedirectResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:120'],
        ]);

        $ranking = $this->ranks->check($keyword, $data['domain'], $data['location'] ?? null);

        return back()->with('status', $ranking->position !== null
            ? __('Ranked #:pos.', ['pos' => $ranking->position])
            : __('Not ranking in the top results.'));
    }

    public function recluster(): RedirectResponse
    {
        $count = $this->keywords->recluster();

        return back()->with('status', __('Clustered :n keywords.', ['n' => $count]));
    }

    public function destroy(Keyword $keyword): RedirectResponse
    {
        $keyword->delete();

        return back()->with('status', __('Keyword removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?Keyword $keyword = null): array
    {
        return $request->validate([
            'phrase' => ['required', 'string', 'max:200'],
            'intent' => ['required', Rule::in(['informational', 'commercial', 'transactional', 'navigational'])],
            'type' => ['nullable', 'string', 'max:40'],
            // Geo targeting (LSEO-002..005): any granularity — city, state,
            // service area or neighborhood — stored on the keyword and passed
            // to every rank check for that keyword.
            'location' => ['nullable', 'string', 'max:120'],
            'search_volume' => ['nullable', 'integer', 'min:0'],
            'difficulty' => ['nullable', 'integer', 'min:0', 'max:100'],
            'mapped_url' => ['nullable', 'url', 'max:2048'],
            'is_tracked' => ['sometimes', 'boolean'],
        ]);
    }
}
