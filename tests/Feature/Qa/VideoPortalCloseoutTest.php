<?php

declare(strict_types=1);

/**
 * Video Marketing + Client Portal close-out (Phase 42 — VID-001/014/015/016/
 * 017, PORTAL-003/012/013/014/015).
 *
 * The video strategy report from real records, YouTube video-ad drafts and
 * video retargeting, personalized sales video + the campaign video block over
 * the real send pipeline, and the portal's campaign status, roadmap, meeting
 * notes, file visibility flags and PDF report.
 */

use App\Authorization\Role;
use App\Messaging\Contracts\MailProvider;
use App\Messaging\EmailMessage;
use App\Messaging\SentResult;
use App\Models\Activity;
use App\Models\AdCampaign;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\File;
use App\Models\MarketingList;
use App\Models\OutboundMessage;
use App\Models\RetargetingAudience;
use App\Models\StrategyItem;
use App\Services\Content\VideoStrategy;
use App\Services\Marketing\CampaignService;
use App\Services\Marketing\ListService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('VidPortal Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('computes the video strategy report from real records, citing its numbers', function () {
    // Below the floor: no recommendations, said plainly.
    ContentPiece::create(['title' => 'Lone video', 'slug' => 'v1', 'content_type' => 'video', 'status' => 'draft', 'funnel_stage' => 'tof']);
    expect(app(VideoStrategy::class)->report()['sufficient'])->toBeFalse();

    // Old library, nothing recent, no BOF, half unpublished.
    ContentPiece::forceCreate(['organization_id' => $this->org->id, 'title' => 'Webinar TOF', 'slug' => 'v2', 'content_type' => 'webinar', 'status' => 'published', 'funnel_stage' => 'tof', 'created_at' => now()->subDays(200)]);
    ContentPiece::forceCreate(['organization_id' => $this->org->id, 'title' => 'Interview MOF', 'slug' => 'v3', 'content_type' => 'interview', 'status' => 'draft', 'funnel_stage' => 'mof', 'created_at' => now()->subDays(180)]);
    ContentPiece::forceCreate(['organization_id' => $this->org->id, 'title' => 'Old video', 'slug' => 'v4', 'content_type' => 'video', 'status' => 'draft', 'funnel_stage' => 'tof', 'created_at' => now()->subDays(150)]);

    $report = app(VideoStrategy::class)->report();
    $recs = implode(' ', $report['recommendations']);

    expect($report['pieces'])->toBe(4)
        ->and($report['funnel']['bof'])->toBe(0)
        ->and($recs)->toContain('0 BOF')
        ->and($recs)->toContain('1 of 4 video pieces are published');

    // The content page carries it.
    $props = $this->actingAs($this->owner)->get(route('content.pieces.index'))->assertOk()->viewData('page')['props'];
    expect($props['video_strategy']['pieces'])->toBe(4);
});

it('drafts YouTube video ads from video pieces and attaches retargeting audiences', function () {
    $video = ContentPiece::create(['title' => 'Plant-floor IT in 4 minutes', 'slug' => 'yt1', 'content_type' => 'video', 'status' => 'published', 'excerpt' => 'Uptime for manufacturers.', 'url' => 'https://example.com/videos/plant-floor']);
    $article = ContentPiece::create(['title' => 'Not a video', 'slug' => 'yt2', 'content_type' => 'article', 'status' => 'published']);

    // Only video-shaped content becomes a video ad.
    $this->actingAs($this->owner)->from(route('content.pieces.show', $article))
        ->post(route('ads.youtube.promote-content'), ['content_piece_id' => $article->id])
        ->assertRedirect()->assertSessionHasErrors('content_piece_id');

    $this->actingAs($this->owner)->post(route('ads.youtube.promote-content'), ['content_piece_id' => $video->id])->assertRedirect();
    $campaign = AdCampaign::where('platform', 'youtube')->firstOrFail();
    $ad = $campaign->groups()->firstOrFail()->ads()->firstOrFail();
    expect($campaign->type)->toBe('video_ad')
        ->and($campaign->status)->toBe('draft')
        ->and($ad->body)->toContain('Upload the video in YouTube Studio');

    // Idempotent.
    $this->actingAs($this->owner)->post(route('ads.youtube.promote-content'), ['content_piece_id' => $video->id])->assertRedirect();
    expect(AdCampaign::where('platform', 'youtube')->count())->toBe(1);

    // VID-015: the audience rides Google Ads Customer Match.
    $audience = RetargetingAudience::create(['name' => 'Video viewers', 'source' => 'rules', 'member_count' => 18]);
    $this->actingAs($this->owner)->post(route('ads.campaigns.audience', $campaign), ['audience_id' => $audience->id])->assertRedirect();
    expect($campaign->refresh()->targeting['audience_id'])->toBe($audience->id);
});

it('sends personalized sales videos and campaign video blocks through the tracked pipeline', function () {
    // VID-016: the personalized sales video.
    $contact = Contact::create(['first_name' => 'Vee', 'email' => 'vee@x.com', 'email_opt_in' => true]);
    $this->actingAs($this->owner)->post(route('crm.contacts.video-message', $contact), [
        'video_url' => 'https://videos.example/for-vee', 'subject' => 'Made this for you', 'message' => 'Hi Vee — 90 seconds on your backup gaps.',
    ])->assertRedirect();

    $message = OutboundMessage::where('source', 'sales_video')->firstOrFail();
    expect($message->status)->toBe('sent')
        ->and(Activity::where('title', 'Sales video sent')->exists())->toBeTrue();

    // http refused.
    $this->actingAs($this->owner)->from(route('crm.contacts.show', $contact))
        ->post(route('crm.contacts.video-message', $contact), ['video_url' => 'http://x.example/v', 'subject' => 's', 'message' => 'm'])
        ->assertRedirect()->assertSessionHasErrors('video_url');

    // VID-017: the campaign video block, click-tracked.
    $capture = new class implements MailProvider
    {
        /** @var list<EmailMessage> */
        public array $sent = [];

        public function send(EmailMessage $message): SentResult
        {
            $this->sent[] = $message;

            return SentResult::accepted('fake-'.count($this->sent));
        }
    };
    app()->instance(MailProvider::class, $capture);

    $list = MarketingList::create(['name' => 'Video list', 'type' => 'static']);
    app(ListService::class)->addContact($list, $contact);
    $campaign = Campaign::create(['name' => 'Video push', 'channel' => 'email', 'subject' => 'Watch', 'body_html' => '<p>Intro</p>', 'marketing_list_id' => $list->id, 'status' => 'draft', 'video_url' => 'https://videos.example/launch', 'video_title' => 'Our 2-minute launch tour']);
    app(CampaignService::class)->send($campaign);

    expect($capture->sent)->toHaveCount(1)
        ->and($capture->sent[0]->html)->toContain('Our 2-minute launch tour')
        ->and($capture->sent[0]->html)->toContain('/e/c/')       // the watch link rides the click tracker
        ->and($capture->sent[0]->html)->not->toContain('href="https://videos.example/launch"');
});

it('surfaces campaign status, roadmap and meeting notes in the portal, visibility-gated', function () {
    Campaign::create(['name' => 'Q4 nurture', 'channel' => 'email', 'status' => 'sent', 'stat_sent' => 120, 'stat_opened' => 60, 'stat_clicked' => 12, 'sent_at' => now()]);
    StrategyItem::create(['type' => 'roadmap', 'title' => 'Launch healthcare vertical', 'status' => 'in_progress', 'priority' => 'high', 'due_on' => now()->addMonth()]);
    StrategyItem::create(['type' => 'audit', 'title' => 'Internal only', 'status' => 'open']);

    $contact = Contact::create(['first_name' => 'C', 'email' => 'c@x.com']);
    Activity::create(['subject_type' => 'contact', 'subject_id' => $contact->id, 'type' => 'meeting', 'title' => 'Kickoff', 'body' => 'Agreed on the Q4 plan.', 'occurred_at' => now(), 'client_visible' => true]);
    Activity::create(['subject_type' => 'contact', 'subject_id' => $contact->id, 'type' => 'meeting', 'title' => 'Internal sync', 'body' => 'Pricing discussion.', 'occurred_at' => now()]);

    $client = addMember($this->org, Role::Client);
    $props = $this->actingAs($client)->get(route('portal.dashboard'))->assertOk()->viewData('page')['props'];

    expect($props['campaigns'][0]['name'])->toBe('Q4 nurture')
        ->and($props['campaigns'][0]['sent'])->toBe(120)
        ->and(collect($props['roadmap'])->pluck('title'))->toContain('Launch healthcare vertical')
        ->and(collect($props['roadmap'])->pluck('title'))->not->toContain('Internal only')
        ->and(collect($props['meeting_notes'])->pluck('title'))->toContain('Kickoff')
        ->and(collect($props['meeting_notes'])->pluck('title'))->not->toContain('Internal sync');

    // The visibility toggle drives the gate (PORTAL-014).
    $meeting = Activity::where('title', 'Internal sync')->firstOrFail();
    $this->actingAs($this->owner)->patch(route('crm.activities.visibility', $meeting))->assertRedirect();
    $props = $this->actingAs($client)->get(route('portal.dashboard'))->assertOk()->viewData('page')['props'];
    expect(collect($props['meeting_notes'])->pluck('title'))->toContain('Internal sync');
});

it('gates portal files on the client-visible flag and serves the PDF report', function () {
    File::create(['uploaded_by' => $this->owner->id, 'disk' => 'local', 'path' => 'a/internal.pdf', 'name' => 'internal.pdf', 'mime' => 'application/pdf', 'size' => 100]);
    $shared = File::create(['uploaded_by' => $this->owner->id, 'disk' => 'local', 'path' => 'a/report.pdf', 'name' => 'monthly-report.pdf', 'mime' => 'application/pdf', 'size' => 100]);

    // Flip the flag through the endpoint (PORTAL-012).
    $this->actingAs($this->owner)->patch(route('files.visibility', $shared))->assertRedirect();

    $client = addMember($this->org, Role::Client);
    $files = collect($this->actingAs($client)->get(route('portal.support'))->assertOk()->viewData('page')['props']['files']);
    expect($files->pluck('name'))->toContain('monthly-report.pdf')
        ->and($files->pluck('name'))->not->toContain('internal.pdf');

    // PORTAL-013: the PDF report streams for the client.
    $response = $this->actingAs($client)->get(route('portal.report'));
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf');
});

it('notifies client users when the monthly report is ready', function () {
    $client = addMember($this->org, Role::Client);
    app(CurrentOrganization::class)->forget();

    $this->artisan('reports:portal-monthly')->assertSuccessful();

    app(CurrentOrganization::class)->set($this->org);
    $notification = $client->notifications()->latest()->first();
    expect($notification)->not->toBeNull()
        ->and($notification->data['title'])->toContain('performance report is ready')
        ->and($notification->data['url'])->toBe('/portal/report');

    // Owners are not clients — no notification for them.
    expect($this->owner->notifications()->count())->toBe(0);
});
