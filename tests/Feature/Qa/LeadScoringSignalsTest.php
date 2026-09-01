<?php

declare(strict_types=1);

/**
 * Lead Scoring close-out (Phase 9 — LSCR-005..010, 019).
 *
 * The behavioural rows close because event capture is now automatic end to
 * end: pricing/service pageviews score through the tracker, repeat visits and
 * form submissions record §20-weighted signals, and email engagement lands as
 * campaign clicks (Phase 8). Routing: an unowned captured lead is assigned
 * round-robin to the least-loaded active member the moment it exists.
 */

use App\Authorization\Role;
use App\Models\Contact;
use App\Models\Form;
use App\Models\IntentSignal;
use App\Models\ScoringRule;
use App\Models\Visitor;
use App\Services\Sales\LeadScoringService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Str;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Scoring Signals Org');
    subscribeOrganization($this->org, 'enterprise');
    $this->org->forceFill(['tracking_key' => 'tk_'.Str::lower(Str::random(24))])->save();
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function scoreTrack($test, string $vid, array $payload)
{
    return $test->postJson('/t/'.$test->org->tracking_key.'/e', array_merge(['vid' => $vid], $payload));
}

it('captures every behavioural signal automatically and scores it end to end', function () {
    app(CurrentOrganization::class)->set($this->org);
    Form::create(['name' => 'CMMC Guide Download', 'slug' => 'cmmc-guide', 'fields' => [
        ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
    ], 'status' => 'published', 'lifecycle_stage' => 'lead']);
    ScoringRule::create(['name' => 'Behavioural', 'category' => 'behavioural', 'attribute' => 'intent_score',
        'operator' => 'gte', 'value' => '25', 'points' => 60, 'is_active' => true]);
    app(CurrentOrganization::class)->forget();

    $vid = 'scoresignals123456ab';

    // 1. Anonymous browsing: pricing (8) + service page (3) accrue on the visitor.
    scoreTrack($this, $vid, ['type' => 'pageview', 'path' => '/pricing']);
    scoreTrack($this, $vid, ['type' => 'pageview', 'path' => '/services/cybersecurity']);

    // 2. Form submission with the tracker cookie: capture + link + route + signal.
    $this->withUnencryptedCookie('_pt_vid', $vid)
        ->post(route('public.form.submit', 'cmmc-guide'), ['name' => 'Signal Sam', 'email' => 'sam@prospect.test'])
        ->assertOk();

    $contact = Contact::withoutGlobalScopes()->where('email', 'sam@prospect.test')->firstOrFail();

    // LSCR-019: routed to an owner at capture time.
    expect($contact->owner_id)->not->toBeNull();

    // LSCR-008/009: the form submission recorded a §20-weighted signal.
    expect(IntentSignal::withoutGlobalScopes()->where('contact_id', $contact->id)->where('type', 'form_submission')->value('weight'))->toBe(10);

    // 3. LSCR-005/006/007: post-identification pageviews score automatically.
    scoreTrack($this, $vid, ['type' => 'pageview', 'path' => '/pricing']);
    expect(IntentSignal::withoutGlobalScopes()->where('contact_id', $contact->id)->where('type', 'high_intent_page')->count())->toBe(1);

    // 4. LSCR-010: a fresh session records the repeat-visit signal.
    Visitor::withoutGlobalScopes()->where('visitor_key', $vid)->firstOrFail()
        ->forceFill(['last_seen_at' => now()->subMinutes(31)])->save();
    scoreTrack($this, $vid, ['type' => 'pageview', 'path' => '/contact']);
    expect(IntentSignal::withoutGlobalScopes()->where('contact_id', $contact->id)->where('type', 'repeat_visit')->value('weight'))->toBe(10);

    // 5. The scoring engine turns the accumulated signals into a lead score.
    app(CurrentOrganization::class)->set($this->org);
    $scored = app(LeadScoringService::class)->apply($contact->fresh());
    // form_submission 10 + high_intent_page 8 + repeat_visit 10 + high_intent_page 8 = 36 >= 25 -> rule fires.
    expect($scored->lead_score)->toBe(60)
        ->and($scored->lifecycle_stage)->toBe('sql');
});

it('routes round-robin to the least-loaded active member', function () {
    app(CurrentOrganization::class)->set($this->org);
    $rep = addMember($this->org, Role::SalesRepresentative);
    // The owner already carries a contact; the rep is free.
    Contact::create(['first_name' => 'Busy', 'email' => 'busy@x.test', 'owner_id' => $this->owner->id]);
    Form::create(['name' => 'Contact', 'slug' => 'route-me', 'fields' => [
        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
    ], 'status' => 'published', 'lifecycle_stage' => 'lead']);
    app(CurrentOrganization::class)->forget();

    $this->post(route('public.form.submit', 'route-me'), ['email' => 'fresh@prospect.test'])->assertOk();

    $lead = Contact::withoutGlobalScopes()->where('email', 'fresh@prospect.test')->firstOrFail();
    expect((int) $lead->owner_id)->toBe((int) $rep->id);

    // An already-owned contact resubmitting keeps its owner.
    app(CurrentOrganization::class)->set($this->org);
    $owned = Contact::create(['first_name' => 'Kept', 'email' => 'kept@x.test', 'owner_id' => $this->owner->id]);
    app(CurrentOrganization::class)->forget();
    $this->post(route('public.form.submit', 'route-me'), ['email' => 'kept@x.test'])->assertOk();
    expect((int) $owned->fresh()->owner_id)->toBe((int) $this->owner->id);
});
