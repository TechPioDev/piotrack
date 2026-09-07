<?php

namespace App\Services\Seo;

use App\Models\AiPrompt;
use App\Models\ChatMessage;
use App\Models\Keyword;
use App\Models\PageSection;
use App\Models\SitePage;

/**
 * AEO-001/004/006/019: the optimization layer over the Stage 7 machinery —
 * question research mined from what the tenant's audience actually asks,
 * featured-snippet formatting checks on published pages, conversational
 * coverage of the question inventory, and AI-Overview readiness via the LLMO
 * scorer. Entity optimization (AEO-007) rides KnowledgeGraphService.
 * Everything computed first-party; live SERP/AI-Overview presence stays with
 * the AI-visibility engine seam.
 */
class AnswerEngineOptimizer
{
    private const QUESTION_STARTS = ['who', 'what', 'how', 'why', 'when', 'where', 'which', 'can', 'does', 'do', 'is', 'are', 'should'];

    /** A featured snippet's answer paragraph budget. */
    public const SNIPPET_CHARS = 320;

    public function __construct(private LlmoContentScorer $scorer) {}

    /**
     * AEO-001: questions the tenant is ALREADY being asked — visitor chat
     * messages ending in "?" and question-modified tracked keywords — that
     * are not yet in the prompt library.
     *
     * @return list<array{question: string, source: string}>
     */
    public function mineQuestions(): array
    {
        $known = AiPrompt::pluck('text')->map(fn ($t) => mb_strtolower(trim((string) $t)))->all();

        $suggestions = [];

        ChatMessage::where('role', 'visitor')->latest('id')->limit(500)->pluck('body')
            ->each(function ($body) use (&$suggestions, $known) {
                $body = trim((string) $body);
                if (str_ends_with($body, '?') && mb_strlen($body) >= 12 && mb_strlen($body) <= 160
                    && ! in_array(mb_strtolower($body), $known, true)) {
                    $suggestions[mb_strtolower($body)] = ['question' => $body, 'source' => 'chat'];
                }
            });

        Keyword::pluck('phrase')->each(function ($phrase) use (&$suggestions, $known) {
            $phrase = trim((string) $phrase);
            $first = mb_strtolower(strtok($phrase, ' ') ?: '');
            if (in_array($first, self::QUESTION_STARTS, true)) {
                $question = ucfirst($phrase).'?';
                if (! in_array(mb_strtolower($question), $known, true)) {
                    $suggestions[mb_strtolower($question)] = ['question' => $question, 'source' => 'keyword'];
                }
            }
        });

        return array_values($suggestions);
    }

    /**
     * AEO-004: featured-snippet formatting per published page — a question
     * heading with a snippet-length answer directly after it is the format
     * Google lifts; each failure is named.
     *
     * @return list<array{page: string, slug: string, question: string, ok: bool, issue: string|null}>
     */
    public function snippetTargets(): array
    {
        $rows = [];

        SitePage::with('sections')->where('status', SitePage::STATUS_PUBLISHED)->get()
            ->each(function (SitePage $page) use (&$rows) {
                $sections = $page->sections->filter(fn (PageSection $s) => $s->is_visible)->values();

                foreach ($sections as $section) {
                    $heading = trim((string) $section->heading);
                    if (! str_ends_with($heading, '?')) {
                        continue;
                    }

                    $body = trim((string) $section->body);
                    $issue = null;
                    if ($body === '') {
                        $issue = __('No answer under the question heading.');
                    } elseif (mb_strlen($body) > self::SNIPPET_CHARS && ! str_contains($body, "\n-") && ! preg_match('/\n\d+\./', $body)) {
                        $issue = __('Answer runs :n characters with no list — open with a :max-character direct answer or break into steps.', ['n' => mb_strlen($body), 'max' => self::SNIPPET_CHARS]);
                    }

                    $rows[] = [
                        'page' => $page->title,
                        'slug' => $page->slug,
                        'question' => $heading,
                        'ok' => $issue === null,
                        'issue' => $issue,
                    ];
                }
            });

        return $rows;
    }

    /**
     * AEO-006: how conversational the question inventory is — engines answer
     * spoken-style queries, so coverage is measured, not assumed.
     *
     * @return array{total: int, conversational: int, long_tail: int, recommendations: list<string>}
     */
    public function conversationalCoverage(): array
    {
        $prompts = AiPrompt::where('is_active', true)->pluck('text');

        $conversational = $prompts->filter(function ($text) {
            $first = mb_strtolower(strtok(trim((string) $text), ' ') ?: '');

            return in_array($first, self::QUESTION_STARTS, true);
        })->count();

        $longTail = $prompts->filter(fn ($t) => str_word_count((string) $t) >= 5)->count();

        $recommendations = [];
        if ($prompts->count() > 0 && $conversational < (int) ceil($prompts->count() / 2)) {
            $recommendations[] = __('Only :c of :t prompts are conversational (who/what/how…) — spoken-style queries are what answer engines actually receive.', ['c' => $conversational, 't' => $prompts->count()]);
        }
        if ($prompts->count() > 0 && $longTail < (int) ceil($prompts->count() / 2)) {
            $recommendations[] = __('Only :c of :t prompts run five words or more — short heads rarely trigger answer features.', ['c' => $longTail, 't' => $prompts->count()]);
        }

        return [
            'total' => $prompts->count(),
            'conversational' => $conversational,
            'long_tail' => $longTail,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * AEO-019: AI-Overview readiness — the LLMO scorer over each published
     * page, weakest first. Extractable, structured, fact-rich content is what
     * AI Overviews cite; the score says which pages are not.
     *
     * @return list<array{page: string, slug: string, score: int, weakest: string|null}>
     */
    public function aiOverviewReadiness(): array
    {
        return SitePage::with('sections')->where('status', SitePage::STATUS_PUBLISHED)->get()
            ->map(function (SitePage $page) {
                $html = '<article><h1>'.e($page->title).'</h1>'
                    .$page->sections->filter(fn (PageSection $s) => $s->is_visible)
                        ->map(fn (PageSection $s) => '<h2>'.e((string) $s->heading).'</h2><p>'.nl2br(e((string) $s->body)).'</p>')
                        ->implode('')
                    .'</article>';

                $result = $this->scorer->score($html);
                $failing = collect($result['factors'])->first(fn (array $f) => ! $f['ok']);

                return [
                    'page' => $page->title,
                    'slug' => $page->slug,
                    'score' => $result['score'],
                    'weakest' => $failing['detail'] ?? null,
                ];
            })
            ->sortBy('score')->values()->all();
    }
}
