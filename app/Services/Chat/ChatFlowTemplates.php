<?php

namespace App\Services\Chat;

/**
 * Starter conversations a tenant can drop into a widget and then edit (spec §57).
 * Templates are ordinary flow graphs — nothing about them is special or locked,
 * so once applied they behave exactly like a hand-built conversation.
 */
class ChatFlowTemplates
{
    /**
     * @return list<array{key: string, name: string, description: string, steps: int}>
     */
    public function catalog(): array
    {
        return array_map(fn (array $t) => [
            'key' => $t['key'],
            'name' => $t['name'],
            'description' => $t['description'],
            'steps' => count($t['flow']['nodes']),
        ], $this->all());
    }

    /**
     * @return array{start: string, nodes: array<string, array<string, mixed>>}|null
     */
    public function flow(string $key): ?array
    {
        foreach ($this->all() as $template) {
            if ($template['key'] === $key) {
                return $template['flow'];
            }
        }

        return null;
    }

    /**
     * @return list<array{key: string, name: string, description: string, flow: array{start: string, nodes: array<string, array<string, mixed>>}}>
     */
    private function all(): array
    {
        return [
            [
                'key' => 'msp_qualification',
                'name' => 'MSP lead qualification',
                'description' => 'The full managed-IT qualification: service interest, company size, current provider, biggest challenge, contact details and a meeting offer.',
                'flow' => DefaultChatFlow::definition(),
            ],
            [
                'key' => 'cybersecurity',
                'name' => 'Cybersecurity lead qualification',
                'description' => 'Focused on security buyers, with a high-priority route for anyone reporting a live incident.',
                'flow' => $this->cybersecurity(),
            ],
            [
                'key' => 'cmmc',
                'name' => 'CMMC readiness assessment',
                'description' => 'Qualifies defence-contract suppliers on CMMC level, timeline and contract exposure.',
                'flow' => $this->cmmc(),
            ],
            [
                'key' => 'consultation',
                'name' => 'Book a consultation',
                'description' => 'The short path: name, email, company, and straight to booking a call.',
                'flow' => $this->consultation(),
            ],
            [
                'key' => 'existing_customer',
                'name' => 'Existing customer routing',
                'description' => 'Sends current clients to support, billing or their account manager instead of treating them as new leads.',
                'flow' => $this->existingCustomer(),
            ],
            [
                'key' => 'after_hours',
                'name' => 'After-hours lead capture',
                'description' => 'A short out-of-hours form that captures details and sets expectations for a reply.',
                'flow' => $this->afterHours(),
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function cybersecurity(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Looking for help with cybersecurity?', 'next' => 'q_need'],
                'q_need' => [
                    'type' => 'choice',
                    'text' => 'What are you primarily looking for?',
                    'field' => 'security_need',
                    'options' => [
                        ['id' => 'assessment', 'label' => 'Security assessment', 'score' => 10, 'next' => 'q_size'],
                        ['id' => 'mdr', 'label' => 'MDR / SOC', 'score' => 15, 'next' => 'q_size'],
                        ['id' => 'compliance', 'label' => 'Compliance', 'score' => 10, 'next' => 'q_size'],
                        ['id' => 'insurance', 'label' => 'Cyber insurance requirements', 'score' => 10, 'next' => 'q_size'],
                        ['id' => 'incident', 'label' => 'We may have a security incident', 'score' => 30, 'next' => 'msg_incident', 'priority' => 'high'],
                        ['id' => 'general', 'label' => 'General cybersecurity help', 'score' => 5, 'next' => 'q_size'],
                    ],
                ],
                'msg_incident' => [
                    'type' => 'message',
                    'text' => 'Understood — we treat active incidents as a priority. Leave your details and our security team will contact you immediately. Please do not share any technical details here.',
                    'next' => 'in_name',
                ],
                'q_size' => [
                    'type' => 'choice',
                    'text' => 'How many employees do you have?',
                    'field' => 'company_size',
                    'options' => [
                        ['id' => '1-10', 'label' => '1–10', 'score' => 0, 'next' => 'q_timeline'],
                        ['id' => '11-50', 'label' => '11–50', 'score' => 10, 'next' => 'q_timeline'],
                        ['id' => '51-250', 'label' => '51–250', 'score' => 20, 'next' => 'q_timeline'],
                        ['id' => '250+', 'label' => '250+', 'score' => 15, 'next' => 'q_timeline'],
                    ],
                ],
                'q_timeline' => [
                    'type' => 'choice',
                    'text' => 'What is your timeline?',
                    'field' => 'timeline',
                    'options' => [
                        ['id' => 'urgent', 'label' => 'Immediately', 'score' => 25, 'next' => 'in_name'],
                        ['id' => '90days', 'label' => 'Within 90 days', 'score' => 20, 'next' => 'in_name'],
                        ['id' => 'exploring', 'label' => 'Just exploring', 'score' => 0, 'next' => 'in_name'],
                    ],
                ],
                'in_name' => ['type' => 'input', 'input' => 'text', 'field' => 'first_name', 'text' => 'What is your first name?', 'next' => 'in_email'],
                'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'What is the best work email to reach you?', 'next' => 'in_company'],
                'in_company' => ['type' => 'input', 'input' => 'company', 'field' => 'company_name', 'text' => 'What company are you with?', 'next' => 'q_meeting'],
                'q_meeting' => [
                    'type' => 'choice',
                    'text' => 'Would you like a 30-minute security consultation?',
                    'field' => 'wants_meeting',
                    'options' => [
                        ['id' => 'yes', 'label' => 'Yes — schedule it', 'score' => 30, 'next' => 'end_meeting'],
                        ['id' => 'no', 'label' => 'Not right now', 'score' => 0, 'next' => 'end_thanks'],
                    ],
                ],
                'end_meeting' => ['type' => 'end', 'outcome' => 'meeting', 'text' => 'Perfect — pick a time that suits you.'],
                'end_thanks' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks! Our security team will be in touch shortly.'],
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function cmmc(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Working towards CMMC compliance? Let us find out where you stand.', 'next' => 'q_level'],
                'q_level' => [
                    'type' => 'choice',
                    'text' => 'Which CMMC level do you need to meet?',
                    'field' => 'cmmc_level',
                    'options' => [
                        ['id' => 'level1', 'label' => 'Level 1', 'score' => 10, 'next' => 'q_contract'],
                        ['id' => 'level2', 'label' => 'Level 2', 'score' => 20, 'next' => 'q_contract'],
                        ['id' => 'unsure', 'label' => 'Not sure yet', 'score' => 10, 'next' => 'q_contract'],
                    ],
                ],
                'q_contract' => [
                    'type' => 'choice',
                    'text' => 'Do you currently hold or bid on DoD contracts?',
                    'field' => 'dod_contracts',
                    'options' => [
                        ['id' => 'hold', 'label' => 'We hold contracts', 'score' => 25, 'next' => 'q_timeline'],
                        ['id' => 'bidding', 'label' => 'We are bidding', 'score' => 20, 'next' => 'q_timeline'],
                        ['id' => 'exploring', 'label' => 'Exploring the market', 'score' => 5, 'next' => 'q_timeline'],
                    ],
                ],
                'q_timeline' => [
                    'type' => 'choice',
                    'text' => 'When do you need to be compliant?',
                    'field' => 'timeline',
                    'options' => [
                        ['id' => '90days', 'label' => 'Within 90 days', 'score' => 25, 'next' => 'in_name'],
                        ['id' => '6months', 'label' => 'Within 6 months', 'score' => 15, 'next' => 'in_name'],
                        ['id' => 'later', 'label' => 'Later than that', 'score' => 5, 'next' => 'in_name'],
                    ],
                ],
                'in_name' => ['type' => 'input', 'input' => 'text', 'field' => 'first_name', 'text' => 'What is your first name?', 'next' => 'in_email'],
                'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'And your work email?', 'next' => 'in_company'],
                'in_company' => ['type' => 'input', 'input' => 'company', 'field' => 'company_name', 'text' => 'What company are you with?', 'next' => 'end_meeting'],
                'end_meeting' => ['type' => 'end', 'outcome' => 'meeting', 'text' => 'Thanks — book a CMMC readiness call and we will map out your gaps.'],
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function consultation(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Happy to set up a quick call — just three details.', 'next' => 'in_name'],
                'in_name' => ['type' => 'input', 'input' => 'text', 'field' => 'first_name', 'text' => 'What is your name?', 'next' => 'in_email'],
                'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'What is your work email?', 'next' => 'in_company'],
                'in_company' => ['type' => 'input', 'input' => 'company', 'field' => 'company_name', 'text' => 'And your company?', 'next' => 'score_intent'],
                'score_intent' => ['type' => 'score', 'points' => 25, 'next' => 'end_meeting'],
                'end_meeting' => ['type' => 'end', 'outcome' => 'meeting', 'text' => 'Great — choose a time that works for you.'],
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function existingCustomer(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Are you an existing client?', 'next' => 'q_who'],
                'q_who' => [
                    'type' => 'choice',
                    'text' => 'How can we help today?',
                    'field' => 'visitor_type',
                    'options' => [
                        ['id' => 'support', 'label' => 'Technical support', 'score' => 0, 'next' => 'end_support'],
                        ['id' => 'billing', 'label' => 'Billing question', 'score' => 0, 'next' => 'end_support'],
                        ['id' => 'account', 'label' => 'Speak to my account manager', 'score' => 0, 'next' => 'end_support'],
                        ['id' => 'project', 'label' => 'Project question', 'score' => 0, 'next' => 'end_support'],
                        ['id' => 'new', 'label' => 'I am not a client yet', 'score' => 10, 'next' => 'in_name'],
                    ],
                ],
                'in_name' => ['type' => 'input', 'input' => 'text', 'field' => 'first_name', 'text' => 'Welcome! What is your name?', 'next' => 'in_email'],
                'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'And the best email to reach you?', 'next' => 'end_lead'],
                'end_support' => ['type' => 'end', 'outcome' => 'support', 'text' => 'Thanks — our team has been notified and will follow up through your usual support channel.'],
                'end_lead' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks! Someone will reach out shortly.'],
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function afterHours(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => [
                    'type' => 'message',
                    'text' => 'Thanks for getting in touch. Our team is offline right now, but leave your details and we will reply next business day.',
                    'next' => 'in_name',
                ],
                'in_name' => ['type' => 'input', 'input' => 'text', 'field' => 'first_name', 'text' => 'What is your name?', 'next' => 'in_email'],
                'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'What email should we reply to?', 'next' => 'in_message'],
                'in_message' => ['type' => 'input', 'input' => 'text', 'field' => 'enquiry', 'text' => 'Briefly, what do you need help with?', 'next' => 'end_thanks'],
                'end_thanks' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Got it — we will be in touch first thing next business day.'],
            ],
        ];
    }
}
