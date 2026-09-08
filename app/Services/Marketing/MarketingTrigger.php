<?php

namespace App\Services\Marketing;

use App\Models\Contact;
use App\Models\Workflow;

/**
 * Fires automation triggers (AUTO-001…008). Given a trigger type + contact, it
 * enrolls the contact in every active workflow listening for that trigger whose
 * optional config matches the context. Callers: lead capture (form_submission),
 * CRM stage changes (lead_stage/deal_stage), engagement tracking
 * (email_engagement), list add (list_added).
 */
class MarketingTrigger
{
    public function __construct(private WorkflowEngine $engine) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function fire(string $triggerType, Contact $contact, array $context = []): int
    {
        $workflows = Workflow::where('trigger_type', $triggerType)
            ->where('status', 'active')
            ->get()
            ->filter(fn (Workflow $w) => $this->matches($w, $context));

        $enrolled = 0;

        foreach ($workflows as $workflow) {
            if ($this->engine->enroll($workflow, $contact) !== null) {
                $enrolled++;
            }
        }

        return $enrolled;
    }

    /**
     * A workflow's trigger_config may pin the trigger to a specific target
     * (e.g. {form_id: 5} or {stage: 'qualified'}). Empty config matches any.
     * Two config-key shapes carry semantics beyond equality: `path_contains`
     * substring-matches the visited path (AUTO-003) and `min_*` keys are
     * numeric floors against the same-named context value (AUTO-008).
     *
     * @param  array<string, mixed>  $context
     */
    private function matches(Workflow $workflow, array $context): bool
    {
        $config = $workflow->trigger_config ?? [];

        foreach ($config as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($key === 'path_contains') {
                if (! str_contains(mb_strtolower((string) ($context['path'] ?? '')), mb_strtolower((string) $value))) {
                    return false;
                }

                continue;
            }

            if (str_starts_with((string) $key, 'min_')) {
                if ((float) ($context[substr((string) $key, 4)] ?? 0) < (float) $value) {
                    return false;
                }

                continue;
            }

            if (($context[$key] ?? null) != $value) {
                return false;
            }
        }

        return true;
    }
}
