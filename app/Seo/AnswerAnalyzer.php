<?php

namespace App\Seo;

/**
 * Turns an AI engine's answer into a visibility measurement (AIVM). One tested
 * implementation shared by every driver, so "mentioned", "position" and
 * "share of answer" mean the same thing whichever engine produced the text.
 *
 * Deterministic string heuristics, honestly approximate:
 *  - mentioned: case-insensitive brand presence;
 *  - position: the brand's rank by FIRST APPEARANCE among the entities we can
 *    actually detect (the brand + the tenant's known competitor names);
 *  - share_of_answer: percent of sentences naming the brand;
 *  - answer excerpt: the receipt every number can be checked against.
 */
class AnswerAnalyzer
{
    public const EXCERPT_LIMIT = 800;

    /**
     * @param  list<string>  $competitorNames
     */
    public function analyze(string $answer, string $brand, array $competitorNames = []): AiVisibilityResult
    {
        $mentioned = $brand !== '' && mb_stripos($answer, $brand) !== false;

        // Entities we can detect, ordered by where they first appear.
        $appearances = [];
        foreach (array_unique(array_merge([$brand], $competitorNames)) as $name) {
            if ($name === '') {
                continue;
            }
            $at = mb_stripos($answer, $name);
            if ($at !== false) {
                $appearances[$name] = $at;
            }
        }
        asort($appearances);

        $position = null;
        if ($mentioned) {
            $position = array_search($brand, array_keys($appearances), true);
            $position = $position === false ? null : $position + 1;
        }

        $competitorsFound = array_values(array_filter(
            array_keys($appearances),
            fn (string $name) => $name !== $brand,
        ));

        return new AiVisibilityResult(
            mentioned: $mentioned,
            position: $position,
            citedSources: $this->extractUrls($answer),
            competitors: $competitorsFound,
            shareOfAnswer: $this->sentenceShare($answer, $brand),
            answerExcerpt: mb_substr(trim($answer), 0, self::EXCERPT_LIMIT),
        );
    }

    /** Percent of sentences that name the brand. */
    private function sentenceShare(string $answer, string $brand): int
    {
        if ($brand === '' || trim($answer) === '') {
            return 0;
        }

        $sentences = preg_split('/(?<=[.!?])\s+|\n+/u', trim($answer)) ?: [];
        $sentences = array_values(array_filter($sentences, fn ($s) => trim($s) !== ''));

        if ($sentences === []) {
            return 0;
        }

        $naming = count(array_filter($sentences, fn (string $s) => mb_stripos($s, $brand) !== false));

        return (int) round($naming / count($sentences) * 100);
    }

    /**
     * @return list<string>
     */
    private function extractUrls(string $answer): array
    {
        preg_match_all('/https?:\/\/[^\s)\]}>"\']+/u', $answer, $matches);

        return array_slice(array_values(array_unique($matches[0])), 0, 10);
    }
}
