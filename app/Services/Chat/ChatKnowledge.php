<?php

namespace App\Services\Chat;

use App\Models\ChatWidget;
use App\Models\ContentPiece;
use App\Models\PageSection;
use App\Models\ServiceLine;
use App\Models\SitePage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * What the assistant is allowed to know: the tenant's own published words.
 *
 * Every competitor grounds its AI in the customer's own content - their site,
 * their help articles, their notes - and answers from that rather than from
 * whatever the model happens to believe about IT services. This is our version
 * of it, built from what the tenant has already published in the product:
 * their website pages, their content pieces, their service lines, and a plain
 * box on the widget for the facts that live nowhere else ("we cover Reading and
 * Slough", "same-day response for managed clients").
 *
 * Nothing is crawled and nothing leaves the tenant: these are rows they own.
 * The search is deliberately plain - the words of the question, matched against
 * title and body - because it has to work identically on SQLite and MySQL, and
 * because an answer that cites the wrong page is worse than no answer.
 */
class ChatKnowledge
{
    /** How many pieces of content one answer may draw on. */
    public const MAX_SOURCES = 3;

    /** How much of each, so a long page cannot crowd out the rest. */
    public const MAX_CHARS_EACH = 700;

    /** The owner's own notes, which always go in first. */
    public const MAX_NOTES = 2000;

    /**
     * Facts for a question, and the titles they came from.
     *
     * @return array{text: string, sources: list<string>}
     */
    public function forQuestion(string $question, ChatWidget $widget): array
    {
        $parts = [];
        $sources = [];

        $notes = trim((string) ($widget->settings['knowledge'] ?? ''));
        if ($notes !== '') {
            $parts[] = "What the team says about this business:\n".Str::limit($notes, self::MAX_NOTES, '');
        }

        $services = ServiceLine::query()->orderBy('name')->limit(15)->pluck('name')->implode(', ');
        if ($services !== '') {
            $parts[] = 'Services offered: '.$services;
        }

        foreach ($this->matches($question) as $match) {
            $sources[] = $match['title'];
            $parts[] = "From “{$match['title']}”:\n".$match['body'];
        }

        return ['text' => implode("\n\n", $parts), 'sources' => $sources];
    }

    /**
     * The published pages and posts closest to what was asked.
     *
     * @return list<array{title: string, body: string, score: int}>
     */
    private function matches(string $question): array
    {
        $words = $this->words($question);
        if ($words === []) {
            return [];
        }

        $candidates = [];

        $pieces = ContentPiece::query()
            ->where('status', 'published')
            ->where(fn (Builder $q) => $this->anyWord($q, $words, ['title', 'excerpt', 'body']))
            ->limit(20)
            ->get(['title', 'excerpt', 'body']);
        foreach ($pieces as $piece) {
            $candidates[] = [
                'title' => (string) $piece->title,
                'text' => trim((string) $piece->excerpt."\n".strip_tags((string) $piece->body)),
            ];
        }

        $pages = SitePage::query()
            ->where('status', SitePage::STATUS_PUBLISHED)
            ->where(fn (Builder $q) => $this->anyWord($q, $words, ['title', 'headline', 'subheadline', 'meta_description']))
            ->limit(20)
            ->get(['id', 'title', 'headline', 'subheadline', 'meta_description']);
        $sections = PageSection::query()
            ->whereIn('site_page_id', $pages->pluck('id'))
            ->where('is_visible', true)
            ->get(['site_page_id', 'heading', 'body'])
            ->groupBy('site_page_id');
        foreach ($pages as $page) {
            $body = $sections->get($page->id, collect())
                ->map(fn (PageSection $s) => trim((string) $s->heading.' '.strip_tags((string) $s->body)))
                ->implode("\n");
            $candidates[] = [
                'title' => (string) $page->title,
                'text' => trim(implode("\n", array_filter([
                    (string) $page->headline,
                    (string) $page->subheadline,
                    (string) $page->meta_description,
                    $body,
                ]))),
            ];
        }

        $scored = [];
        foreach ($candidates as $candidate) {
            $haystack = Str::lower($candidate['title'].' '.$candidate['text']);
            $title = Str::lower($candidate['title']);
            $score = 0;
            foreach ($words as $word) {
                // A word in the title is worth more than the same word buried
                // in a page: it is what the page is actually about.
                $score += substr_count($haystack, $word) + (str_contains($title, $word) ? 3 : 0);
            }
            if ($score > 0 && trim($candidate['text']) !== '') {
                $scored[] = [
                    'title' => $candidate['title'],
                    'body' => Str::limit($this->tidy($candidate['text']), self::MAX_CHARS_EACH),
                    'score' => $score,
                ];
            }
        }

        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, self::MAX_SOURCES);
    }

    /**
     * The words worth searching for: the ones that carry the question.
     *
     * @return list<string>
     */
    private function words(string $question): array
    {
        $stop = ['what', 'when', 'where', 'which', 'your', 'you', 'the', 'and', 'for', 'are', 'can', 'does', 'do', 'with', 'have', 'about', 'how', 'much', 'many', 'from', 'that', 'this', 'there', 'they', 'would', 'could', 'should', 'please', 'tell'];

        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_unique(array_filter(
            $words,
            fn (string $word): bool => mb_strlen($word) >= 4 && ! in_array($word, $stop, true),
        )));

        return array_slice($words, 0, 6);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $words
     * @param  list<string>  $columns
     */
    private function anyWord(Builder $query, array $words, array $columns): void
    {
        foreach ($words as $word) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', '%'.$word.'%');
            }
        }
    }

    /** Collapse the whitespace a stripped page leaves behind. */
    private function tidy(string $text): string
    {
        return trim((string) preg_replace('/\s{2,}/u', ' ', $text));
    }
}
