<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatWidget;
use App\Models\Deal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Website chat reporting (§41–§43).
 *
 * Everything here is counted from what actually happened — widget events and
 * conversation state — never estimated. Where a number cannot be known (no deal
 * is linked yet, nobody has reached a step) it is reported as zero rather than
 * inferred, so a tenant can trust the funnel enough to act on it.
 */
class ChatAnalyticsService
{
    /**
     * Headline counts for the period.
     *
     * @return array<string, int|float>
     */
    public function summary(?Carbon $since = null, ?int $widgetId = null): array
    {
        $events = $this->eventCounts($since, $widgetId);

        $conversations = $this->conversations($since, $widgetId);
        $started = (clone $conversations)->count();
        $completed = (clone $conversations)->whereIn('status', ['qualified', 'converted', 'closed'])
            ->whereNotNull('contact_id')->count();
        $leads = (clone $conversations)->whereNotNull('lead_id')->count();
        $qualified = (clone $conversations)->where('lead_score', '>=', 60)->count();
        $meetings = $events['meeting'] ?? 0;

        $impressions = $events['impression'] ?? 0;
        $opens = $events['open'] ?? 0;

        return [
            'impressions' => $impressions,
            'opens' => $opens,
            'conversations' => $started,
            'completed' => $completed,
            'leads' => $leads,
            'qualified' => $qualified,
            'meetings' => $meetings,
            'open_rate' => $this->rate($opens, $impressions),
            'engagement_rate' => $this->rate($started, $opens),
            'completion_rate' => $this->rate($completed, $started),
            'lead_rate' => $this->rate($leads, $started),
            'revenue' => $this->revenue($since, $widgetId),
        ];
    }

    /**
     * The §42 ladder: each rung with the conversion from the rung above.
     *
     * @return list<array{stage: string, count: int, rate: ?float}>
     */
    public function funnel(?Carbon $since = null, ?int $widgetId = null): array
    {
        $summary = $this->summary($since, $widgetId);

        $rungs = [
            ['stage' => 'Widget views', 'count' => (int) $summary['impressions']],
            ['stage' => 'Chat opens', 'count' => (int) $summary['opens']],
            ['stage' => 'Conversations', 'count' => (int) $summary['conversations']],
            ['stage' => 'Leads', 'count' => (int) $summary['leads']],
            ['stage' => 'Qualified leads', 'count' => (int) $summary['qualified']],
            ['stage' => 'Meetings', 'count' => (int) $summary['meetings']],
        ];

        $out = [];
        foreach ($rungs as $i => $rung) {
            $previous = $i === 0 ? null : $rungs[$i - 1]['count'];
            $out[] = [
                'stage' => $rung['stage'],
                'count' => $rung['count'],
                'rate' => $previous === null ? null : $this->rate($rung['count'], $previous),
            ];
        }

        return $out;
    }

    /**
     * Where visitors stop answering (§43).
     *
     * A conversation's cursor is the step it is waiting on, so an unfinished
     * conversation is sitting at exactly the question that lost it. Reported as a
     * share of everyone who reached that step, which is the number worth acting on.
     *
     * @return list<array{node: string, label: string, reached: int, abandoned: int, rate: float}>
     */
    public function dropOff(?Carbon $since = null, ?int $widgetId = null): array
    {
        $conversations = $this->conversations($since, $widgetId)
            ->get(['id', 'chat_widget_id', 'answers', 'status', 'contact_id']);

        if ($conversations->isEmpty()) {
            return [];
        }

        // Labels come from each widget's own flow, so a step reads as its question.
        // Answers are stored under a step's FIELD name while the cursor holds its
        // NODE id, so the two must be mapped onto each other or the same question
        // appears twice — once as "email", once as "What is your email?".
        $labels = [];
        $nodeForField = [];
        foreach (ChatWidget::query()->get(['id', 'flow']) as $widget) {
            foreach ((array) (($widget->flow['nodes'] ?? [])) as $id => $node) {
                if (! is_array($node)) {
                    continue;
                }
                $labels[$id] ??= (string) ($node['text'] ?? $id);
                $field = (string) ($node['field'] ?? '');
                if ($field !== '') {
                    $nodeForField[$field] ??= (string) $id;
                }
            }
        }

        $reached = [];
        $abandoned = [];

        foreach ($conversations as $conversation) {
            $answers = $conversation->answers ?? [];
            $cursor = $answers['_node'] ?? null;

            // Every stored field means its step was answered, i.e. reached. Count
            // it against the step that asked, not the field it was stored in.
            foreach (array_keys($answers) as $key) {
                if (str_starts_with((string) $key, '_')) {
                    continue;
                }
                $node = $nodeForField[(string) $key] ?? (string) $key;
                $reached[$node] = ($reached[$node] ?? 0) + 1;
            }

            if ($cursor !== null && ! str_starts_with((string) $cursor, '_')) {
                $reached[(string) $cursor] = ($reached[(string) $cursor] ?? 0) + 1;

                // Still parked on a question, and never became a contact: lost here.
                if ($conversation->contact_id === null) {
                    $abandoned[(string) $cursor] = ($abandoned[(string) $cursor] ?? 0) + 1;
                }
            }
        }

        $rows = [];
        foreach ($reached as $node => $count) {
            $lost = $abandoned[$node] ?? 0;
            $rows[] = [
                'node' => (string) $node,
                'label' => $labels[$node] ?? (string) $node,
                'reached' => $count,
                'abandoned' => $lost,
                'rate' => $this->rate($lost, $count),
            ];
        }

        // Worst leak first — that is what a tenant should fix next.
        usort($rows, fn (array $a, array $b) => [$b['abandoned'], $b['rate']] <=> [$a['abandoned'], $a['rate']]);

        return $rows;
    }

    /**
     * Per-widget comparison. Widgets sharing an `experiment` key are variants of
     * one test (§44) — the numbers are reported plainly, with no claim of
     * statistical significance, because at these volumes that claim would be a lie.
     *
     * @return list<array{id: int, name: string, experiment: ?string, variant: ?string, conversations: int, leads: int, qualified: int, lead_rate: float}>
     */
    public function byWidget(?Carbon $since = null): array
    {
        return ChatWidget::query()
            ->get(['id', 'name', 'settings'])
            ->map(function (ChatWidget $widget) use ($since) {
                $base = $this->conversations($since, $widget->id);
                $started = (clone $base)->count();
                $leads = (clone $base)->whereNotNull('lead_id')->count();

                return [
                    'id' => $widget->id,
                    'name' => $widget->name,
                    'experiment' => ($widget->settings ?? [])['experiment'] ?? null,
                    'variant' => ($widget->settings ?? [])['variant'] ?? null,
                    'conversations' => $started,
                    'leads' => $leads,
                    'qualified' => (clone $base)->where('lead_score', '>=', 60)->count(),
                    'lead_rate' => $this->rate($leads, $started),
                ];
            })
            ->values()
            ->all();
    }

    /** Revenue from won deals traceable to a chat conversation (minor units). */
    private function revenue(?Carbon $since, ?int $widgetId): int
    {
        $contactIds = $this->conversations($since, $widgetId)
            ->whereNotNull('contact_id')
            ->pluck('contact_id')
            ->all();

        if ($contactIds === []) {
            return 0;
        }

        return (int) Deal::query()
            ->whereIn('contact_id', $contactIds)
            ->whereHas('stage', fn ($q) => $q->where('is_won', true))
            ->sum('value');
    }

    /** @return Builder<ChatConversation> */
    private function conversations(?Carbon $since, ?int $widgetId)
    {
        return ChatConversation::query()
            ->where('is_preview', false)
            ->when($since !== null, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($widgetId !== null, fn ($q) => $q->where('chat_widget_id', $widgetId));
    }

    /** @return array<string, int> */
    private function eventCounts(?Carbon $since, ?int $widgetId): array
    {
        return ChatEvent::query()
            ->when($since !== null, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($widgetId !== null, fn ($q) => $q->where('chat_widget_id', $widgetId))
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
    }
}
