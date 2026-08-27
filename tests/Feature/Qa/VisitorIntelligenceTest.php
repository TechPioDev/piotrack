<?php

declare(strict_types=1);

/**
 * Visitor Intelligence (Module 06). The pixel is a public, keyed, throttled
 * surface: ingestion must be tenant-sealed, sessions honestly counted,
 * first-touch attribution immutable, and intent signals only ever attributed
 * to a KNOWN contact — anonymous heat stays on the visitor row.
 */

use App\Models\Contact;
use App\Models\Form;
use App\Models\IntentSignal;
use App\Models\Visitor;
use App\Support\CurrentOrganization;
use Illuminate\Support\Str;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Visitor Org');
    subscribeOrganization($this->org, 'enterprise');
    $this->org->forceFill(['tracking_key' => 'tk_'.Str::lower(Str::random(24))])->save();
});

function track($test, array $payload, ?string $key = null)
{
    return $test->postJson('/t/'.($key ?? $test->org->tracking_key).'/e', array_merge(['vid' => 'abcdef1234567890'], $payload));
}

it('serves the tracker script and rejects unknown keys', function () {
    $this->get('/t/'.$this->org->tracking_key.'.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8');

    $this->get('/t/tk_doesnotexist.js')->assertNotFound();
    track($this, ['type' => 'pageview', 'path' => '/x'], 'tk_doesnotexist')->assertNotFound();
});

it('ingests pageviews into a tenant-scoped visitor with sessions on a 30-minute window', function () {
    track($this, ['type' => 'pageview', 'path' => '/home', 'utm_source' => 'google', 'utm_campaign' => 'brand'])->assertOk();
    track($this, ['type' => 'pageview', 'path' => '/about', 'utm_source' => 'linkedin'])->assertOk();

    $visitor = Visitor::withoutGlobalScope('tenant')->firstOrFail();
    expect((int) $visitor->organization_id)->toBe((int) $this->org->id)
        ->and($visitor->visits)->toBe(1)
        ->and($visitor->page_views)->toBe(2)
        ->and($visitor->last_path)->toBe('/about')
        // First-touch attribution never overwritten.
        ->and($visitor->utm_source)->toBe('google')
        ->and($visitor->utm_campaign)->toBe('brand');

    // A quiet 40 minutes starts a second session.
    $this->travel(40)->minutes();
    track($this, ['type' => 'pageview', 'path' => '/pricing'])->assertOk();
    expect($visitor->refresh()->visits)->toBe(2);
    $this->travelBack();
});

it('rejects junk visitor ids', function () {
    track($this, ['type' => 'pageview', 'vid' => 'UPPER CASE ID!', 'path' => '/x'])->assertStatus(422);
    expect(Visitor::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('identifies a visitor by email and records the signal for the matched contact', function () {
    app(CurrentOrganization::class)->set($this->org);
    $contact = Contact::create(['first_name' => 'Dana', 'email' => 'dana@clinic.test']);
    app(CurrentOrganization::class)->forget();

    track($this, ['type' => 'pageview', 'path' => '/services/cybersecurity'])->assertOk();
    track($this, ['type' => 'identify', 'email' => 'Dana@Clinic.test'])->assertOk();

    $visitor = Visitor::withoutGlobalScope('tenant')->firstOrFail();
    expect((int) $visitor->contact_id)->toBe((int) $contact->id)
        ->and($visitor->email)->toBe('dana@clinic.test');
    expect(IntentSignal::withoutGlobalScope('tenant')->where('contact_id', $contact->id)->where('type', 'identified_on_site')->exists())->toBeTrue();

    // Post-identification browsing feeds the intent engine.
    track($this, ['type' => 'pageview', 'path' => '/pricing'])->assertOk();
    $signal = IntentSignal::withoutGlobalScope('tenant')->where('type', 'high_intent_page')->firstOrFail();
    expect((int) $signal->contact_id)->toBe((int) $contact->id)
        ->and((int) $signal->weight)->toBe(8);
});

it('keeps anonymous heat on the visitor row and out of intent signals', function () {
    track($this, ['type' => 'pageview', 'path' => '/pricing'])->assertOk();
    track($this, ['type' => 'pageview', 'path' => '/blog/backup-tips'])->assertOk();

    $visitor = Visitor::withoutGlobalScope('tenant')->firstOrFail();
    expect($visitor->intent_score)->toBe(10) // 8 high-intent + 2 content
        ->and(IntentSignal::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('links the browsing trail when a tracked visitor submits a hosted form', function () {
    track($this, ['type' => 'pageview', 'path' => '/services/managed-it'])->assertOk();

    app(CurrentOrganization::class)->set($this->org);
    $form = Form::create(['name' => 'Contact', 'slug' => 'contact-'.uniqid(), 'status' => 'published', 'fields' => [
        ['name' => 'first_name', 'type' => 'text', 'required' => true],
        ['name' => 'email', 'type' => 'email', 'required' => true],
    ]]);
    app(CurrentOrganization::class)->forget();

    $this->withUnencryptedCookie('_pt_vid', 'abcdef1234567890')
        ->post('/f/'.$form->slug, ['first_name' => 'Sam', 'email' => 'sam@prospect.test'])
        ->assertOk(); // the public form renders its thank-you page

    $visitor = Visitor::withoutGlobalScope('tenant')->firstOrFail();
    $contact = Contact::withoutGlobalScope('tenant')->where('email', 'sam@prospect.test')->firstOrFail();
    expect((int) $visitor->contact_id)->toBe((int) $contact->id);
});

it('seals tenants: one org\'s key cannot see or touch another\'s visitors', function () {
    [$orgB, $ownerB] = makeOrganization('Other Visitor Org');
    subscribeOrganization($orgB, 'enterprise');
    $orgB->forceFill(['tracking_key' => 'tk_'.Str::lower(Str::random(24))])->save();

    track($this, ['type' => 'pageview', 'path' => '/a'])->assertOk();
    track($this, ['type' => 'pageview', 'path' => '/b'], $orgB->tracking_key)->assertOk();

    expect(Visitor::withoutGlobalScope('tenant')->where('organization_id', $this->org->id)->count())->toBe(1)
        ->and(Visitor::withoutGlobalScope('tenant')->where('organization_id', $orgB->id)->count())->toBe(1);

    // Page props are tenant-scoped.
    $mine = $this->actingAs($this->owner)->get(route('sales.visitors.index'))->assertOk()->viewData('page')['props'];
    expect($mine['total'])->toBe(1)
        ->and(collect($mine['visitors']['data'])->pluck('last_path'))->toContain('/a')->not->toContain('/b');
});

it('mints a tracking key on first visit to the page and shows the snippet', function () {
    $this->org->forceFill(['tracking_key' => null])->save();

    $props = $this->actingAs($this->owner)->get(route('sales.visitors.index'))->assertOk()->viewData('page')['props'];

    expect($props['trackingKey'])->toStartWith('tk_')
        ->and($props['snippet'])->toContain($props['trackingKey'])
        ->and($this->org->refresh()->tracking_key)->toBe($props['trackingKey']);
});
