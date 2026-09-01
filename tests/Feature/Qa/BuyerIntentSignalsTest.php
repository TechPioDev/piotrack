<?php

declare(strict_types=1);

/**
 * Buyer Intent Intelligence close-out (Phase 8 — INTENT-010..013).
 *
 * Cross-module intent ingestion is now first-party and real: a campaign click
 * records intent through the public tracking pixel route (org resolved from
 * the recipient, since public routes carry no tenant context); a known contact
 * arriving on a paid first touch records ad engagement once per session; real
 * CRM touchpoints (call/email/meeting — not notes or tasks) record activity
 * intent; and the buying window fires only on sustained recent activity.
 * Company identification (reverse-IP) stays external and unfaked.
 */

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\IntentSignal;
use App\Models\Visitor;
use App\Services\Sales\IntentService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Str;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Intent Org');
    subscribeOrganization($this->org, 'enterprise');
    $this->org->forceFill(['tracking_key' => 'tk_'.Str::lower(Str::random(24))])->save();
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('records campaign-click intent from the public tracking route (INTENT-010)', function () {
    app(CurrentOrganization::class)->set($this->org);
    $contact = Contact::create(['first_name' => 'Clicky', 'email' => 'clicky@x.test']);
    $campaign = Campaign::create(['name' => 'Q4 Push', 'type' => 'email', 'status' => 'sent']);
    $recipient = CampaignRecipient::create([
        'campaign_id' => $campaign->id, 'contact_id' => $contact->id,
        'address' => 'clicky@x.test', 'token' => 'tok-click-1', 'status' => 'sent',
    ]);
    app(CurrentOrganization::class)->forget();

    $this->get(route('public.track.click', ['token' => 'tok-click-1', 'u' => 'https://msp.test/pricing']))
        ->assertRedirect('https://msp.test/pricing');

    $signal = IntentSignal::withoutGlobalScopes()->where('contact_id', $contact->id)->where('type', 'campaign_click')->firstOrFail();
    expect($signal->weight)->toBe(10)
        ->and((int) $signal->organization_id)->toBe((int) $this->org->id);

    // A second click on the same token does not double-record intent.
    $this->get(route('public.track.click', ['token' => 'tok-click-1', 'u' => 'https://msp.test/pricing']));
    expect(IntentSignal::withoutGlobalScopes()->where('contact_id', $contact->id)->where('type', 'campaign_click')->count())->toBe(1);
});

it('records ad engagement when a known contact arrives on a paid first touch (INTENT-011)', function () {
    app(CurrentOrganization::class)->set($this->org);
    $contact = Contact::create(['first_name' => 'Addy', 'email' => 'addy@x.test']);
    app(CurrentOrganization::class)->forget();

    $vid = 'intentad1234567890ab';
    // First session arrives via paid UTM and identifies.
    $this->postJson('/t/'.$this->org->tracking_key.'/e', ['vid' => $vid, 'type' => 'pageview', 'path' => '/', 'utm_source' => 'google', 'utm_medium' => 'cpc']);
    $this->postJson('/t/'.$this->org->tracking_key.'/e', ['vid' => $vid, 'type' => 'identify', 'email' => 'addy@x.test']);

    // A second session (31 quiet minutes) records ad engagement exactly once.
    Visitor::withoutGlobalScopes()->where('visitor_key', $vid)->firstOrFail()
        ->forceFill(['last_seen_at' => now()->subMinutes(31)])->save();
    $this->postJson('/t/'.$this->org->tracking_key.'/e', ['vid' => $vid, 'type' => 'pageview', 'path' => '/services']);
    $this->postJson('/t/'.$this->org->tracking_key.'/e', ['vid' => $vid, 'type' => 'pageview', 'path' => '/pricing']);

    expect(IntentSignal::withoutGlobalScopes()->where('contact_id', $contact->id)->where('type', 'ad_engagement')->count())->toBe(1);
});

it('records CRM-activity intent for real touchpoints only (INTENT-012)', function () {
    app(CurrentOrganization::class)->set($this->org);
    $contact = Contact::create(['first_name' => 'Meets', 'email' => 'meets@x.test']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->post(route('crm.activities.store'), [
        'subject_type' => 'contact', 'subject_id' => $contact->id, 'type' => 'meeting', 'title' => 'Discovery call',
    ])->assertRedirect();

    // A note is bookkeeping, not buyer intent.
    $this->actingAs($this->owner)->post(route('crm.activities.store'), [
        'subject_type' => 'contact', 'subject_id' => $contact->id, 'type' => 'note', 'title' => 'internal note',
    ])->assertRedirect();

    $signals = IntentSignal::withoutGlobalScopes()->where('contact_id', $contact->id)->where('type', 'crm_activity')->get();
    expect($signals)->toHaveCount(1)->and($signals[0]->weight)->toBe(5);
});

it('detects a buying window only on sustained recent activity (INTENT-013)', function () {
    app(CurrentOrganization::class)->set($this->org);
    $intent = app(IntentService::class);

    // One big signal is hot, but not a window.
    $spike = Contact::create(['first_name' => 'Spike', 'email' => 's@x.test']);
    $intent->record($spike, 'pricing_page_view', 25);
    expect($intent->inBuyingWindow($spike))->toBeFalse();

    // Three signals over the score floor inside the fortnight: window open.
    $steady = Contact::create(['first_name' => 'Steady', 'email' => 'st@x.test']);
    foreach ([8, 8, 8] as $weight) {
        $intent->record($steady, 'service_view', $weight);
    }
    expect($intent->inBuyingWindow($steady))->toBeTrue();

    // The same activity aged past the window no longer counts.
    IntentSignal::where('contact_id', $steady->id)->update(['occurred_at' => now()->subDays(20)]);
    expect($intent->inBuyingWindow($steady->fresh()))->toBeFalse();

    // Surfaced on the intent workspace.
    app(CurrentOrganization::class)->forget();
    $props = $this->actingAs($this->owner)->get(route('sales.intent.index'))->assertOk()->viewData('page')['props'];
    expect(collect($props['contacts'])->firstWhere('name', 'Spike')['buying_window'])->toBeFalse();
});
