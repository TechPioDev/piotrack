<?php

declare(strict_types=1);

/**
 * Revenue Attribution + Support close-out (Phase 33 — ATTR-006..011, SUPP-002).
 *
 * First-touch keyword/ad/landing-path capture on the pixel, won-revenue
 * rollups per dimension (visitor → identified contact → closed deals), form
 * and call attribution, and ticket attachments surfaced on the support screen.
 */

use App\Models\Call;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Deal;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Pipeline;
use App\Models\SitePage;
use App\Models\Ticket;
use App\Models\Visitor;
use App\Services\Analytics\AttributionService;
use App\Support\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Attr Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function wonDealFor(Contact $contact, int $value): void
{
    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $won = $pipeline->stages()->where('is_won', true)->firstOrFail();
    Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $won->id, 'name' => 'W'.uniqid(), 'value' => $value, 'status' => 'won', 'contact_id' => $contact->id]);
}

it('captures keyword, ad and landing path first-touch, set once across later visits', function () {
    $this->org->forceFill(['tracking_key' => 'tk_attrtest001'])->save();
    app(CurrentOrganization::class)->forget();

    $this->postJson(route('public.track.event', 'tk_attrtest001'), [
        'vid' => 'attrvisitor1', 'type' => 'pageview', 'path' => '/s/managed-it',
        'utm_source' => 'google', 'utm_medium' => 'cpc',
        'utm_term' => 'managed it services', 'utm_content' => 'ad-variant-a',
    ])->assertOk();

    // A later visit with different UTMs must never overwrite the first touch.
    $this->postJson(route('public.track.event', 'tk_attrtest001'), [
        'vid' => 'attrvisitor1', 'type' => 'pageview', 'path' => '/pricing',
        'utm_term' => 'different keyword', 'utm_content' => 'ad-variant-b',
    ])->assertOk();

    app(CurrentOrganization::class)->set($this->org);
    $visitor = Visitor::where('visitor_key', 'attrvisitor1')->firstOrFail();
    expect($visitor->utm_term)->toBe('managed it services')
        ->and($visitor->utm_content)->toBe('ad-variant-a')
        ->and($visitor->first_path)->toBe('/s/managed-it')
        ->and($visitor->last_path)->toBe('/pricing');
});

it('rolls won revenue up by keyword and ad, ignoring anonymous visitors and unwon contacts', function () {
    $won = Contact::create(['first_name' => 'Won', 'email' => 'won@x.com', 'lifecycle_stage' => 'customer']);
    $open = Contact::create(['first_name' => 'Open', 'email' => 'open@x.com', 'lifecycle_stage' => 'sql']);

    Visitor::create(['visitor_key' => 'kv1', 'first_seen_at' => now(), 'visits' => 1, 'contact_id' => $won->id, 'utm_term' => 'msp near me', 'utm_content' => 'headline-a', 'first_path' => '/s/managed-it']);
    Visitor::create(['visitor_key' => 'kv2', 'first_seen_at' => now(), 'visits' => 1, 'contact_id' => $open->id, 'utm_term' => 'msp near me', 'utm_content' => 'headline-b']);
    Visitor::create(['visitor_key' => 'kv3', 'first_seen_at' => now(), 'visits' => 1, 'utm_term' => 'anonymous keyword']); // never identified

    wonDealFor($won, 240000);

    $dimensions = app(AttributionService::class)->dimensionAttribution();

    $keyword = collect($dimensions['keywords'])->firstWhere('bucket', 'msp near me');
    expect($keyword['contacts'])->toBe(2)
        ->and($keyword['revenue'])->toBe(240000) // only the won contact's deal
        ->and(collect($dimensions['keywords'])->firstWhere('bucket', 'anonymous keyword'))->toBeNull()
        ->and(collect($dimensions['ads'])->firstWhere('bucket', 'headline-a')['revenue'])->toBe(240000)
        ->and(collect($dimensions['ads'])->firstWhere('bucket', 'headline-b')['revenue'])->toBe(0);
});

it('labels landing-page and content attribution with the page or piece the path belongs to', function () {
    SitePage::create(['type' => 'service', 'slug' => 'managed-it', 'title' => 'Managed IT Services', 'status' => SitePage::STATUS_PUBLISHED]);
    ContentPiece::create(['title' => 'CMMC Guide', 'slug' => 'cmmc-guide', 'content_type' => 'guide', 'status' => 'published', 'url' => 'https://example.test/resources/cmmc-guide']);

    $a = Contact::create(['first_name' => 'A', 'email' => 'a@x.com']);
    $b = Contact::create(['first_name' => 'B', 'email' => 'b@x.com']);
    $c = Contact::create(['first_name' => 'C', 'email' => 'c@x.com']);
    Visitor::create(['visitor_key' => 'cv1', 'first_seen_at' => now(), 'visits' => 1, 'contact_id' => $a->id, 'first_path' => '/s/managed-it']);
    Visitor::create(['visitor_key' => 'cv2', 'first_seen_at' => now(), 'visits' => 1, 'contact_id' => $b->id, 'first_path' => '/resources/cmmc-guide']);
    Visitor::create(['visitor_key' => 'cv3', 'first_seen_at' => now(), 'visits' => 1, 'contact_id' => $c->id, 'first_path' => '/some/unknown-path']);

    wonDealFor($a, 100000);
    wonDealFor($b, 50000);

    $dimensions = app(AttributionService::class)->dimensionAttribution();

    $content = collect($dimensions['content']);
    expect($content->firstWhere('bucket', 'Managed IT Services')['revenue'])->toBe(100000)
        ->and($content->firstWhere('bucket', 'CMMC Guide')['revenue'])->toBe(50000)
        ->and($content->firstWhere('bucket', '/some/unknown-path')['revenue'])->toBe(0); // raw, never guessed

    expect(collect($dimensions['landing_pages'])->firstWhere('bucket', '/s/managed-it')['revenue'])->toBe(100000);
});

it('attributes revenue to the capturing form and the first call source', function () {
    $form = Form::create(['name' => 'CMMC assessment form', 'slug' => 'attr-form-'.uniqid(), 'status' => 'published', 'fields' => []]);
    $contact = Contact::create(['first_name' => 'F', 'email' => 'f@x.com']);
    FormSubmission::create(['form_id' => $form->id, 'contact_id' => $contact->id, 'payload' => []]);
    wonDealFor($contact, 300000);

    $caller = Contact::create(['first_name' => 'CallLead', 'email' => 'call@x.com']);
    Call::create(['contact_id' => $caller->id, 'direction' => 'inbound', 'duration_seconds' => 300, 'status' => 'completed', 'source' => 'gbp', 'score' => 40, 'is_qualified' => true, 'occurred_at' => now()]);
    Call::create(['contact_id' => $caller->id, 'direction' => 'inbound', 'duration_seconds' => 100, 'status' => 'completed', 'source' => 'paid', 'score' => 10, 'is_qualified' => false, 'occurred_at' => now()]);
    wonDealFor($caller, 120000);

    $dimensions = app(AttributionService::class)->dimensionAttribution();

    expect(collect($dimensions['forms'])->firstWhere('bucket', 'CMMC assessment form')['revenue'])->toBe(300000)
        // The FIRST call's source wins; the later paid call attributes nothing.
        ->and(collect($dimensions['calls'])->firstWhere('bucket', 'gbp')['revenue'])->toBe(120000)
        ->and(collect($dimensions['calls'])->firstWhere('bucket', 'paid'))->toBeNull();
});

it('attaches documents to tickets and lists them on the support screen', function () {
    Storage::fake('local');
    $ticket = Ticket::create(['subject' => 'VPN down', 'body' => 'Cannot connect.', 'status' => 'open', 'priority' => 'high']);
    app(CurrentOrganization::class)->forget();

    $path = storage_path('framework/attach-'.uniqid());
    file_put_contents($path, '%PDF-1.4 diagnostic report');

    $this->actingAs($this->owner)->post(route('files.store'), [
        'file' => new UploadedFile($path, 'diagnostics.pdf', null, null, true),
        'attachable_type' => 'ticket',
        'attachable_id' => $ticket->id,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $props = $this->actingAs($this->owner)->get(route('support.index'))->assertOk()->viewData('page')['props'];
    $row = collect($props['tickets'])->firstWhere('id', $ticket->id);
    expect($row['attachments'])->toHaveCount(1)
        ->and($row['attachments'][0]['name'])->toBe('diagnostics.pdf');

    // A ticket in another tenant can never be the attach target (re-pinned).
    [$otherOrg] = makeOrganization('Other Attr Org');
    app(CurrentOrganization::class)->set($otherOrg);
    $foreign = Ticket::create(['subject' => 'Foreign', 'body' => 'x', 'status' => 'open', 'priority' => 'normal']);
    app(CurrentOrganization::class)->forget();

    file_put_contents($path2 = storage_path('framework/attach-'.uniqid()), '%PDF-1.4 x');
    $this->actingAs($this->owner)->post(route('files.store'), [
        'file' => new UploadedFile($path2, 'sneaky.pdf', null, null, true),
        'attachable_type' => 'ticket',
        'attachable_id' => $foreign->id,
    ])->assertStatus(422);

    app(CurrentOrganization::class)->set($this->org);
});
