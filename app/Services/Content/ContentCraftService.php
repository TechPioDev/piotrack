<?php

namespace App\Services\Content;

use App\Models\ContentPiece;
use App\Models\Keyword;
use App\Models\KeywordRanking;
use App\Models\Vertical;
use App\Services\Ai\AiGateway;
use App\Services\Seo\AnswerEngineOptimizer;

/**
 * CONT-033..036: the craft layer over content the tenant actually holds.
 * Refresh and expansion are COMPUTED queues — age, thin word counts, real
 * ranking drops, unanswered mined questions — each entry citing its numbers.
 * Conversion/technical craft checks are transparent heuristics, and AI copy
 * assists are DRAFTS through the tested gateway that never publish anything.
 */
class ContentCraftService
{
    /** Days after which a published piece is refresh-eligible on age alone. */
    public const STALE_DAYS = 180;

    public const THIN_WORDS = 300;

    /** Word floor below which a ranking-worthy piece is an expansion candidate. */
    public const EXPANSION_WORDS = 900;

    /** A ranking fall of at least this many positions flags the piece. */
    public const DROP_POSITIONS = 10;

    public function __construct(
        private AiGateway $gateway,
        private AnswerEngineOptimizer $aeo,
    ) {}

    /**
     * CONT-035: published pieces that need a refresh, with the reasons named.
     *
     * @return list<array{id: int, title: string, reasons: list<string>}>
     */
    public function refreshQueue(): array
    {
        $queue = [];

        foreach (ContentPiece::whereNotNull('published_at')->get() as $piece) {
            $reasons = [];

            $ageDays = (int) abs(now()->diffInDays($piece->published_at));
            if ($ageDays >= self::STALE_DAYS) {
                $reasons[] = __('published :days days ago', ['days' => $ageDays]);
            }

            $words = str_word_count(strip_tags((string) $piece->body));
            if ($words < self::THIN_WORDS) {
                $reasons[] = __('thin (:words words)', ['words' => $words]);
            }

            if ($piece->optimization_score !== null && (int) $piece->optimization_score < 50) {
                $reasons[] = __('optimization score :score', ['score' => $piece->optimization_score]);
            }

            $drop = $this->rankingDrop((string) $piece->target_keyword);
            if ($drop !== null) {
                $reasons[] = __('":keyword" fell from #:best to #:now', ['keyword' => $piece->target_keyword, 'best' => $drop['best'], 'now' => $drop['now']]);
            }

            if ($reasons !== []) {
                $queue[] = ['id' => $piece->id, 'title' => (string) $piece->title, 'reasons' => $reasons];
            }
        }

        return $queue;
    }

    /**
     * CONT-036: where existing content should grow — thin ranking-worthy
     * pieces, and mined audience questions no published piece answers.
     *
     * @return array{thin_pieces: list<array{id: int, title: string, words: int, keyword: string|null}>, unanswered_questions: list<array{question: string, source: string}>}
     */
    public function expansionQueue(): array
    {
        $thin = [];
        $published = ContentPiece::whereNotNull('published_at')->get();

        foreach ($published as $piece) {
            $words = str_word_count(strip_tags((string) $piece->body));
            if ($words < self::EXPANSION_WORDS && $piece->target_keyword !== null && $piece->target_keyword !== '') {
                $thin[] = ['id' => $piece->id, 'title' => (string) $piece->title, 'words' => $words, 'keyword' => $piece->target_keyword];
            }
        }

        // Questions the audience actually asked (P44 mining) that no published
        // piece covers — checked against real titles and bodies.
        $corpus = mb_strtolower($published->map(fn (ContentPiece $p) => $p->title.' '.$p->body)->implode(' '));
        $unanswered = [];
        foreach ($this->aeo->mineQuestions() as $mined) {
            $core = mb_strtolower(trim((string) $mined['question'], " ?\t"));
            if ($core !== '' && ! str_contains($corpus, $core)) {
                $unanswered[] = ['question' => (string) $mined['question'], 'source' => (string) $mined['source']];
            }
        }

        return ['thin_pieces' => $thin, 'unanswered_questions' => array_slice($unanswered, 0, 10)];
    }

    /**
     * CONT-033/034: deterministic conversion + technical craft checks —
     * transparent heuristics, each with its fix.
     *
     * @return list<array{key: string, kind: string, status: string, detail: string}>
     */
    public function craftChecks(ContentPiece $piece): array
    {
        $body = strip_tags((string) $piece->body);
        $lower = mb_strtolower($body);
        $words = max(1, str_word_count($body));

        $checks = [];

        // Conversion craft.
        $hasCta = ($piece->cta !== null && $piece->cta !== '') || preg_match('/\b(book|schedule|contact|get started|talk to|request)\b/i', $body) === 1;
        $checks[] = ['key' => 'cta', 'kind' => 'conversion', 'status' => $hasCta ? 'pass' : 'warn',
            'detail' => $hasCta ? __('A call to action is present.') : __('No CTA found - tell the reader what to do next.')];

        $youDensity = round(substr_count($lower, 'you') / $words * 100, 1);
        $checks[] = ['key' => 'reader_address', 'kind' => 'conversion', 'status' => $youDensity >= 1 ? 'pass' : 'warn',
            'detail' => __('":you" density :d per 100 words (aim >= 1).', ['you' => 'you', 'd' => $youDensity])];

        $hasNumbers = preg_match('/\d/', $body) === 1;
        $checks[] = ['key' => 'specificity', 'kind' => 'conversion', 'status' => $hasNumbers ? 'pass' : 'warn',
            'detail' => $hasNumbers ? __('Concrete figures present.') : __('No concrete figures - specifics convert better than adjectives.')];

        // MSP technical craft.
        preg_match_all('/\b([A-Z]{2,6})\b/', $body, $matches);
        $acronyms = array_unique($matches[1] ?? []);
        $unexpanded = array_values(array_filter($acronyms, fn (string $a) => ! str_contains($body, '('.$a.')') && ! preg_match('/'.preg_quote($a, '/').'\s*\(/', $body)));
        $checks[] = ['key' => 'acronyms', 'kind' => 'technical', 'status' => count($unexpanded) <= 2 ? 'pass' : 'warn',
            'detail' => count($unexpanded) <= 2
                ? __('Acronyms are explained (:count unexpanded).', ['count' => count($unexpanded)])
                : __(':count acronyms never expanded (:list) - expand each on first use.', ['count' => count($unexpanded), 'list' => implode(', ', array_slice($unexpanded, 0, 5))])];

        $sentences = max(1, preg_match_all('/[.!?]+\s/', $body));
        $avgSentence = round($words / $sentences, 1);
        $checks[] = ['key' => 'sentence_length', 'kind' => 'technical', 'status' => $avgSentence <= 28 ? 'pass' : 'warn',
            'detail' => __('Average sentence :n words (aim <= 28).', ['n' => $avgSentence])];

        $depth = str_word_count($body);
        $checks[] = ['key' => 'depth', 'kind' => 'technical', 'status' => $depth >= self::THIN_WORDS ? 'pass' : 'warn',
            'detail' => __(':words words (:floor-word floor for technical depth).', ['words' => $depth, 'floor' => self::THIN_WORDS])];

        return $checks;
    }

    /**
     * CONT-033/034: an AI DRAFT (headline / opening / CTA) through the tested
     * gateway. Returned to the editor, never persisted or published.
     */
    public function draftCopy(ContentPiece $piece, string $focus): string
    {
        return $this->gateway->run('content.copy', 'content.copy', [
            'focus' => $focus === 'technical' ? 'MSP technical' : 'conversion',
            'title' => (string) $piece->title,
            'keyword' => $piece->target_keyword ?: 'none recorded',
            'vertical' => (string) (Vertical::find($piece->vertical_id)?->name ?? 'MSP buyers in general'),
            'excerpt' => $piece->excerpt ?: mb_substr(strip_tags((string) $piece->body), 0, 300),
        ])->text;
    }

    /**
     * @return array{best: int, now: int}|null
     */
    private function rankingDrop(string $keyword): ?array
    {
        if ($keyword === '') {
            return null;
        }

        $tracked = Keyword::where('phrase', $keyword)->where('is_tracked', true)->first();
        if ($tracked === null) {
            return null;
        }

        $positions = KeywordRanking::where('keyword_id', $tracked->id)
            ->where('is_competitor', false)->whereNotNull('position')
            ->orderBy('checked_at')->pluck('position')->map(fn ($p) => (int) $p);

        if ($positions->count() < 2) {
            return null;
        }

        $now = $positions->last();
        $best = $positions->slice(0, -1)->min();

        return $now - $best >= self::DROP_POSITIONS ? ['best' => $best, 'now' => $now] : null;
    }
}
