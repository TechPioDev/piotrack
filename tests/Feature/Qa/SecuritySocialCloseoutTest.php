<?php

declare(strict_types=1);

/**
 * Security + Social close-out (Phase 31 — SEC-003/005, SOC-006/018/019/027).
 *
 * First-party upload content scanning; field-level encryption evidence
 * re-pinned; social strategy computed from posting records; the paid-social
 * bridge from post to draft ad campaign; and social lead attribution through
 * the existing channel classifier.
 */

use App\Models\AdCampaign;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Deal;
use App\Models\File;
use App\Models\Integration;
use App\Models\Pipeline;
use App\Models\SocialPost;
use App\Models\Visitor;
use App\Security\UploadScanner;
use App\Services\Content\SocialStrategy;
use App\Support\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('SecSoc Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('scans upload content and refuses what contradicts its claimed type', function () {
    Storage::fake('local');
    app(CurrentOrganization::class)->forget();
    $this->actingAs($this->owner);

    // tmpfile()-backed fakes cannot be reopened by path on Windows, so the
    // payloads are written as ordinary files first — like real uploads are.
    $upload = function (string $name, string $content) {
        // storage_path, not sys_get_temp_dir(): Windows hands out an 8.3 short
        // path there, which finfo cannot open during mime validation.
        $path = storage_path('framework/scan-'.uniqid());
        file_put_contents($path, $content);

        return $this->post(route('files.store'), ['file' => new UploadedFile($path, $name, null, null, true)]);
    };

    // EICAR test signature, whatever the extension claims. Checked in-memory:
    // the host AV (Windows Defender here) blocks an EICAR file from existing
    // on disk at all, so an end-to-end fixture cannot carry this one payload.
    $eicar = 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
    expect(fn () => app(UploadScanner::class)->assertContentSafe($eicar, 'txt'))
        ->toThrow(ValidationException::class);

    // A PHP payload claiming to be an image.
    $upload('logo.png', '<?php system($_GET["c"]); ?>')->assertSessionHasErrors('file');

    // A Windows executable renamed to a document extension.
    $upload('invoice.pdf', "MZ\x90\x00binary")->assertSessionHasErrors('file');

    // Magic-byte mismatch: claims PNG, carries none of PNG's bytes.
    $upload('chart.png', 'just some text')->assertSessionHasErrors('file');

    // Clean files of the claimed types pass end-to-end (a real 1x1 PNG — the
    // mimes rule runs finfo on the whole structure, not just magic bytes).
    $onePixel = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
    $upload('chart.png', $onePixel)->assertSessionHasNoErrors();
    $upload('doc.pdf', '%PDF-1.4 content')->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($this->org);
    expect(File::count())->toBe(2); // only the clean two were stored
});

it('stores sensitive fields encrypted at rest and decrypts them transparently', function () {
    $integration = Integration::create([
        'provider' => 'google_ads', 'name' => 'Ads', 'status' => 'connected',
        'credentials' => ['api_key' => 'super-secret-key-123'],
    ]);
    $this->owner->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    // The raw rows never contain the plain values.
    $rawCredentials = (string) DB::table('integrations')->where('id', $integration->id)->value('credentials');
    $rawSecret = (string) DB::table('users')->where('id', $this->owner->id)->value('two_factor_secret');
    expect($rawCredentials)->not->toContain('super-secret-key-123')
        ->and($rawSecret)->not->toContain('JBSWY3DPEHPK3PXP');

    // The models decrypt transparently.
    expect($integration->refresh()->credentials['api_key'])->toBe('super-secret-key-123')
        ->and($this->owner->refresh()->two_factor_secret)->toBe('JBSWY3DPEHPK3PXP');
});

it('computes the social strategy from posting records with recommendations that cite numbers', function () {
    // LinkedIn active; the other networks silent with nothing scheduled.
    foreach (range(1, 5) as $i) {
        SocialPost::create(['channel' => 'linkedin', 'type' => $i <= 3 ? 'promo' : null, 'body' => "P{$i}", 'status' => 'published', 'published_at' => now()->subDays($i)]);
    }
    ContentPiece::create(['title' => 'Webinar', 'slug' => 'ss-webinar', 'content_type' => 'webinar', 'status' => 'published']);

    $report = app(SocialStrategy::class)->report();
    $byNetwork = collect($report['networks'])->keyBy('network');

    expect($byNetwork['linkedin']['published_30d'])->toBe(5)
        ->and($byNetwork['linkedin']['per_week'])->toBe(1.2)
        ->and($byNetwork['facebook']['published_30d'])->toBe(0)
        ->and($report['mix'])->toBe(['promo' => 3, 'post' => 2]);

    $recommendations = collect($report['recommendations']);
    // Silent networks called out, single-network concentration called out,
    // multimedia with no clips called out — each with its number.
    expect($recommendations->first(fn ($r) => str_contains($r['evidence'], 'Facebook has never')))->not->toBeNull()
        ->and($recommendations->first(fn ($r) => str_contains($r['evidence'], 'All 5 posts')))->not->toBeNull()
        ->and($recommendations->first(fn ($r) => str_contains($r['evidence'], '1 published multimedia piece(s) and 0 clips')))->not->toBeNull();
});

it('bridges a post to a draft ad campaign on its network, refusing networks without an ads platform', function () {
    $linkedin = SocialPost::create(['channel' => 'linkedin', 'body' => 'Our CMMC guide is live', 'status' => 'published', 'published_at' => now()]);
    $facebook = SocialPost::create(['channel' => 'facebook', 'body' => 'Meet the team', 'status' => 'published', 'published_at' => now()]);
    $x = SocialPost::create(['channel' => 'x', 'body' => 'Short take', 'status' => 'published', 'published_at' => now()]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->post(route('content.social.sponsor', $linkedin->id))->assertRedirect()->assertSessionHas('status');
    $this->actingAs($this->owner)->post(route('content.social.sponsor', $facebook->id))->assertRedirect()->assertSessionHas('status');
    $this->actingAs($this->owner)->post(route('content.social.sponsor', $x->id))->assertSessionHasErrors('post');

    // Idempotent: sponsoring twice reuses the campaign.
    $this->actingAs($this->owner)->post(route('content.social.sponsor', $linkedin->id))->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    $campaigns = AdCampaign::where('type', 'sponsored_post')->get();
    expect($campaigns)->toHaveCount(2)
        ->and($campaigns->firstWhere('platform', 'linkedin')->status)->toBe('draft')
        ->and($campaigns->firstWhere('platform', 'linkedin')->targeting['social_post_id'])->toBe($linkedin->id)
        ->and($campaigns->firstWhere('platform', 'meta'))->not->toBeNull();
});

it('attributes social visitors, leads and wins per network through the channel classifier', function () {
    // Two LinkedIn visitors (one identified), one paid visitor that must NOT count.
    $lead = Contact::create(['first_name' => 'Li', 'email' => 'li@x.com', 'lead_source' => 'linkedin', 'lifecycle_stage' => 'customer']);
    Visitor::create(['visitor_key' => 'socvis01', 'first_seen_at' => now(), 'visits' => 1, 'utm_source' => 'linkedin', 'utm_medium' => 'social', 'contact_id' => $lead->id]);
    Visitor::create(['visitor_key' => 'socvis02', 'first_seen_at' => now(), 'visits' => 1, 'utm_source' => 'linkedin', 'utm_medium' => 'social']);
    Visitor::create(['visitor_key' => 'socvis03', 'first_seen_at' => now(), 'visits' => 1, 'utm_source' => 'google', 'utm_medium' => 'cpc']);

    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $won = $pipeline->stages()->where('is_won', true)->firstOrFail();
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $won->id, 'name' => 'Li win', 'value' => 240000, 'status' => 'won', 'contact_id' => $lead->id]);

    $rows = collect(app(SocialStrategy::class)->attribution());

    $linkedin = $rows->firstWhere('network', 'linkedin');
    expect($linkedin['visitors'])->toBe(2)
        ->and($linkedin['leads'])->toBe(1)
        ->and($linkedin['customers'])->toBe(1)
        ->and($linkedin['won_revenue'])->toBe(240000)
        ->and($rows->firstWhere('network', 'google'))->toBeNull(); // paid, not social
});
