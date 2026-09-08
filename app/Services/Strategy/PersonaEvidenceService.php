<?php

namespace App\Services\Strategy;

use App\Models\ChatMessage;
use App\Models\Contact;
use App\Models\Deal;

/**
 * STRAT-007: the first-party evidence buyer personas are developed against —
 * who actually buys (buying roles, titles at won-deal companies) and what
 * they actually ask (visitor chat questions). The persona narrative stays
 * rep-authored; this is the data beside the blank page.
 */
class PersonaEvidenceService
{
    /**
     * @return array{buying_roles: array<string, int>, won_titles: array<string, int>, questions: list<string>, contacts_sampled: int, won_deals: int}
     */
    public function evidence(): array
    {
        $roleContacts = Contact::whereNotNull('buying_role')->get();

        // Titles held by contacts at companies where a deal actually closed won.
        $wonCompanyIds = Deal::where('status', 'won')->whereNotNull('company_id')
            ->pluck('company_id')->unique();
        $wonTitles = Contact::whereIn('company_id', $wonCompanyIds)->whereNotNull('title')->get()
            ->countBy(fn (Contact $c) => (string) $c->title)
            ->sortDesc()->take(8)->all();

        $questions = ChatMessage::where('role', 'visitor')->latest('id')->limit(200)->pluck('body')
            ->map(fn ($body) => trim((string) $body))
            ->filter(fn (string $body) => str_ends_with($body, '?') && mb_strlen($body) >= 12)
            ->unique(fn (string $body) => mb_strtolower($body))
            ->take(6)->values()->all();

        return [
            'buying_roles' => $roleContacts->countBy(fn (Contact $c) => (string) $c->buying_role)->sortDesc()->all(),
            'won_titles' => $wonTitles,
            'questions' => $questions,
            'contacts_sampled' => $roleContacts->count(),
            'won_deals' => Deal::where('status', 'won')->count(),
        ];
    }
}
