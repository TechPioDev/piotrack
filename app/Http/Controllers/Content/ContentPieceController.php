<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\ContentPiece;
use App\Models\Vertical;
use App\Services\Content\ContentService;
use App\Services\Content\MultimediaPromotion;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContentPieceController extends Controller
{
    private const TYPES = ['article', 'service_page', 'location_page', 'case_study', 'whitepaper', 'ebook', 'guide', 'checklist', 'pillar', 'video', 'podcast', 'webinar', 'interview'];

    private const STATUSES = ['idea', 'draft', 'in_review', 'approved', 'published', 'archived'];

    public function __construct(private ContentService $content) {}

    public function index(): Response
    {
        return Inertia::render('content/pieces/index', [
            'pieces' => ContentPiece::latest('id')->get()->map(fn (ContentPiece $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'content_type' => $p->content_type,
                'funnel_stage' => $p->funnel_stage,
                'status' => $p->status,
                'is_lead_magnet' => $p->is_lead_magnet,
                'optimization_score' => $p->optimization_score,
            ]),
            'types' => self::TYPES,
            'verticals' => Vertical::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(ContentPiece $piece): Response
    {
        return Inertia::render('content/pieces/show', [
            'piece' => [
                'id' => $piece->id,
                'title' => $piece->title,
                'content_type' => $piece->content_type,
                'funnel_stage' => $piece->funnel_stage,
                'status' => $piece->status,
                'excerpt' => $piece->excerpt,
                'body' => $piece->body,
                'target_keyword' => $piece->target_keyword,
                'url' => $piece->url,
                'cta' => $piece->cta,
                'is_lead_magnet' => $piece->is_lead_magnet,
                'optimization_score' => $piece->optimization_score,
                'published_at' => $piece->published_at?->toIso8601String(),
            ],
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $piece = $this->content->create($this->validateData($request));

        return redirect()->route('content.pieces.show', $piece->id)->with('status', __('Content created.'));
    }

    public function update(Request $request, ContentPiece $piece): RedirectResponse
    {
        $this->content->update($piece, $this->validateData($request));

        return back()->with('status', __('Content saved.'));
    }

    public function status(Request $request, ContentPiece $piece): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(self::STATUSES)]]);
        $this->content->transition($piece, $data['status']);

        return back()->with('status', __('Moved to :status.', ['status' => $data['status']]));
    }

    /**
     * POD-004: schedule one announcement post per network for a multimedia piece.
     */
    public function promote(ContentPiece $piece, MultimediaPromotion $promotion): RedirectResponse
    {
        try {
            $posts = $promotion->promote($piece);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['piece' => $e->getMessage()]);
        }

        return back()->with('status', __(':n announcement posts scheduled — edit them under Content → Social.', ['n' => count($posts)]));
    }

    /** POD-009: a staggered clip schedule from a long-form piece. */
    public function clips(Request $request, ContentPiece $piece, MultimediaPromotion $promotion): RedirectResponse
    {
        $data = $request->validate(['count' => ['nullable', 'integer', 'min:1', 'max:10']]);

        try {
            $posts = $promotion->clips($piece, (int) ($data['count'] ?? 3));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['piece' => $e->getMessage()]);
        }

        return back()->with('status', __(':n clip slots scheduled — attach each cut under Content → Social before it goes out.', ['n' => count($posts)]));
    }

    public function destroy(ContentPiece $piece): RedirectResponse
    {
        $piece->delete();

        return redirect()->route('content.pieces.index')->with('status', __('Content deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'content_type' => ['required', Rule::in(self::TYPES)],
            'format' => ['nullable', 'string', 'max:40'],
            'funnel_stage' => ['nullable', Rule::in(['tof', 'mof', 'bof'])],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string'],
            'target_keyword' => ['nullable', 'string', 'max:200'],
            'url' => ['nullable', 'url', 'max:2048'],
            'cta' => ['nullable', 'string', 'max:200'],
            'is_lead_magnet' => ['boolean'],
            // MLOC-008: region-specific content targets one branch; null = central.
            'seo_location_id' => ['nullable', 'integer', TenantExists::in('seo_locations')],
            // VERT-014/017: the piece targets one vertical; coverage joins on it.
            'vertical_id' => ['nullable', 'integer', TenantExists::in('verticals')],
        ]);
    }
}
