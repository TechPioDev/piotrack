<?php

declare(strict_types=1);

/**
 * AEO + Sales Enablement close-out (Phase 44 — AEO-001/004/006/007/019,
 * ENAB-008/014/015/016/018).
 *
 * Question research mined from real chat + keywords, featured-snippet and
 * AI-Overview readiness over published pages, conversational coverage of the
 * prompt library, the server-computed ROI calculator, proposal generation
 * from templates, and the vertical/service collateral bindings.
 */

use App\Models\AiPrompt;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatWidget;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Course;
use App\Models\Deal;
use App\Models\Keyword;
use App\Models\PageSection;
use App\Models\Pipeline;
use App\Models\SalesAsset;
use App\Models\ServiceLine;
use App\Models\SitePage;
use App\Models\Vertical;
use App\Services\Seo\AnswerEngineOptimizer;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('AeoEnab Org');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('mines question research from real chat and keywords, and adds picks to the library', function () {
    $widget = ChatWidget::create(['name' => 'Site widget', 'is_active' => true]);
    $conversation = ChatConversation::create(['chat_widget_id' => $widget->id, 'status' => 'open']);
    ChatMessage::create(['chat_conversation_id' => $conversation->id, 'role' => 'visitor', 'body' => 'How much does co-managed IT cost per user?']);
    ChatMessage::create(['chat_conversation_id' => $conversation->id, 'role' => 'visitor', 'body' => 'thanks, that helps']);
    ChatMessage::create(['chat_conversation_id' => $conversation->id, 'role' => 'agent', 'body' => 'Is there anything else?']);

    Keyword::create(['phrase' => 'how long does an it migration take', 'intent' => 'informational']);
    Keyword::create(['phrase' => 'managed it dallas', 'intent' => 'commercial']); // not a question

    AiPrompt::create(['text' => 'How much does co-managed IT cost per user?', 'is_active' => true]); // already known

    $mined = app(AnswerEngineOptimizer::class)->mineQuestions();
    $questions = collect($mined)->pluck('question');

    expect($questions)->toContain('How long does an it migration take?')
        ->and($questions)->not->toContain('How much does co-managed IT cost per user?') // deduped against the library
        ->and($questions)->not->toContain('thanks, that helps')
        ->and($questions)->not->toContain('Is there anything else?'); // agent messages never mined

    // One click lands it in the library, idempotently.
    $this->actingAs($this->owner)->post(route('seo.llmo.questions.store'), ['text' => 'How long does an it migration take?'])->assertRedirect();
    $this->actingAs($this->owner)->post(route('seo.llmo.questions.store'), ['text' => 'How long does an it migration take?'])->assertRedirect();
    expect(AiPrompt::where('text', 'How long does an it migration take?')->count())->toBe(1);
});

it('checks featured-snippet format and AI-Overview readiness on published pages', function () {
    $page = SitePage::create(['type' => 'service', 'slug' => 'backup', 'title' => 'Backup & DR', 'status' => SitePage::STATUS_PUBLISHED]);
    PageSection::create(['site_page_id' => $page->id, 'type' => 'faq', 'heading' => 'How fast can we recover from ransomware?', 'body' => 'Most clients restore core systems within four hours using image-based backups.', 'sort_order' => 1, 'is_visible' => true]);
    PageSection::create(['site_page_id' => $page->id, 'type' => 'faq', 'heading' => 'What does disaster recovery cost?', 'body' => str_repeat('A very long unstructured answer paragraph. ', 20), 'sort_order' => 2, 'is_visible' => true]);
    SitePage::create(['type' => 'landing', 'slug' => 'draft-x', 'title' => 'Draft', 'status' => SitePage::STATUS_DRAFT]);

    $aeo = app(AnswerEngineOptimizer::class);

    $targets = collect($aeo->snippetTargets());
    expect($targets)->toHaveCount(2)
        ->and($targets->firstWhere('question', 'How fast can we recover from ransomware?')['ok'])->toBeTrue()
        ->and($targets->firstWhere('question', 'What does disaster recovery cost?')['ok'])->toBeFalse()
        ->and($targets->firstWhere('question', 'What does disaster recovery cost?')['issue'])->toContain('no list');

    // AEO-019: published pages scored, weakest first, weakness named.
    $overview = $aeo->aiOverviewReadiness();
    expect($overview)->toHaveCount(1)
        ->and($overview[0]['page'])->toBe('Backup & DR')
        ->and($overview[0]['score'])->toBeGreaterThan(0);

    // The LLMO page carries the whole AEO layer.
    $props = $this->actingAs($this->owner)->get(route('seo.llmo.index'))->assertOk()->viewData('page')['props'];
    expect($props['aeo']['snippet_targets'])->toHaveCount(2)
        ->and($props['completeness'])->toHaveKey('score'); // AEO-007 entity checklist rides KnowledgeGraph completeness
});

it('measures conversational coverage of the prompt library with cited counts', function () {
    AiPrompt::create(['text' => 'best msp dallas', 'is_active' => true]);
    AiPrompt::create(['text' => 'msp pricing', 'is_active' => true]);
    AiPrompt::create(['text' => 'How do I switch IT providers without downtime?', 'is_active' => true]);

    $coverage = app(AnswerEngineOptimizer::class)->conversationalCoverage();
    $recs = implode(' ', $coverage['recommendations']);

    expect($coverage['total'])->toBe(3)
        ->and($coverage['conversational'])->toBe(1)
        ->and($recs)->toContain('1 of 3 prompts are conversational');
});

it('computes the ROI calculator server-side and stores the auditable model', function () {
    // 40 hrs × $500 = $20,000 exposure; 80% avoided = $16,000; cost $36,000/yr → net −$20,000.
    $this->actingAs($this->owner)->post(route('sales.enablement.roi'), [
        'company' => 'Clinic Group', 'employees' => 25, 'downtime_hours_year' => 40,
        'downtime_cost_hour' => 50000, 'managed_cost_month' => 300000, 'downtime_reduction_pct' => 80,
    ])->assertRedirect();

    $asset = SalesAsset::where('type', 'roi_calculator')->firstOrFail();
    expect($asset->content)->toContain('Annual downtime exposure: $20,000.00')
        ->and($asset->content)->toContain('Downtime avoided at 80% reduction: $16,000.00')
        ->and($asset->content)->toContain('Net annual benefit: $-20,000.00')
        ->and($asset->content)->toContain('Assumptions entered by the rep');
});

it('generates proposals from templates and binds collateral to vertical and service', function () {
    // ENAB-015/016: bindings persist through the endpoint.
    $vertical = Vertical::where('key', 'healthcare')->firstOrFail();
    $service = ServiceLine::where('is_active', true)->firstOrFail();
    $this->actingAs($this->owner)->post(route('sales.enablement.assets.store'), [
        'type' => 'one_pager', 'title' => 'HIPAA one-pager', 'vertical_id' => $vertical->id, 'service_line_id' => $service->id,
    ])->assertRedirect();
    $bound = SalesAsset::where('title', 'HIPAA one-pager')->firstOrFail();
    expect($bound->vertical_id)->toBe($vertical->id)->and($bound->service_line_id)->toBe($service->id);

    // ENAB-014: merge fields render from the real deal.
    $company = Company::create(['name' => 'Clinic Group']);
    $contact = Contact::create(['first_name' => 'Dana', 'last_name' => 'Reyes', 'email' => 'dana@clinic.example', 'company_id' => $company->id]);
    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $deal = Deal::create(['pipeline_id' => $pipeline->id, 'stage_id' => $pipeline->stages()->orderBy('sort_order')->firstOrFail()->id, 'name' => 'Clinic Group — managed IT', 'value' => 480000, 'status' => 'open', 'contact_id' => $contact->id]);

    $template = SalesAsset::create(['type' => 'proposal_template', 'title' => 'Standard MSP proposal', 'content' => "Prepared for {{company}} ({{contact}})\nEngagement: {{deal_name}} — {{deal_value}}\nServices: {{services}}"]);

    $this->actingAs($this->owner)->post(route('sales.enablement.proposal'), ['asset_id' => $template->id, 'deal_id' => $deal->id])->assertRedirect();

    $proposal = SalesAsset::where('type', 'proposal')->firstOrFail();
    expect($proposal->content)->toContain('Prepared for Clinic Group (Dana Reyes)')
        ->and($proposal->content)->toContain('Clinic Group — managed IT — $4,800.00')
        ->and($proposal->content)->not->toContain('{{')
        ->and($proposal->tags)->toContain('deal:'.$deal->id);

    // A non-template asset is refused as the source.
    $this->actingAs($this->owner)->from(route('sales.enablement.index'))
        ->post(route('sales.enablement.proposal'), ['asset_id' => $bound->id, 'deal_id' => $deal->id])
        ->assertRedirect()->assertSessionHasErrors('asset_id');

    // ENAB-018: sales training rides the tested TRAIN LMS — the seeded sales
    // course exists after seeding.
    $this->actingAs($this->owner)->post(route('strategy.training.seed'))->assertRedirect();
    expect(Course::where('topic', 'sales')->exists())->toBeTrue();
});
