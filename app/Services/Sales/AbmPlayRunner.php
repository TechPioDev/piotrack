<?php

namespace App\Services\Sales;

use App\Models\Activity;
use App\Models\TargetAccount;
use App\Services\Advertising\RetargetingService;
use App\Services\Ai\AiSalesAgent;
use App\Support\AuditLogger;
use RuntimeException;
use Throwable;

/**
 * Sales & marketing orchestration (ABM-016/019): named, runnable plays that
 * coordinate machinery that is already built and tested — committee mapping,
 * AI drafting, CRM tasks, tier lists, retargeting audiences. Every play
 * reports exactly what it did step by step, and nothing a play does reaches a
 * third party: drafts land as tasks for a human, audiences are built for a
 * human to export.
 */
class AbmPlayRunner
{
    public const PLAYS = [
        'executive_outreach' => 'Draft a personalized intro for every decision-maker and queue it as a task for the rep — nothing is sent.',
        'account_retargeting' => 'Sync the tier committee list, build the retargeting audience (customers excluded), ready for platform export.',
    ];

    public function __construct(
        private AccountService $accounts,
        private AiSalesAgent $agent,
        private RetargetingService $retargeting,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{play: string, steps: list<array{step: string, detail: string}>}
     */
    public function run(TargetAccount $account, string $play, ?int $userId = null): array
    {
        if (! array_key_exists($play, self::PLAYS)) {
            throw new RuntimeException('Unknown play.');
        }

        $steps = match ($play) {
            'executive_outreach' => $this->executiveOutreach($account, $userId),
            default => $this->accountRetargeting($account),
        };

        $this->audit->log('sales.abm.play_run', context: ['play' => $play, 'steps' => count($steps)], resourceType: 'target_account', resourceId: (string) $account->id, organizationId: $account->organization_id);

        return ['play' => $play, 'steps' => $steps];
    }

    /**
     * ABM-016: one AI-drafted intro per decision-maker, queued as a CRM task —
     * a human reviews, personalizes and sends. No decision-makers means the
     * play says so and does nothing, instead of inventing targets.
     *
     * @return list<array{step: string, detail: string}>
     */
    private function executiveOutreach(TargetAccount $account, ?int $userId): array
    {
        $company = $account->company->name;
        $decisionMakers = $this->accounts->buyingCommittee($account)
            ->filter(fn ($contact) => $this->accounts->isDecisionMaker($contact))->values();

        if ($decisionMakers->isEmpty()) {
            return [[
                'step' => 'no_decision_makers',
                'detail' => 'No decision-makers identified on the committee — assign buying roles (or titles) first.',
            ]];
        }

        $steps = [];
        foreach ($decisionMakers as $contact) {
            try {
                $draft = $this->agent->draftEmail($contact, 'executive introduction', "Target account: {$company}, tier {$account->tier}");
            } catch (Throwable) {
                // No AI credits or provider trouble: the task still queues, the
                // rep just writes the note themselves.
                $draft = '(AI draft unavailable — write a short personal intro referencing why '.$company.' is a fit.)';
            }

            Activity::create([
                'subject_type' => 'contact',
                'subject_id' => $contact->id,
                'type' => 'task',
                'user_id' => $userId,
                'title' => 'Executive outreach: '.$contact->fullName().' ('.$company.')',
                'body' => $draft,
                'due_at' => now()->addDays(2),
            ]);

            $steps[] = ['step' => 'task_queued', 'detail' => $contact->fullName().' — intro drafted, task due in 2 days.'];
        }

        return $steps;
    }

    /**
     * ABM-014 as a play: the account's tier committee becomes a rebuilt
     * retargeting audience, ready for the existing platform exports.
     *
     * @return list<array{step: string, detail: string}>
     */
    private function accountRetargeting(TargetAccount $account): array
    {
        $audience = $this->accounts->createRetargetingAudience($account->tier, $this->retargeting);

        return [
            ['step' => 'list_synced', 'detail' => "Tier {$account->tier} committee list refreshed."],
            ['step' => 'audience_ready', 'detail' => '"'.$audience->name.'" rebuilt with '.$audience->member_count.' members (existing customers excluded) — export it from Ads → Retargeting.'],
        ];
    }
}
