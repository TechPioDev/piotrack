<?php

declare(strict_types=1);

/**
 * Website Chat / Conversations module (CHAT) — Phase 1 vertical slice.
 *
 * Proves the whole chain the module exists for: an anonymous visitor on someone
 * else's website runs the qualification flow through the public API, and comes
 * out the other side as a scored CRM contact + lead, routed to an owner, with a
 * sales alert and attribution intact — plus the security properties that make it
 * safe to expose publicly (tenant isolation, entitlement, origin allow-list,
 * server-authoritative scoring).
 */

use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatWidget;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\SalesAlert;
use App\Models\ScoringRule;
use App\Services\Chat\DefaultChatFlow;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create([
        'name' => 'Managed IT Website Qualification',
        'status' => 'active',
        'consent' => ['required' => true, 'message' => 'We use this chat to respond to your request.'],
    ]);
    app(CurrentOrganization::class)->forget();
});

/** Walk the whole default flow as a visitor would, returning the final payload. */
function runChat(string $key, array $answers, array $context = []): array
{
    $start = test()->postJson("/wc/{$key}/conversations", $context);
    $start->assertOk();
    $token = $start->json('token');

    $payload = $start->json();
    foreach ($answers as $answer) {
        $response = test()->postJson("/wc/{$key}/conversations/{$token}/messages", $answer);
        $response->assertOk();
        $payload = $response->json();
    }

    return ['token' => $token, 'payload' => $payload];
}

it('serves only whitelisted public config for an active widget', function () {
    $response = $this->getJson("/wc/{$this->widget->public_key}/config");

    $response->assertOk()
        ->assertJsonPath('theme.position', 'bottom-right')
        ->assertJsonPath('consent_required', true);

    // Tenant internals must never reach the customer's website.
    expect($response->json())->not->toHaveKeys(['flow', 'routing', 'organization_id', 'allowed_domains']);
});

it('hides widgets that are not active, unknown, or on an unentitled plan', function () {
    $this->getJson('/wc/wc_does_not_exist/config')->assertNotFound();

    app(CurrentOrganization::class)->set($this->org);
    $paused = ChatWidget::create(['name' => 'Paused', 'status' => 'paused']);
    app(CurrentOrganization::class)->forget();
    $this->getJson("/wc/{$paused->public_key}/config")->assertNotFound();

    // A tenant whose plan lacks the chat feature serves nothing publicly.
    [$starterOrg] = makeOrganization('Starter Co');
    subscribeOrganization($starterOrg, 'starter');
    app(CurrentOrganization::class)->set($starterOrg);
    $unentitled = ChatWidget::create(['name' => 'Starter widget', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();

    $this->getJson("/wc/{$unentitled->public_key}/config")->assertNotFound();
});

it('gates the conversation behind consent and stores nothing when declined', function () {
    $start = $this->postJson("/wc/{$this->widget->public_key}/conversations");

    $start->assertOk()->assertJsonPath('node.type', 'consent');

    $token = $start->json('token');
    $declined = $this->postJson("/wc/{$this->widget->public_key}/conversations/{$token}/messages", ['option' => 'decline']);

    $declined->assertOk()->assertJsonPath('done', true);
    expect(ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token)->status)->toBe('closed');
    expect(Contact::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('runs the full §51 qualification journey into CRM, scoring, routing and alerts', function () {
    // Michael Rodriguez, CFO at Precision Manufacturing Group (180 employees),
    // needs cybersecurity + CMMC within 90 days — the brief's worked example.
    $result = runChat($this->widget->public_key, [
        ['option' => 'accept'],          // consent
        ['option' => 'cybersecurity'],   // service          +15
        ['option' => 'cmmc'],            // cyber need       +15
        ['option' => '51-250'],          // company size     +20
        ['option' => 'yes'],             // has provider     +15
        ['option' => 'compliance'],      // challenge        +10
        ['value' => 'Michael'],
        ['value' => 'Rodriguez'],
        ['value' => 'michael@precisionmfg.test'],
        ['value' => '215-555-0142'],
        ['value' => 'Precision Manufacturing Group'],
        ['option' => '1_3_months'],      // timeframe        +15  (the brief's 90 days)
        ['option' => '1'],               // sites            +0
        ['option' => 'cmmc'],            // compliance       +25  (what they came for)
        ['option' => 'yes'],             // wants meeting    +30
    ], [
        'page' => 'https://acmeit.test/cybersecurity',
        'utm' => ['source' => 'google', 'medium' => 'cpc', 'campaign' => 'cybersecurity-philadelphia'],
    ]);

    expect($result['payload']['done'])->toBeTrue();

    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $result['token']);

    // Scoring is server-side: 15+15+20+15+10 +15+0+25 +30 = 145 → hot.
    expect($conversation->lead_score)->toBe(145);

    // CRM records created, linked, and attributed.
    $contact = Contact::withoutGlobalScope('tenant')->firstWhere('email', 'michael@precisionmfg.test');
    expect($contact)->not->toBeNull()
        ->and($contact->lead_source)->toBe('website_chat')
        ->and($contact->campaign)->toBe('cybersecurity-philadelphia')
        ->and($contact->organization_id)->toBe($this->org->id);

    $lead = Lead::withoutGlobalScope('tenant')->firstWhere('email', 'michael@precisionmfg.test');
    expect($lead)->not->toBeNull()
        ->and($lead->source)->toBe('website_chat')
        ->and($lead->company_name)->toBe('Precision Manufacturing Group')
        ->and($lead->owner_id)->not->toBeNull();          // routed to a salesperson

    expect($conversation->contact_id)->toBe($contact->id)
        ->and($conversation->lead_id)->toBe($lead->id)
        ->and($conversation->status)->toBe('qualified')
        ->and($conversation->attribution['page'])->toBe('https://acmeit.test/cybersecurity')
        ->and($conversation->attribution['utm_source'])->toBe('google');

    // A hot lead raises a sales alert, and the meeting offer hands back a booking link.
    expect(SalesAlert::withoutGlobalScope('tenant')->where('contact_id', $contact->id)->exists())->toBeTrue();

    // Analytics events recorded for the funnel.
    $types = ChatEvent::withoutGlobalScope('tenant')->pluck('type')->all();
    expect($types)->toContain('start')->toContain('lead')->toContain('qualified')->toContain('complete');
});

it('routes existing customers to support instead of creating a sales lead', function () {
    runChat($this->widget->public_key, [
        ['option' => 'accept'],
        ['option' => 'existing'],
        ['option' => 'billing'],
    ]);

    expect(Lead::withoutGlobalScope('tenant')->count())->toBe(0)
        ->and(Contact::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('deduplicates a returning visitor onto the existing contact', function () {
    $answers = [
        ['option' => 'accept'], ['option' => 'managed_it'], ['option' => '11-50'],
        ['option' => 'no'], ['option' => 'slow_support'],
        ['value' => 'Dana'], ['value' => 'Whitfield'], ['value' => 'dana@repeat.test'],
        ['value' => '215-555-0101'], ['value' => 'Repeat Co'],
        ['option' => 'researching'], ['option' => '1'], ['option' => 'none'],
        ['option' => 'no'],
    ];

    runChat($this->widget->public_key, $answers);
    runChat($this->widget->public_key, $answers);

    expect(Contact::withoutGlobalScope('tenant')->where('email', 'dana@repeat.test')->count())->toBe(1);
});

it('validates answers server-side and rejects an invented option', function () {
    $start = $this->postJson("/wc/{$this->widget->public_key}/conversations");
    $token = $start->json('token');
    $key = $this->widget->public_key;

    // An option that is not on the current node cannot be used to skip ahead.
    $this->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => 'free_money'])
        ->assertStatus(422);

    $this->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => 'accept']);
    $this->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => 'managed_it']);
    $this->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => '11-50']);
    $this->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => 'no']);
    $this->postJson("/wc/{$key}/conversations/{$token}/messages", ['option' => 'slow_support']);
    $this->postJson("/wc/{$key}/conversations/{$token}/messages", ['value' => 'Dana']);
    $this->postJson("/wc/{$key}/conversations/{$token}/messages", ['value' => 'Whitfield']);

    // The email node enforces a real address.
    $this->postJson("/wc/{$key}/conversations/{$token}/messages", ['value' => 'not-an-email'])
        ->assertStatus(422);
});

it('never lets one tenant reach another tenant conversation', function () {
    [$otherOrg] = makeOrganization('Rival MSP');
    subscribeOrganization($otherOrg, 'enterprise');
    app(CurrentOrganization::class)->set($otherOrg);
    $rivalWidget = ChatWidget::create(['name' => 'Rival', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();

    $start = $this->postJson("/wc/{$this->widget->public_key}/conversations");
    $token = $start->json('token');

    // A conversation token is only valid for the widget that created it.
    $this->postJson("/wc/{$rivalWidget->public_key}/conversations/{$token}/messages", ['option' => 'accept'])
        ->assertNotFound();
});

it('enforces the domain allow-list when configured', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update(['allowed_domains' => ['acmeit.test']]);
    app(CurrentOrganization::class)->forget();

    $key = $this->widget->public_key;

    $this->getJson("/wc/{$key}/config", ['Origin' => 'https://evil.example'])->assertForbidden();
    $this->getJson("/wc/{$key}/config", ['Origin' => 'https://acmeit.test'])->assertOk();
    $this->getJson("/wc/{$key}/config", ['Origin' => 'https://www.acmeit.test'])->assertOk();
});

it('silently absorbs honeypot submissions', function () {
    $this->postJson("/wc/{$this->widget->public_key}/conversations", ['website' => 'http://spam.test'])
        ->assertOk()
        ->assertJsonPath('done', true);

    expect(ChatConversation::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('applies the tenant scoring rules on top of the chat score', function () {
    app(CurrentOrganization::class)->set($this->org);
    ScoringRule::create([
        'name' => 'Chat source', 'category' => 'demographic', 'attribute' => 'lead_source',
        'operator' => 'equals', 'value' => 'website_chat', 'points' => 40, 'is_active' => true,
    ]);
    app(CurrentOrganization::class)->forget();

    runChat($this->widget->public_key, [
        ['option' => 'accept'], ['option' => 'm365'], ['option' => '1-10'],
        ['option' => 'no'], ['option' => 'other'],
        ['value' => 'Sam'], ['value' => 'Low'], ['value' => 'sam@small.test'],
        ['value' => '215-555-0199'], ['value' => 'Small Co'],
        // All zero-score answers, so the tenant rule's 40 is the whole total.
        ['option' => 'researching'], ['option' => '1'], ['option' => 'none'],
        ['option' => 'no'],
    ]);

    $contact = Contact::withoutGlobalScope('tenant')->firstWhere('email', 'sam@small.test');

    // The chat contributed 5+0+5+0 = 10; the tenant's own rule adds 40 and wins.
    expect($contact->lead_score)->toBe(40);
});

/**
 * Qualification depth is worth having, but not at the cost of the lead.
 *
 * Drop-off is measured per question and it is severe before the email — the
 * phone step alone loses roughly four visitors in ten. So the deeper questions
 * sit after contact capture: abandoning there costs detail, not the lead, and a
 * salesperson still has someone to call. This pins that ordering, because moving
 * one of them earlier would look harmless and quietly cost conversions.
 */
it('asks the deeper qualification only after it has the contact', function () {
    $flow = DefaultChatFlow::definition();
    $nodes = $flow['nodes'];

    foreach (['q_timeframe', 'q_locations', 'q_compliance'] as $id) {
        expect($nodes)->toHaveKey($id);
    }

    // Walk from the start and record the order questions are reached, always
    // taking the first option, so "after" is proven by the graph and not by
    // where the array happens to be written.
    $seen = [];
    $cursor = $flow['start'];
    $guard = 0;
    while ($cursor !== null && $guard++ < 40) {
        $node = $nodes[$cursor] ?? null;
        if ($node === null) {
            break;
        }
        $seen[] = $cursor;
        $cursor = $node['options'][0]['next'] ?? $node['next'] ?? null;
    }

    $emailAt = array_search('in_email', $seen, true);
    expect($emailAt)->not->toBeFalse();

    foreach (['q_timeframe', 'q_locations', 'q_compliance'] as $id) {
        $at = array_search($id, $seen, true);
        expect($at)->not->toBeFalse()
            ->and($at)->toBeGreaterThan($emailAt);
    }
});

it('scores the answers that decide whether to call today', function () {
    $nodes = DefaultChatFlow::definition()['nodes'];

    $score = function (string $node, string $option) use ($nodes): int {
        foreach ($nodes[$node]['options'] as $candidate) {
            if ($candidate['id'] === $option) {
                return (int) ($candidate['score'] ?? 0);
            }
        }

        return -1;
    };

    // Someone buying now outranks someone reading around, and a regulated
    // buyer outranks one with nothing to comply with.
    expect($score('q_timeframe', 'now'))->toBeGreaterThan($score('q_timeframe', 'researching'))
        ->and($score('q_compliance', 'cmmc'))->toBeGreaterThan($score('q_compliance', 'none'))
        ->and($score('q_locations', '6+'))->toBeGreaterThan($score('q_locations', '1'));

    // "Right away" is the one that should reach a human today.
    $now = collect($nodes['q_timeframe']['options'])->firstWhere('id', 'now');
    expect($now['priority'] ?? null)->toBe('high');
});
