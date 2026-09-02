<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\AuthorityAsset;
use App\Models\Review;
use App\Models\ReviewRequest;
use App\Services\Content\ReputationService;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReputationController extends Controller
{
    public function __construct(private ReputationService $reputation) {}

    public function index(): Response
    {
        return Inertia::render('content/reputation/index', [
            // Where the reviews came from, and what replying actually does.
            // No driver can post a reply back to a platform, so the screen must
            // not let "Responded" imply the public has seen it.
            'reviewSource' => [
                'name' => (string) config('content.review_provider', 'fixture'),
                'live' => (string) config('content.review_provider', 'fixture') !== 'fixture',
                'canPublishResponses' => false,
            ],
            'aggregate' => $this->reputation->aggregate(),
            'reviews' => Review::latest('id')->limit(100)->get()->map(fn (Review $r) => [
                'id' => $r->id,
                'source' => $r->source,
                'author_name' => $r->author_name,
                'rating' => $r->rating,
                'body' => $r->body,
                'video_url' => $r->video_url,
                'sentiment' => $r->sentiment,
                'responded' => $r->responded,
                'response' => $r->response,
            ]),
            'requests' => ReviewRequest::latest('id')->limit(50)->get()->map(fn (ReviewRequest $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'channel' => $r->channel,
                'status' => $r->status,
            ]),
            'assets' => AuthorityAsset::latest('id')->get()->map(fn (AuthorityAsset $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'name' => $a->name,
                'issuer' => $a->issuer,
                'url' => $a->url,
            ]),
            'assetTypes' => ReputationService::ASSET_TYPES,
            // REP-006/007: per-directory optimization checklists.
            'directoryChecklists' => $this->reputation->directoryChecklists(),
        ]);
    }

    public function storeReview(Request $request): RedirectResponse
    {
        $this->reputation->recordReview($request->validate([
            'source' => ['required', Rule::in(['google', 'clutch', 'g2', 'facebook', 'manual'])],
            'author_name' => ['nullable', 'string', 'max:120'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:5000'],
            'url' => ['nullable', 'url', 'max:2048'],
            // REP-005: a testimonial can carry its video link.
            'video_url' => ['nullable', 'url', 'max:2048'],
        ]));

        return back()->with('status', __('Review recorded.'));
    }

    public function respond(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['response' => ['required', 'string', 'max:5000']]);
        $this->reputation->respond($review, $data['response']);

        return back()->with('status', __('Response saved.'));
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(['google', 'clutch'])],
            'identifier' => ['required', 'string', 'max:200'],
        ]);

        $count = $this->reputation->import($data['source'], $data['identifier']);

        return back()->with('status', __('Imported :n reviews.', ['n' => $count]));
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_id' => ['nullable', TenantExists::active('contacts')],
            'channel' => ['required', Rule::in(['email', 'sms'])],
        ]);

        ReviewRequest::create($data);

        return back()->with('status', __('Review request created.'));
    }

    public function sendRequest(ReviewRequest $reviewRequest): RedirectResponse
    {
        $this->reputation->sendRequest($reviewRequest);

        return back()->with('status', __('Review request sent.'));
    }

    public function storeAsset(Request $request): RedirectResponse
    {
        AuthorityAsset::create($request->validate([
            'type' => ['required', Rule::in(ReputationService::ASSET_TYPES)],
            'name' => ['required', 'string', 'max:200'],
            'issuer' => ['nullable', 'string', 'max:200'],
            'url' => ['nullable', 'url', 'max:2048'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'achieved_on' => ['nullable', 'date'],
            // Directory profiles / placements carry structured details.
            'details' => ['nullable', 'array'],
            'details.description' => ['nullable', 'string', 'max:2000'],
            'details.services' => ['nullable', 'array', 'max:30'],
            'details.services.*' => ['string', 'max:120'],
            'details.review_count' => ['nullable', 'integer', 'min:0'],
        ]));

        return back()->with('status', __('Authority asset added.'));
    }

    /** REP-019: a landing-page draft assembled from real proof only. */
    public function createProofPage(): RedirectResponse
    {
        try {
            $page = $this->reputation->createProofPage();
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['proof' => $e->getMessage()]);
        }

        return back()->with('status', __('Proof page ":name" drafted — review it under landing pages.', ['name' => $page->name]));
    }

    public function destroyAsset(AuthorityAsset $asset): RedirectResponse
    {
        $asset->delete();

        return back()->with('status', __('Asset removed.'));
    }
}
