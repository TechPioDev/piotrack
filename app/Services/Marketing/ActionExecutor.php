<?php

namespace App\Services\Marketing;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\MarketingList;
use App\Models\RetargetingAudience;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Notifications\WorkflowNotification;
use App\Services\Advertising\RetargetingService;
use App\Support\NotificationDispatcher;

/**
 * Executes a single workflow step's action against a contact (AUTO-018…028).
 * Each action is best-effort and self-contained; a missing target (e.g. a
 * deleted list) is a no-op rather than a failure that stalls the enrollment.
 */
class ActionExecutor
{
    public function __construct(
        private MessageDispatcher $dispatcher,
        private ListService $lists,
        private NotificationDispatcher $notifications,
    ) {}

    public function execute(WorkflowStep $step, Contact $contact, Workflow $workflow): void
    {
        $config = $step->action_config ?? [];

        match ($step->action_type) {
            'send_email' => $this->dispatcher->sendEmail(
                $contact,
                (string) ($config['subject'] ?? ''),
                (string) ($config['body'] ?? ''),
                'automation',
                $workflow->id,
            ),
            'send_sms' => $this->dispatcher->sendSms(
                $contact,
                (string) ($config['body'] ?? ''),
                'automation',
                $workflow->id,
            ),
            'assign' => $this->assign($contact, $config),
            'create_task', 'schedule_follow_up' => $this->createTask($contact, $step, $config),
            'update_crm' => $this->updateCrm($contact, $config),
            'change_score' => $this->changeScore($contact, $config),
            'change_lifecycle' => $this->changeLifecycle($contact, $config),
            'notify' => $this->notify($workflow, $config),
            'add_to_list' => $this->listMembership($contact, $config, add: true),
            'remove_from_list' => $this->listMembership($contact, $config, add: false),
            'add_to_audience' => $this->addToAudience($contact, $config),
            default => null,
        };
    }

    /**
     * AUTO-027: drop the contact into a LIST-sourced retargeting audience —
     * membership is list-derived, so every export (CSV, customer match)
     * includes them immediately. Rule-sourced audiences compute membership
     * from their rules; faking a per-contact add there would lie, so the
     * action only touches list-backed audiences.
     *
     * @param  array<string, mixed>  $config
     */
    private function addToAudience(Contact $contact, array $config): void
    {
        if (! isset($config['audience_id'])) {
            return;
        }

        $audience = RetargetingAudience::find((int) $config['audience_id']);
        if ($audience === null || $audience->source !== 'list' || $audience->marketing_list_id === null) {
            return;
        }

        $list = MarketingList::find((int) $audience->marketing_list_id);
        if ($list === null) {
            return;
        }

        $this->lists->addContact($list, $contact);
        app(RetargetingService::class)->rebuild($audience);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assign(Contact $contact, array $config): void
    {
        if (isset($config['user_id'])) {
            $contact->update(['owner_id' => (int) $config['user_id']]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function createTask(Contact $contact, WorkflowStep $step, array $config): void
    {
        $dueDays = (int) ($config['due_in_days'] ?? ($step->action_type === 'schedule_follow_up' ? 3 : 0));

        Activity::create([
            'subject_type' => 'contact',
            'subject_id' => $contact->id,
            'type' => 'task',
            'title' => (string) ($config['title'] ?? 'Follow up'),
            'body' => (string) ($config['body'] ?? ''),
            'due_at' => $dueDays > 0 ? now()->addDays($dueDays) : now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function updateCrm(Contact $contact, array $config): void
    {
        $updates = array_intersect_key($config, array_flip(['title', 'lead_source', 'campaign', 'lifecycle_stage']));

        if ($updates !== []) {
            $contact->update($updates);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function changeScore(Contact $contact, array $config): void
    {
        $delta = (int) ($config['delta'] ?? 0);
        $contact->update(['lead_score' => max(0, $contact->lead_score + $delta)]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function changeLifecycle(Contact $contact, array $config): void
    {
        if (isset($config['stage'])) {
            $contact->update(['lifecycle_stage' => (string) $config['stage']]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function notify(Workflow $workflow, array $config): void
    {
        $organization = $workflow->organization()->first();

        if ($organization !== null) {
            $this->notifications->toOrganizationOwners(
                $organization,
                new WorkflowNotification((string) ($config['message'] ?? "Workflow \"{$workflow->name}\" fired."), '/marketing/automation'),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function listMembership(Contact $contact, array $config, bool $add): void
    {
        if (! isset($config['list_id'])) {
            return;
        }

        $list = MarketingList::find((int) $config['list_id']);

        if ($list === null) {
            return;
        }

        $add ? $this->lists->addContact($list, $contact) : $this->lists->removeContact($list, $contact);
    }
}
