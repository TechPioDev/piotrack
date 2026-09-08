<?php

declare(strict_types=1);

/**
 * Marketing Strategy & Research close-out (Phase 47 — STRAT-005/007/008/009/014/015/016).
 *
 * TAM/SAM/SOM computed server-side with per-input provenance and saved as an
 * auditable research item; personas authored against first-party evidence;
 * pain-point themes over real signals; the measured journey map; service-line
 * opportunity analysis on hard deal bindings; positioning angles citing their
 * numbers; messaging presence analysis over published assets.
 */

use App\Models\BrandProfile;
use App\Models\BuyerPersona;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatWidget;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Deal;
use App\Models\Keyword;
use App\Models\Pipeline;
use App\Models\Review;
use App\Models\ServiceLine;
use App\Models\SitePage;
use App\Models\SocialInteraction;
use App\Models\StrategyItem;
use App\Models\Ticket;
use App\Models\Visitor;
use App\Services\Strategy\JourneyMapService;
use App\Services\Strategy\MarketSizingService;
use App\Services\Strategy\MessagingAnalysisService;
use App\Services\Strategy\PainPointResearchService;
use App\Services\Strategy\PositioningResearchService;
use App\Services\Strategy\ServiceLineOpportunityService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Research Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function researchDeal(array $attributes): Deal
{
    $pipeline = Pipeline::where('is_default', true)->with('stages')->firstOrFail();
    $status = $attributes['status'] ?? 'open';
    $stage = match ($status) {
        'won' => $pipeline->stages->firstWhere('is_won', true),
        'lost' => $pipeline->stages->firstWhere('is_lost', true),
        default => $pipeline->stages->firstWhere('is_won', false),
    };

    return Deal::create($attributes + ['pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'name' => 'D'.uniqid()]);
}

it('computes the TAM model from entered market data and record-derived economics, saved auditably', function () {
    // 3 won (median MRR $2,000.00) + 2 lost => real win rate 60%.
    researchDeal(['status' => 'won', 'mrr' => 100000, 'closed_at' => now()]);
    researchDeal(['status' => 'won', 'mrr' => 200000, 'closed_at' => now()]);
    researchDeal(['status' => 'won', 'mrr' => 300000, 'closed_at' => now()]);
    researchDeal(['status' => 'lost', 'closed_at' => now()]);
    researchDeal(['status' => 'lost', 'closed_at' => now()]);

    $model = app(MarketSizingService::class)->model(['market_businesses' => 1000, 'addressable_pct' => 50, 'reachable_pct' => 10]);

    // The rep entered only the market; economics derive from real records.
    expect($model['provenance']['market_businesses'])->toBe('entered')
        ->and($model['provenance']['avg_mrr'])->toBe('derived_from_records')
        ->and($model['provenance']['win_rate_pct'])->toBe('derived_from_records')
        ->and($model['inputs']['avg_mrr'])->toBe(200000)
        ->and($model['inputs']['win_rate_pct'])->toBe(60.0)
        ->and($model['sam_accounts'])->toBe(500)
        ->and($model['som_accounts'])->toBe(30)          // 500 × 10% × 60%
        ->and($model['som_mrr'])->toBe(30 * 200000);

    // The endpoint stores it as an auditable research item.
    $this->actingAs($this->owner)->post(route('strategy.research.tam'), [
        'title' => 'Philly metro TAM',
        'market_businesses' => 1000,
        'addressable_pct' => 50,
        'reachable_pct' => 10,
    ])->assertRedirect();

    $item = StrategyItem::where('type', 'research')->latest('id')->firstOrFail();
    expect($item->title)->toBe('Philly metro TAM')
        ->and($item->findings)->toContain('derived_from_records')
        ->and($item->findings)->toContain('SAM: 500 accounts')
        ->and($item->findings)->toContain('SOM: 30 accounts')
        ->and($item->findings)->toContain('3 won / 5 closed deals');

    // An explicit entry beats the derived default and is labeled as entered.
    $override = app(MarketSizingService::class)->model(['market_businesses' => 100, 'avg_mrr' => 500, 'win_rate_pct' => 25]);
    expect($override['provenance']['avg_mrr'])->toBe('entered')
        ->and($override['inputs']['avg_mrr'])->toBe(50000);
});

it('develops personas against first-party evidence, tenant-fenced', function () {
    // Evidence: buying roles, titles at won-deal companies, real visitor questions.
    $company = Company::create(['name' => 'Won Mfg']);
    Contact::create(['first_name' => 'A', 'email' => 'a@x.test', 'company_id' => $company->id, 'title' => 'CFO', 'buying_role' => 'decision_maker']);
    Contact::create(['first_name' => 'B', 'email' => 'b@x.test', 'company_id' => $company->id, 'title' => 'IT Manager', 'buying_role' => 'influencer']);
    researchDeal(['status' => 'won', 'company_id' => $company->id, 'closed_at' => now()]);

    $widget = ChatWidget::create(['name' => 'W', 'is_active' => true]);
    $conversation = ChatConversation::create(['chat_widget_id' => $widget->id, 'status' => 'open']);
    ChatMessage::create(['chat_conversation_id' => $conversation->id, 'role' => 'visitor', 'body' => 'How fast do you respond to a ransomware incident?']);

    $props = $this->actingAs($this->owner)->get(route('strategy.research'))->assertOk()->viewData('page')['props'];
    expect($props['persona_evidence']['buying_roles'])->toHaveKey('decision_maker')
        ->and($props['persona_evidence']['won_titles'])->toHaveKey('CFO')
        ->and($props['persona_evidence']['questions'][0])->toContain('ransomware');

    // The narrative is rep-authored, structured, and editable.
    $this->actingAs($this->owner)->post(route('strategy.research.personas.store'), [
        'name' => 'Operations Olivia',
        'role_title' => 'Operations Director',
        'seniority' => 'director',
        'pains' => 'Downtime on the plant floor.',
    ])->assertRedirect();

    $persona = BuyerPersona::firstOrFail();
    $this->actingAs($this->owner)->patch(route('strategy.research.personas.update', $persona), [
        'name' => 'Operations Olivia',
        'goals' => 'Zero unplanned downtime.',
    ])->assertRedirect();
    expect($persona->refresh()->goals)->toBe('Zero unplanned downtime.');

    // Another tenant can neither edit nor remove it.
    [$otherOrg, $otherOwner] = makeOrganization('Other Research Org');
    subscribeOrganization($otherOrg, 'enterprise');
    $this->actingAs($otherOwner)->delete(route('strategy.research.personas.destroy', $persona))->assertNotFound();
    expect(BuyerPersona::withoutGlobalScopes()->whereKey($persona->id)->exists())->toBeTrue();
});

it('buckets pain-point research from the signals the platform actually holds, with labeled quotes', function () {
    $widget = ChatWidget::create(['name' => 'W', 'is_active' => true]);
    $conversation = ChatConversation::create(['chat_widget_id' => $widget->id, 'status' => 'open']);
    ChatMessage::create(['chat_conversation_id' => $conversation->id, 'role' => 'visitor', 'body' => 'We got hit by ransomware last quarter.']);
    Review::create(['source' => 'google', 'author_name' => 'R', 'rating' => 2, 'sentiment' => 'negative', 'body' => 'Constant outage after outage.']);
    SocialInteraction::create(['network' => 'linkedin', 'kind' => 'comment', 'body' => 'Their pricing is too expensive for what you get.', 'sentiment' => 'negative']);
    Ticket::create(['requester_id' => $this->owner->id, 'subject' => 'Waiting three days for a callback', 'body' => 'Still no response.', 'status' => 'open']);

    $research = app(PainPointResearchService::class)->themes();

    expect($research['total_signals'])->toBe(4)
        ->and($research['sources'])->toBe(['chat' => 1, 'review' => 1, 'social' => 1, 'ticket' => 1]);

    $byTheme = collect($research['themes'])->keyBy('theme');
    expect($byTheme['Security & breaches']['total'])->toBe(1)
        ->and($byTheme['Downtime & reliability']['by_source'])->toBe(['review' => 1])
        ->and($byTheme['Cost & budget']['quotes'][0]['source'])->toBe('social')
        ->and($byTheme['Response time & support']['total'])->toBeGreaterThanOrEqual(1);
});

it('maps the buyer journey with measured stage metrics, content coverage and real time-to-close', function () {
    Visitor::create(['visitor_key' => 'v1', 'first_seen_at' => now(), 'visits' => 1, 'page_views' => 2]);
    $lead = Contact::create(['first_name' => 'L', 'email' => 'l@x.test', 'lifecycle_stage' => 'mql']);
    Contact::create(['first_name' => 'S', 'email' => 's@x.test', 'lifecycle_stage' => 'sql']);
    Visitor::create(['visitor_key' => 'v2', 'first_seen_at' => now(), 'visits' => 1, 'page_views' => 1, 'contact_id' => $lead->id]);

    ContentPiece::create(['title' => 'Awareness post', 'slug' => 'aw1', 'content_type' => 'article', 'status' => 'published', 'published_at' => now(), 'funnel_stage' => 'tof']);
    ContentPiece::create(['title' => 'Comparison guide', 'slug' => 'co1', 'content_type' => 'guide', 'status' => 'published', 'published_at' => now(), 'funnel_stage' => 'mof']);

    $deal = researchDeal(['status' => 'won', 'mrr' => 150000, 'closed_at' => now()]);
    $deal->created_at = now()->subDays(30);
    $deal->save();

    $map = app(JourneyMapService::class)->map();
    $stages = collect($map['stages'])->keyBy('stage');

    expect($stages['awareness']['metrics']['visitors'])->toBe(2)
        ->and($stages['awareness']['metrics']['identified'])->toBe(1)
        ->and($stages['awareness']['published_content'])->toBe(1)
        ->and($stages['awareness']['conversion']['rate'])->toBe(50.0)
        ->and($stages['consideration']['metrics']['mqls'])->toBe(1)
        ->and($stages['consideration']['published_content'])->toBe(1)
        ->and($stages['decision']['metrics']['sqls'])->toBe(1)
        ->and($stages['customer']['metrics']['won'])->toBe(1)
        ->and($stages['customer']['conversion']['rate'])->toBe(100.0)
        ->and($map['avg_days_to_close'])->toEqualWithDelta(30.0, 0.1);
});

it('analyses service-line opportunity on hard deal bindings with recommendations citing numbers', function () {
    $lines = ServiceLine::where('is_active', true)->orderBy('id')->limit(2)->get();
    [$first, $second] = [$lines[0], $lines[1]];

    // The deal form binds a service line (STRAT-014 hard binding).
    $this->actingAs($this->owner)->post(route('crm.deals.store'), [
        'name' => 'Managed security for Acme',
        'value' => 4000,
        'service_line_id' => $first->id,
    ])->assertRedirect();
    expect(Deal::firstOrFail()->service_line_id)->toBe($first->id);

    researchDeal(['status' => 'won', 'mrr' => 250000, 'value' => 250000, 'service_line_id' => $first->id, 'closed_at' => now()]);
    SitePage::create(['type' => 'service', 'slug' => 'svc-2', 'title' => 'Second line', 'status' => SitePage::STATUS_PUBLISHED, 'service_line_id' => $second->id]);
    researchDeal(['status' => 'open', 'value' => 90000]); // deliberately unbound

    $analysis = app(ServiceLineOpportunityService::class)->analysis();
    $byLine = collect($analysis['lines'])->keyBy('id');

    expect($byLine[$first->id]['deals'])->toBe(2)
        ->and($byLine[$first->id]['won'])->toBe(1)
        ->and($byLine[$first->id]['won_mrr'])->toBe(250000)
        ->and($byLine[$first->id]['win_rate'])->toBe(100.0)
        ->and($byLine[$first->id]['pages'])->toBe(0)
        // Won revenue but no page selling it — the recommendation cites the number.
        ->and(implode(' ', $byLine[$first->id]['recommendations']))->toContain('$2,500.00')
        ->and($byLine[$second->id]['pages'])->toBe(1)
        ->and(implode(' ', $byLine[$second->id]['recommendations']))->toContain('No deals are bound')
        ->and($analysis['unbound_deals'])->toBe(1);
});

it('composes positioning angles and messaging coverage that cite the records behind them', function () {
    BrandProfile::create([
        'legal_name' => 'Acme Managed IT',
        'usp' => 'Guaranteed fifteen-minute response for manufacturers',
        'value_proposition' => 'Predictable uptime for regulated manufacturers',
        'differentiators' => ['co-managed helpdesk'],
    ]);
    Keyword::create(['phrase' => 'msp philadelphia', 'intent' => 'commercial', 'is_tracked' => true, 'current_position' => 3, 'search_volume' => 500]);
    SitePage::create(['type' => 'service', 'slug' => 'help', 'title' => 'Co-managed helpdesk for manufacturers', 'headline' => 'Predictable uptime, regulated manufacturers welcome', 'status' => SitePage::STATUS_PUBLISHED]);
    ContentPiece::create(['title' => 'Predictable uptime for regulated manufacturers', 'slug' => 'up1', 'content_type' => 'article', 'status' => 'published', 'published_at' => now(), 'body' => 'Uptime, compliance, manufacturers.']);
    ContentPiece::create(['title' => 'Holiday party recap', 'slug' => 'hp1', 'content_type' => 'article', 'status' => 'published', 'published_at' => now(), 'body' => 'We had cake.']);

    $positioning = app(PositioningResearchService::class)->research();
    $angles = collect($positioning['angles']);

    // We rank and no competitor is measured against us -> a cited search lead.
    $search = $angles->firstWhere('angle', 'Search visibility');
    expect($search['strength'])->toBe('lead')
        ->and($search['evidence'])->toContain('1 of 1 measured keywords');

    // The differentiator is live on our pages and unclaimed -> ownable.
    expect($angles->contains(fn (array $a) => str_contains($a['angle'], 'co-managed helpdesk') && $a['strength'] === 'lead'))->toBeTrue();

    $messaging = app(MessagingAnalysisService::class)->analysis();
    $valueProp = collect($messaging['elements'])->firstWhere('label', 'Value proposition');

    expect($valueProp['carried_by']['content'])->toBe(1)
        ->and($valueProp['carried_by']['page'])->toBe(1)
        ->and($valueProp['total'])->toBe(2)
        // The party recap carries none of the recorded messaging.
        ->and(collect($messaging['assets_without_messaging'])->pluck('title'))->toContain('Holiday party recap');

    // The research page serves the whole layer.
    $props = $this->actingAs($this->owner)->get(route('strategy.research'))->assertOk()->viewData('page')['props'];
    expect($props['positioning']['angles'])->not->toBeEmpty()
        ->and($props['messaging']['asset_counts']['content'])->toBe(2);
});
