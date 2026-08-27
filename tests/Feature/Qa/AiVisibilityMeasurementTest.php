<?php

declare(strict_types=1);

/**
 * Measurable AI Visibility (Module 07). The analyzer is one tested meaning of
 * "mentioned/position/share" shared by every driver; ChatGPT and Gemini drivers
 * are Http::fake-verified end to end; and every stored check carries the
 * answer excerpt its numbers were computed from.
 */

use App\Models\AiPrompt;
use App\Models\AiVisibilityCheck;
use App\Models\PlatformSetting;
use App\Seo\AnswerAnalyzer;
use App\Seo\Providers\GeminiAiSearchProvider;
use App\Seo\Providers\OpenAiSearchProvider;
use App\Seo\SeoProviderManager;
use App\Services\Ai\AiVisibilityDashboard;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('AI Vis Org');
    subscribeOrganization($this->org, 'enterprise');
});

it('analyzes an answer into mention, ordered position, competitors and share', function () {
    $answer = "Rival IT is a solid choice for Philadelphia manufacturers. Acme IT Services is another strong option.\n"
        .'Acme IT Services stands out for CMMC work. See https://example.com/msp-guide for details.';

    $result = app(AnswerAnalyzer::class)->analyze($answer, 'Acme IT Services', ['Rival IT', 'Ghost MSP']);

    expect($result->mentioned)->toBeTrue()
        ->and($result->position)->toBe(2) // Rival IT appears first
        ->and($result->competitors)->toBe(['Rival IT']) // Ghost MSP absent from the answer
        ->and($result->shareOfAnswer)->toBe(50) // 2 of 4 sentences name the brand
        ->and($result->citedSources)->toBe(['https://example.com/msp-guide'])
        ->and($result->answerExcerpt)->toContain('Rival IT is a solid choice');

    $missing = app(AnswerAnalyzer::class)->analyze('Nothing about anyone here.', 'Acme IT Services');
    expect($missing->mentioned)->toBeFalse()->and($missing->position)->toBeNull()->and($missing->shareOfAnswer)->toBe(0);
});

it('caps the stored evidence excerpt', function () {
    $result = app(AnswerAnalyzer::class)->analyze(str_repeat('Acme wins. ', 200), 'Acme');
    expect(mb_strlen($result->answerExcerpt))->toBeLessThanOrEqual(AnswerAnalyzer::EXCERPT_LIMIT);
});

it('drives the OpenAI API and analyzes its answer', function () {
    config(['seo.openai.key' => 'sk-test']);
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Top MSPs: Northwind IT Services leads the list, ahead of Rival IT.']]],
    ])]);

    $result = (new OpenAiSearchProvider(new AnswerAnalyzer))->query('best msp?', 'Northwind IT Services', ['Rival IT']);

    expect($result->mentioned)->toBeTrue()
        ->and($result->position)->toBe(1)
        ->and($result->competitors)->toBe(['Rival IT'])
        ->and($result->answerExcerpt)->toContain('Northwind IT Services leads');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.openai.com') && $request->hasHeader('Authorization'));
});

it('drives the Gemini API and analyzes its answer', function () {
    config(['seo.gemini.key' => 'g-test']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => 'Consider Northwind IT Services for managed IT.']]]]],
    ])]);

    $ok = (new GeminiAiSearchProvider(new AnswerAnalyzer))->query('best msp?', 'Northwind IT Services');
    expect($ok->mentioned)->toBeTrue()->and($ok->answerExcerpt)->toContain('Consider Northwind');
});

it('fails closed when the Gemini API errors', function () {
    config(['seo.gemini.key' => 'g-test']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);

    $failed = (new GeminiAiSearchProvider(new AnswerAnalyzer))->query('best msp?', 'Northwind IT Services');
    expect($failed->mentioned)->toBeFalse()->and($failed->answerExcerpt)->toBe('');
});

it('resolves keys from the platform AI console when env has none', function () {
    config(['seo.openai.key' => null]);
    PlatformSetting::put('ai.openai.api_key', 'sk-console');

    expect(OpenAiSearchProvider::key())->toBe('sk-console');
});

it('labels engines live or simulated by driver availability', function () {
    config(['seo.openai.key' => 'sk-test', 'seo.gemini.key' => null]);

    $statuses = app(SeoProviderManager::class)->engineStatuses(AiVisibilityDashboard::ENGINES);

    expect($statuses['chatgpt'])->toBe('live')
        ->and($statuses['gemini'])->toBe('simulated')
        ->and($statuses['perplexity'])->toBe('simulated');
});

it('stores evidence and per-engine provider on library runs', function () {
    config(['seo.openai.key' => 'sk-test']);
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Northwind IT Services is the leading MSP in Philadelphia.']]],
    ])]);

    app(CurrentOrganization::class)->set($this->org);
    AiPrompt::create(['text' => 'Who is the best MSP in Philadelphia?', 'is_active' => true]);
    app(AiVisibilityDashboard::class)->runLibrary('Northwind IT Services', ['chatgpt', 'perplexity']);
    app(CurrentOrganization::class)->forget();

    $chatgpt = AiVisibilityCheck::withoutGlobalScope('tenant')->where('engine', 'chatgpt')->firstOrFail();
    $perplexity = AiVisibilityCheck::withoutGlobalScope('tenant')->where('engine', 'perplexity')->firstOrFail();

    // The live engine records the real answer and its driver; the unwired one
    // stays fixture, clearly labeled simulated in its excerpt.
    expect($chatgpt->provider)->toBe('openai')
        ->and($chatgpt->answer_excerpt)->toContain('leading MSP in Philadelphia')
        ->and((bool) $chatgpt->mentioned)->toBeTrue()
        ->and($perplexity->provider)->toBe('fixture')
        ->and($perplexity->answer_excerpt)->toContain('[Simulated answer');
});

it('serves engine statuses and evidence to the visibility page', function () {
    app(CurrentOrganization::class)->set($this->org);
    AiVisibilityCheck::create([
        'prompt' => 'best msp?', 'engine' => 'chatgpt', 'provider' => 'fixture', 'brand' => 'X',
        'mentioned' => true, 'recommended' => false, 'position' => 2,
        'cited_sources' => [], 'competitors' => [], 'share_of_answer' => 40,
        'answer_excerpt' => 'X ranks second in this simulated answer.', 'checked_at' => now(),
    ]);
    app(CurrentOrganization::class)->forget();

    $props = $this->actingAs($this->owner)->get(route('ai.visibility.index'))->assertOk()->viewData('page')['props'];

    expect($props['engineStatuses'])->toHaveKeys(AiVisibilityDashboard::ENGINES)
        ->and($props['evidence'][0]['answer_excerpt'])->toContain('ranks second');
});
