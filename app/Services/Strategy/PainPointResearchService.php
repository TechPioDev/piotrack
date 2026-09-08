<?php

namespace App\Services\Strategy;

use App\Models\ChatMessage;
use App\Models\Review;
use App\Models\SocialInteraction;
use App\Models\Ticket;

/**
 * STRAT-008: pain-point research over signals the platform actually holds —
 * visitor chat messages, negative reviews, negative social interactions and
 * open support tickets — bucketed by a deterministic keyword theme scan.
 * A transparent heuristic stated as such in the UI; rep-recorded interview
 * findings live beside it as strategy items, never replaced by it.
 */
class PainPointResearchService
{
    /** Theme => the keywords that place a signal in it. */
    public const THEMES = [
        'Security & breaches' => ['breach', 'ransomware', 'phish', 'hack', 'malware', 'virus', 'security'],
        'Downtime & reliability' => ['downtime', 'outage', 'down', 'crash', 'unstable', 'unreliable'],
        'Cost & budget' => ['cost', 'price', 'pricing', 'expensive', 'budget', 'overpaying'],
        'Response time & support' => ['response', 'waiting', 'unanswered', 'slow', 'ticket', 'callback'],
        'Compliance & audits' => ['compliance', 'hipaa', 'cmmc', 'audit', 'regulation', 'insurance'],
        'Staffing & expertise' => ['staff', 'turnover', 'expertise', 'skill', 'shortage', 'hire'],
    ];

    private const QUOTE_LIMIT = 3;

    private const QUOTE_CHARS = 160;

    /**
     * @return array{themes: list<array{theme: string, total: int, by_source: array<string, int>, quotes: list<array{text: string, source: string}>}>, sources: array<string, int>, total_signals: int}
     */
    public function themes(): array
    {
        $signals = $this->signals();

        $themes = [];
        foreach (self::THEMES as $theme => $keywords) {
            $matched = array_values(array_filter(
                $signals,
                fn (array $s) => $this->matches($s['text'], $keywords)
            ));

            $bySource = [];
            foreach ($matched as $signal) {
                $bySource[$signal['source']] = ($bySource[$signal['source']] ?? 0) + 1;
            }

            $themes[] = [
                'theme' => $theme,
                'total' => count($matched),
                'by_source' => $bySource,
                'quotes' => array_map(
                    fn (array $s) => [
                        'text' => mb_strlen($s['text']) > self::QUOTE_CHARS
                            ? mb_substr($s['text'], 0, self::QUOTE_CHARS - 1).'…'
                            : $s['text'],
                        'source' => $s['source'],
                    ],
                    array_slice($matched, 0, self::QUOTE_LIMIT)
                ),
            ];
        }

        usort($themes, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        $sources = [];
        foreach ($signals as $signal) {
            $sources[$signal['source']] = ($sources[$signal['source']] ?? 0) + 1;
        }

        return ['themes' => $themes, 'sources' => $sources, 'total_signals' => count($signals)];
    }

    /**
     * Every first-party signal worth scanning, labeled with where it came from.
     *
     * @return list<array{text: string, source: string}>
     */
    private function signals(): array
    {
        $signals = [];

        foreach (ChatMessage::where('role', 'visitor')->latest('id')->limit(500)->pluck('body') as $body) {
            $signals[] = ['text' => trim((string) $body), 'source' => 'chat'];
        }
        foreach (Review::where('sentiment', 'negative')->whereNotNull('body')->latest('id')->limit(200)->pluck('body') as $body) {
            $signals[] = ['text' => trim((string) $body), 'source' => 'review'];
        }
        foreach (SocialInteraction::where('sentiment', 'negative')->latest('id')->limit(200)->pluck('body') as $body) {
            $signals[] = ['text' => trim((string) $body), 'source' => 'social'];
        }
        foreach (Ticket::where('status', '!=', 'resolved')->latest('id')->limit(200)->get(['subject', 'body']) as $ticket) {
            $signals[] = ['text' => trim((string) $ticket->subject.' '.(string) $ticket->body), 'source' => 'ticket'];
        }

        return array_values(array_filter($signals, fn (array $s) => $s['text'] !== ''));
    }

    /**
     * @param  list<string>  $keywords
     */
    private function matches(string $text, array $keywords): bool
    {
        $lower = mb_strtolower($text);
        foreach ($keywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
