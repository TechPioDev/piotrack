<?php

declare(strict_types=1);

/**
 * Content Marketing + Website Chat + Buyer Intent + AI Sales Agent +
 * Analytics close-out (Phase 52 — CONT-033/034/035/036, CHAT-041,
 * INTENT-002, AISA-006, ANLY-012).
 *
 * Computed refresh/expansion queues, transparent craft checks with gateway
 * drafts, chat teaser A/B with per-variant results, reverse-IP company
 * identification through the enrichment seam, the agent's provider-labeled
 * enrichment block, and map-pack positions through the rank seam.
 */

use App\Ai\AiCompletion;
use App\Crm\Contracts\EnrichmentProvider;
use App\Models\BrandProfile;
use App\Models\ChatConversation;
use App\Models\ChatWidget;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Keyword;
use App\Models\KeywordRanking;
use App\Models\Visitor;
use App\Seo\Contracts\RankProvider;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiSalesAgent;
use App\Services\Content\ContentCraftService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('ContentIntel Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('computes the refresh queue from age, thinness, score and REAL ranking drops (CONT-035)', function () {
    // Aged + thin + a real 15-position collapse on its target keyword.
    $stale = ContentPiece::create(['title' => 'Old backup guide', 'slug' => 'obg', 'content_type' => 'article', 'status' => 'published', 'body' => 'Short.', 'target_keyword' => 'msp backup', 'optimization_score' => 30]);
    $stale->forceFill(['published_at' => now()->subDays(400)])->save();
    $tracked = Keyword::create(['phrase' => 'msp backup', 'intent' => 'commercial', 'is_tracked' => true]);
    KeywordRanking::create(['keyword_id' => $tracked->id, 'engine' => 'google', 'position' => 4, 'is_competitor' => false, 'provider' => 'fixture', 'checked_at' => now()->subDays(60)]);
    KeywordRanking::create(['keyword_id' => $tracked->id, 'engine' => 'google', 'position' => 19, 'is_competitor' => false, 'provider' => 'fixture', 'checked_at' => now()]);

    // A healthy piece stays out of the queue.
    ContentPiece::create(['title' => 'Fresh deep dive', 'slug' => 'fdd', 'content_type' => 'article', 'status' => 'published', 'published_at' => now(), 'optimization_score' => 90, 'body' => str_repeat('Substantial paragraph with real depth here. ', 200)]);

    $queue = app(ContentCraftService::class)->refreshQueue();
    expect($queue)->toHaveCount(1);
    $reasons = implode(' | ', $queue[0]['reasons']);
    expect($queue[0]['title'])->toBe('Old backup guide')
        ->and($reasons)->toContain('400 days')
        ->and($reasons)->toContain('thin')
        ->and($reasons)->toContain('fell from #4 to #19');
});

it('finds expansion gaps: thin ranking-worthy pieces and unanswered mined questions (CONT-036)', function () {
    ContentPiece::create(['title' => 'Thin co-managed page', 'slug' => 'tcm', 'content_type' => 'article', 'status' => 'published', 'published_at' => now(), 'target_keyword' => 'co-managed it', 'body' => str_repeat('word ', 200)]);
    // A question-shaped tracked keyword no published piece answers.
    Keyword::create(['phrase' => 'how much does managed it cost', 'intent' => 'informational', 'is_tracked' => true]);

    $expansion = app(ContentCraftService::class)->expansionQueue();
    expect($expansion['thin_pieces'][0]['title'])->toBe('Thin co-managed page')
        ->and($expansion['thin_pieces'][0]['words'])->toBe(200)
        ->and(collect($expansion['unanswered_questions'])->pluck('question')->implode(' '))->toContain('How much does managed it cost');

    // The pieces page carries both queues.
    $props = $this->actingAs($this->owner)->get(route('content.pieces.index'))->assertOk()->viewData('page')['props'];
    expect($props['expansion']['thin_pieces'])->toHaveCount(1);
});

it('runs transparent craft checks and gateway copy DRAFTS that publish nothing (CONT-033/034)', function () {
    $weak = ContentPiece::create(['title' => 'Jargon soup', 'slug' => 'js', 'content_type' => 'article', 'status' => 'draft',
        'body' => 'Our RMM and PSA integrate with SIEM and SOC and EDR and XDR pipelines seamlessly for enterprises everywhere.']);

    $checks = collect(app(ContentCraftService::class)->craftChecks($weak))->keyBy('key');
    expect($checks['cta']['status'])->toBe('warn')
        ->and($checks['cta']['kind'])->toBe('conversion')
        ->and($checks['acronyms']['status'])->toBe('warn')
        ->and($checks['acronyms']['detail'])->toContain('RMM')
        ->and($checks['specificity']['status'])->toBe('warn');

    $strong = ContentPiece::create(['title' => 'Clear guide', 'slug' => 'cg', 'content_type' => 'article', 'status' => 'draft', 'cta' => 'Book an assessment',
        'body' => str_repeat('You get a 15-minute response commitment from your remote monitoring and management (RMM) platform team. ', 40)]);
    $strongChecks = collect(app(ContentCraftService::class)->craftChecks($strong))->keyBy('key');
    expect($strongChecks['cta']['status'])->toBe('pass')
        ->and($strongChecks['reader_address']['status'])->toBe('pass');

    // The draft endpoint returns gateway output as a flash — nothing persisted.
    $gateway = Mockery::mock(AiGateway::class);
    $gateway->shouldReceive('run')->once()->withArgs(fn ($feature, $key, $vars) => $feature === 'content.copy' && $vars['focus'] === 'conversion')
        ->andReturn(new AiCompletion(text: "HEADLINE: Faster IT\nOPENING: You deserve uptime.\nCTA: Book a call.", promptTokens: 5, completionTokens: 5, model: 'fixture-1'));
    app()->instance(AiGateway::class, $gateway);

    $before = ContentPiece::count();
    $this->actingAs($this->owner)->post(route('content.pieces.draft-copy', $weak), ['focus' => 'conversion'])
        ->assertRedirect()->assertSessionHas('draft');
    expect(ContentPiece::count())->toBe($before)
        ->and($weak->refresh()->body)->toContain('RMM'); // untouched by the draft
});

it('A/B tests the chat teaser sticky per visitor with per-variant results (CHAT-041)', function () {
    $widget = ChatWidget::create(['name' => 'W', 'public_key' => 'wk_abtest001', 'status' => 'active',
        'settings' => ['teaser' => 'Need IT help?', 'teaser_b' => 'Ransomware worries? Ask us.', 'mode' => 'bot']]);

    app(CurrentOrganization::class)->forget();

    // Sticky: the same visitor always sees the same teaser.
    $first = $this->getJson('/wc/wk_abtest001/config?vid=visitor-aaa')->assertOk()->json();
    $again = $this->getJson('/wc/wk_abtest001/config?vid=visitor-aaa')->assertOk()->json();
    expect($first['teaser'])->toBe($again['teaser'])
        ->and($first['teaser_variant'])->toBeIn(['a', 'b']);

    // Starting a conversation stamps the variant it was served.
    $this->postJson('/wc/wk_abtest001/conversations', ['visitor' => 'visitor-aaa'])->assertOk();

    app(CurrentOrganization::class)->set($this->org);
    $conversation = ChatConversation::firstOrFail();
    expect($conversation->attribution['teaser_variant'])->toBe($first['teaser_variant']);

    // Per-variant results on the analytics page; a lead counts as converted.
    $contact = Contact::create(['first_name' => 'Chat', 'email' => 'chat@x.test']);
    $conversation->update(['contact_id' => $contact->id]);
    $props = $this->actingAs($this->owner)->get(route('chat.analytics'))->assertOk()->viewData('page')['props'];
    expect($props['teaser_test']['active'])->toBeTrue()
        ->and($props['teaser_test']['variants'][$first['teaser_variant']]['conversations'])->toBe(1)
        ->and($props['teaser_test']['variants'][$first['teaser_variant']]['leads'])->toBe(1);

    // Without a B teaser there is no split and no variant stamping.
    $widget->update(['settings' => ['teaser' => 'Solo teaser', 'mode' => 'bot']]);
    app(CurrentOrganization::class)->forget();
    expect($this->getJson('/wc/wk_abtest001/config?vid=visitor-aaa')->json('teaser_variant'))->toBeNull();
    app(CurrentOrganization::class)->set($this->org);
});

it('identifies companies by reverse IP through the seam, first-party truth first (INTENT-002)', function () {
    $provider = app(EnrichmentProvider::class);

    // Private space and provider misses are honest nulls; hits are deterministic.
    expect($provider->identifyCompany('192.168.1.10'))->toBeNull();
    $hit = null;
    foreach (range(1, 40) as $i) {
        $candidate = $provider->identifyCompany("52.4.18.{$i}");
        if ($candidate !== null) {
            $hit = ['ip' => "52.4.18.{$i}", 'data' => $candidate];

            break;
        }
    }
    expect($hit)->not->toBeNull()
        ->and($provider->identifyCompany($hit['ip']))->toBe($hit['data']);

    // The visitors page: anonymous visitors get the provider ID; identified
    // visitors keep their contact's real company (never the guess).
    Visitor::create(['visitor_key' => 'anonvisitor1', 'first_seen_at' => now(), 'visits' => 1, 'last_ip' => $hit['ip']]);
    $contact = Contact::create(['first_name' => 'Known', 'email' => 'known@x.test']);
    Visitor::create(['visitor_key' => 'knownvisitor', 'first_seen_at' => now(), 'visits' => 1, 'contact_id' => $contact->id, 'last_ip' => $hit['ip']]);

    $props = $this->actingAs($this->owner)->get(route('sales.visitors.index'))->assertOk()->viewData('page')['props'];
    $rows = collect($props['visitors']['data']);
    expect($props['enrichment_provider'])->toBe('fixture')
        ->and($rows->firstWhere('contact_id', null)['identified_company']['company_name'])->toBe($hit['data']['company_name'])
        ->and($rows->firstWhere('contact_id', $contact->id)['identified_company'])->toBeNull();
});

it('labels the agent research enrichment block with its driver, never fabricating (AISA-006)', function () {
    $captured = [];
    $gateway = Mockery::mock(AiGateway::class);
    $gateway->shouldReceive('run')->andReturnUsing(function ($feature, $key, $vars) use (&$captured) {
        $captured[] = $vars;

        return new AiCompletion(text: 'summary', promptTokens: 5, completionTokens: 5, model: 'fixture-1');
    });
    app()->instance(AiGateway::class, $gateway);

    $corporate = Contact::create(['first_name' => 'C', 'email' => 'ops@ridgeline-mfg.example']);
    $personal = Contact::create(['first_name' => 'P', 'email' => 'someone@gmail.com']);

    $agent = app(AiSalesAgent::class);
    $agent->researchLead($corporate);
    $agent->researchLead($personal);

    expect($captured[0]['profile'])->toContain('Enrichment data (driver: fixture - simulated)')
        ->and($captured[0]['profile'])->toContain('Ridgeline Mfg')
        ->and($captured[1]['profile'])->toContain('No enrichment data for this address (driver: fixture)');
});

it('measures map-pack positions through the rank seam, provider-labeled (ANLY-012)', function () {
    BrandProfile::first()?->update(['legal_name' => 'Acme Managed IT'])
        ?? BrandProfile::create(['legal_name' => 'Acme Managed IT']);
    Keyword::create(['phrase' => 'msp philadelphia', 'intent' => 'commercial', 'is_tracked' => true, 'location' => 'Philadelphia, PA']);
    Keyword::create(['phrase' => 'it support chester county', 'intent' => 'commercial', 'is_tracked' => true, 'location' => 'Chester County, PA']);

    $provider = app(RankProvider::class);
    $first = $provider->localPack('msp philadelphia', 'Acme Managed IT', 'Philadelphia, PA');
    // Deterministic: same inputs, same answer (a position 1-3 or an honest null).
    expect($provider->localPack('msp philadelphia', 'Acme Managed IT', 'Philadelphia, PA'))->toBe($first);
    if ($first !== null) {
        expect($first)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3);
    }

    $props = $this->actingAs($this->owner)->get(route('analytics.dashboard'))->assertOk()->viewData('page')['props'];
    expect($props['map_rankings']['provider'])->toBe('fixture')
        ->and($props['map_rankings']['rows'])->toHaveCount(2)
        ->and($props['map_rankings']['rows'][0]['position'])->toBe($first);
});
