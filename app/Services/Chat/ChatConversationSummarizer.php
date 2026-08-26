<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Services\Ai\AiGateway;

/**
 * The handoff summary (CHAT-044): a few sentences an agent reads before
 * taking over, instead of scrolling the transcript under a waiting visitor.
 *
 * Runs through the same gateway as every AI feature — credit caps, cost
 * recording, audit — and fails to null rather than to an error: a missing
 * summary costs the agent thirty seconds of reading; an exception here would
 * cost them the page.
 */
class ChatConversationSummarizer
{
    public function __construct(private readonly AiGateway $ai) {}

    /**
     * Generate and store a summary. Returns the summary, the cached one when
     * nothing new has been said since it was written, or null when the AI is
     * unavailable — callers render "no summary yet", never an error.
     */
    public function summarize(ChatConversation $conversation): ?string
    {
        $messages = $conversation->messages()
            // Internal notes stay internal — same rule as the visitor poll.
            // The summary travels further than the inbox (alerts, CRM), so a
            // note leaking into it would undo that isolation.
            ->whereIn('role', ['visitor', 'bot', 'agent'])
            ->orderBy('id')
            ->get(['role', 'body', 'created_at']);

        if ($messages->isEmpty()) {
            return null;
        }

        // Nothing said since the last summary: the cached one is still true.
        if ($conversation->summary !== null
            && $conversation->summary_generated_at !== null
            && ! $messages->last()->created_at->gt($conversation->summary_generated_at)) {
            return $conversation->summary;
        }

        $transcript = $messages
            ->map(fn ($m) => strtoupper((string) $m->role).': '.$m->body)
            ->implode("\n");

        $answers = collect($conversation->answers ?? [])
            ->except(['_node', '_consent', '_priority', '_ai_turns', '_booking'])
            ->map(fn ($value, $key) => str_replace('_', ' ', (string) $key).': '.$value)
            ->implode('; ');

        try {
            $summary = trim($this->ai->run('chat.summarize', 'chat.summarize', [
                'transcript' => mb_substr($transcript, -6000),
                'answers' => $answers !== '' ? $answers : 'none captured yet',
            ])->text);
        } catch (\Throwable) {
            return null;
        }

        if ($summary === '') {
            return null;
        }

        $conversation->forceFill([
            'summary' => mb_substr($summary, 0, 2000),
            'summary_generated_at' => now(),
        ])->save();

        return $conversation->summary;
    }
}
