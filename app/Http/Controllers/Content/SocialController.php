<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\ContentPiece;
use App\Models\SocialPost;
use App\Services\Content\SocialService;
use App\Services\Content\SocialStrategy;
use App\Support\AuditLogger;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SocialController extends Controller
{
    private const CHANNELS = ['linkedin', 'facebook', 'x', 'youtube', 'other'];

    public function __construct(
        private SocialService $social,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('content/social/index', [
            'posts' => SocialPost::latest('id')->get()->map(fn (SocialPost $p) => [
                'id' => $p->id,
                'channel' => $p->channel,
                'type' => $p->type,
                'body' => $p->body,
                'status' => $p->status,
                'scheduled_at' => $p->scheduled_at?->toIso8601String(),
                'published_at' => $p->published_at?->toIso8601String(),
                'impressions' => $p->impressions,
                'likes' => $p->likes,
                'comments' => $p->comments,
                'shares' => $p->shares,
            ]),
            'channels' => self::CHANNELS,
            'pieces' => ContentPiece::orderBy('title')->get(['id', 'title'])
                ->map(fn ($p) => ['id' => $p->id, 'title' => $p->title]),
            // SOC-006: strategy computed from the tenant's own posting records.
            'strategy' => app(SocialStrategy::class)->report(),
            // SOC-027: social lead attribution through the channel classifier.
            'attribution' => app(SocialStrategy::class)->attribution(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $post = SocialPost::create($request->validate([
            'channel' => ['required', Rule::in(self::CHANNELS)],
            'type' => ['nullable', 'string', 'max:40'],
            'body' => ['required', 'string', 'max:5000'],
            'media_url' => ['nullable', 'url', 'max:2048'],
            'content_piece_id' => ['nullable', TenantExists::in('content_pieces')],
        ]));

        $this->audit->log('content.social.created', context: ['channel' => $post->channel], resourceType: 'social_post', resourceId: (string) $post->id, organizationId: $post->organization_id);

        return back()->with('status', __('Post created.'));
    }

    public function schedule(Request $request, SocialPost $post): RedirectResponse
    {
        $data = $request->validate(['scheduled_at' => ['required', 'date']]);
        $this->social->schedule($post, Carbon::parse($data['scheduled_at']));

        return back()->with('status', __('Post scheduled.'));
    }

    public function publish(SocialPost $post): RedirectResponse
    {
        $this->social->publish($post);

        return back()->with('status', __('Post published.'));
    }

    /**
     * SOC-018/019: boost a post — a draft ad campaign on the post's network's
     * ad platform, finished in the Ads module where budgets and metrics live.
     */
    public function sponsor(SocialPost $post): RedirectResponse
    {
        $platform = ['linkedin' => 'linkedin', 'facebook' => 'meta', 'youtube' => 'youtube'][$post->channel] ?? null;

        if ($platform === null) {
            return back()->withErrors(['post' => __('No ads platform is available for :channel — sponsored posts run on LinkedIn, Facebook and YouTube.', ['channel' => $post->channel])]);
        }

        // Idempotent: a post sponsors into one campaign, never a stack of them.
        $existing = AdCampaign::where('type', 'sponsored_post')
            ->where('targeting->social_post_id', $post->id)->first();

        $campaign = $existing ?? AdCampaign::create([
            'platform' => $platform,
            'name' => 'Sponsored: '.mb_substr((string) ($post->body ?: 'social post'), 0, 80),
            'type' => 'sponsored_post',
            'objective' => 'awareness',
            'status' => 'draft',
            'targeting' => ['social_post_id' => $post->id],
        ]);

        $this->audit->log('content.social.sponsored', context: ['post' => $post->id, 'campaign' => $campaign->id], resourceType: 'social_post', resourceId: (string) $post->id, organizationId: $post->organization_id);

        return back()->with('status', __('Draft campaign ":name" ready — set the budget under Ads → Campaigns.', ['name' => $campaign->name]));
    }

    public function refreshMetrics(SocialPost $post): RedirectResponse
    {
        $this->social->refreshMetrics($post);

        return back()->with('status', __('Metrics refreshed.'));
    }

    public function destroy(SocialPost $post): RedirectResponse
    {
        $post->delete();

        return back()->with('status', __('Post deleted.'));
    }
}
