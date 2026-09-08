<?php

namespace App\Services\Ai;

use App\Crm\Contracts\EnrichmentProvider;
use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiScore;
use App\Models\Call;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Services\Sales\IntentService;
use Illuminate\Database\Eloquent\Model;

/**
 * The AI sales agent (AISA). Every call runs through {@see AiGateway}, so all of
 * it is prompt-versioned, cost-tracked and credit-limited.
 *
 * The division is deliberate:
 *  - Work that only INFORMS a human (qualification, summaries, drafts, scores,
 *    recommendations, research) is returned directly.
 *  - Work that would CHANGE DATA or REACH A THIRD PARTY (CRM updates, booking,
 *    sending email) is only ever PROPOSED via {@see AiActionService} and waits
 *    for a human to confirm it.
 *
 * AI scores are advisory: they are returned to the caller and never overwrite
 * the deterministic Stage 10 `lead_score`.
 */
class AiSalesAgent
{
    public function __construct(
        private AiGateway $gateway,
        private AiActionService $actions,
        private IntentService $intent,
        private ProspectSiteReader $sites,
    ) {}

    /**
     * AI lead qualification (AISA-001).
     *
     * @return array{qualified: bool, reason: string, raw: string}
     */
    public function qualify(Contact $contact): array
    {
        $completion = $this->gateway->run('sales.qualify', 'sales.qualify', [
            'name' => $contact->fullName(),
            'company' => $contact->company_id !== null ? $contact->company->name : 'unknown',
            'lifecycle' => $contact->lifecycle_stage,
            'score' => $contact->lead_score,
            'notes' => 'Source: '.($contact->lead_source ?? 'unknown'),
        ]);

        $text = $completion->text;

        return [
            'qualified' => (bool) preg_match('/QUALIFIED:\s*yes/i', $text),
            'reason' => $this->field($text, 'REASON') ?? '',
            'raw' => $text,
        ];
    }

    /**
     * Website chatbot reply (AISA-002). Persists both sides of the exchange.
     */
    public function reply(AiConversation $conversation, string $message): AiMessage
    {
        AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'body' => $message,
        ]);

        $history = $conversation->messages()->orderBy('id')->get()
            ->map(fn (AiMessage $m) => ucfirst($m->role).': '.$m->body)
            ->implode("\n");

        $completion = $this->gateway->run('sales.chat', 'sales.chat_reply', [
            'history' => $history,
            'message' => $message,
        ]);

        return AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'ai_request_id' => $this->gateway->lastRequestId(),
            'role' => 'assistant',
            'body' => $completion->text,
        ]);
    }

    /**
     * AI conversation summary (AISA-003), stored on the conversation.
     */
    public function summarizeConversation(AiConversation $conversation): string
    {
        $transcript = $conversation->messages()->orderBy('id')->get()
            ->map(fn (AiMessage $m) => ucfirst($m->role).': '.$m->body)
            ->implode("\n");

        $summary = $this->gateway->run('sales.summarize', 'sales.summarize', ['transcript' => $transcript])->text;
        $conversation->update(['summary' => $summary]);

        return $summary;
    }

    /**
     * AI sales-call summary (AISA-014), stored on the call.
     */
    public function summarizeCall(Call $call): string
    {
        $transcript = $call->transcript ?: "A {$call->duration_seconds}s {$call->direction} call from {$call->source} ({$call->status}).";
        $summary = $this->gateway->run('sales.call_summary', 'sales.summarize', ['transcript' => $transcript])->text;

        $call->update(['summary' => $summary]);

        return $summary;
    }

    /**
     * AI email creation (AISA-008) and follow-up (AISA-009). Returns a DRAFT —
     * nothing is sent. Sending is a separate, confirmed action.
     */
    public function draftEmail(Contact $contact, string $purpose, string $context = ''): string
    {
        return $this->gateway->run('sales.draft_email', 'sales.draft_email', [
            'name' => $contact->fullName(),
            'company' => $contact->company_id !== null ? $contact->company->name : 'their company',
            'purpose' => $purpose,
            'context' => $context,
        ])->text;
    }

    public function followUp(Contact $contact): string
    {
        return $this->draftEmail($contact, 'follow up after no reply', 'Lifecycle: '.$contact->lifecycle_stage);
    }

    /**
     * AI objection handling (AISA-010).
     */
    public function handleObjection(string $objection): string
    {
        return $this->gateway->run('sales.objection', 'sales.objection', ['objection' => $objection])->text;
    }

    /**
     * AI next-best-action recommendation (AISA-011).
     */
    public function nextBestAction(Contact $contact): string
    {
        return $this->gateway->run('sales.next_action', 'sales.next_action', [
            'name' => $contact->fullName(),
            'lifecycle' => $contact->lifecycle_stage,
            'score' => $contact->lead_score,
            'signals' => 'intent score '.$this->intent->intentScore($contact),
        ])->text;
    }

    /**
     * AI lead research (AISA-005) / account research (AISA-007). Grounded in the
     * data we hold PLUS the prospect's own public website (fetched through the
     * SSRF guard — real external evidence, provenance-labeled). It still never
     * claims facts from anywhere else.
     */
    public function researchLead(Contact $contact): string
    {
        $company = $contact->company_id !== null ? $contact->company : null;

        return $this->gateway->run('sales.research', 'sales.research', [
            'profile' => sprintf(
                "Contact: %s\nTitle: %s\nCompany: %s\nLifecycle: %s\nLead score: %d",
                $contact->fullName(), $contact->title ?? 'unknown',
                $company->name ?? 'unknown', $contact->lifecycle_stage, $contact->lead_score,
            )."\n".$this->enrichmentEvidence($contact)."\n".$this->siteEvidence($company),
        ])->text;
    }

    /**
     * AISA-006: the provider-labeled enrichment block. Firmographics come from
     * the EnrichmentProvider seam — never from the model — and the block says
     * which driver supplied them, or plainly that none did.
     */
    private function enrichmentEvidence(Contact $contact): string
    {
        $provider = app(EnrichmentProvider::class);

        if ($contact->email === null || $contact->email === '') {
            return 'No enrichment data: the contact has no email address to look up.';
        }

        $data = $provider->enrich((string) $contact->email);
        if ($data['company_name'] === null) {
            return sprintf('No enrichment data for this address (driver: %s).', $provider->name());
        }

        return sprintf(
            "Enrichment data (driver: %s%s):\nCompany: %s\nIndustry: %s\nEmployees: %s\nRegion: %s",
            $provider->name(),
            $provider->name() === 'fixture' ? ' - simulated' : '',
            $data['company_name'], $data['industry'] ?? 'unknown',
            $data['employee_range'] ?? 'unknown', $data['region'] ?? 'unknown',
        );
    }

    public function researchAccount(Company $company): string
    {
        $contacts = Contact::where('company_id', $company->id)->count();

        return $this->gateway->run('sales.research', 'sales.research', [
            'profile' => sprintf("Company: %s\nIndustry: %s\nKnown contacts: %d", $company->name, $company->industry ?? 'unknown', $contacts)
                ."\n".$this->siteEvidence($company),
        ])->text;
    }

    /**
     * The provenance-labeled external grounding block: what the prospect's own
     * site says, or an explicit statement that only CRM records were used —
     * honesty in both directions, never silence.
     */
    private function siteEvidence(?Company $company): string
    {
        $page = $this->sites->read($company?->website ?: $company?->domain);

        if ($page === null) {
            return 'No public website could be read; this summary uses only the CRM records above.';
        }

        return sprintf(
            "Observed on their public website (%s, fetched %s):\nTitle: %s\nDescription: %s\nHeadings: %s\nExcerpt: %s",
            $page['url'], now()->toDateString(), $page['title'], $page['description'],
            implode(' | ', $page['headings']), $page['excerpt'],
        );
    }

    /**
     * AI lead scoring (AISA-012) — advisory only. It is returned to the caller
     * and never written over the deterministic Stage 10 lead score.
     *
     * @return array{score: int, reason: string}
     */
    public function scoreLead(Contact $contact): array
    {
        return $this->recordScore($contact, $this->parseScore($this->gateway->run('sales.score_lead', 'sales.score', [
            'profile' => sprintf(
                "Lead: %s\nTitle: %s\nLifecycle: %s\nDeterministic score: %d\nIntent score: %d",
                $contact->fullName(), $contact->title ?? 'unknown', $contact->lifecycle_stage,
                $contact->lead_score, $this->intent->intentScore($contact),
            ),
        ])->text));
    }

    /**
     * AI opportunity scoring (AISA-013) — advisory only.
     *
     * @return array{score: int, reason: string}
     */
    public function scoreOpportunity(Deal $deal): array
    {
        return $this->recordScore($deal, $this->parseScore($this->gateway->run('sales.score_deal', 'sales.score', [
            'profile' => sprintf(
                "Deal: %s\nValue: %d\nStage: %s\nSource: %s",
                $deal->name, $deal->value, $deal->stage->name, $deal->lead_source ?? 'unknown',
            ),
        ])->text));
    }

    /**
     * Persist advisory-score history (AISA-012/013) so calibration can measure
     * predictive quality against real outcomes. History only — the
     * deterministic lead_score is never written from here.
     *
     * @param  array{score: int, reason: string}  $parsed
     * @return array{score: int, reason: string}
     */
    private function recordScore(Model $scoreable, array $parsed): array
    {
        AiScore::create([
            'scoreable_type' => $scoreable->getMorphClass(),
            'scoreable_id' => $scoreable->getKey(),
            'score' => $parsed['score'],
            'reason' => $parsed['reason'],
        ]);

        return $parsed;
    }

    /**
     * AI proposal assistance (AISA-016) — returns an outline for a human.
     */
    public function draftProposal(Deal $deal): string
    {
        return $this->gateway->run('sales.proposal', 'sales.proposal', [
            'company' => $deal->company_id !== null ? $deal->company->name : 'the client',
            'deal' => $deal->name,
            'value' => $deal->value,
            'notes' => $deal->lead_source ?? '',
        ])->text;
    }

    /**
     * AI CRM updating (AISA-015). PROPOSES a change; a human must confirm before
     * anything is written to the contact.
     *
     * @param  array<string, mixed>  $changes
     */
    public function proposeCrmUpdate(Contact $contact, array $changes): AiAction
    {
        return $this->actions->propose(
            'update_crm',
            'Update '.$contact->fullName().': '.implode(', ', array_keys($changes)),
            ['changes' => $changes],
            $contact,
        );
    }

    /**
     * AI appointment booking (AISA-004). PROPOSES a meeting; a human must
     * confirm before a booking is created.
     *
     * @param  array<string, mixed>  $details
     */
    public function proposeBooking(Contact $contact, array $details): AiAction
    {
        return $this->actions->propose(
            'book_meeting',
            'Book a meeting with '.$contact->fullName(),
            $details,
            $contact,
        );
    }

    /**
     * Propose sending a drafted email (never sends directly).
     */
    public function proposeEmail(Contact $contact, string $subject, string $body): AiAction
    {
        return $this->actions->propose(
            'send_email',
            'Send "'.$subject.'" to '.$contact->fullName(),
            ['to' => $contact->email, 'subject' => $subject, 'body' => $body],
            $contact,
        );
    }

    /**
     * @return array{score: int, reason: string}
     */
    private function parseScore(string $text): array
    {
        $score = (int) ($this->field($text, 'SCORE') ?? 0);

        return [
            'score' => max(0, min(100, $score)),
            'reason' => $this->field($text, 'REASON') ?? '',
        ];
    }

    private function field(string $text, string $label): ?string
    {
        return preg_match('/'.$label.':\s*(.+)/i', $text, $m) === 1 ? trim($m[1]) : null;
    }
}
